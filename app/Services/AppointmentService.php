<?php

namespace App\Services;

use App\Events\AppointmentCancelled;
use App\Exceptions\SlotConflictException;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicSession;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(private NotificationService $notifications) {}

    public function checkIn(Appointment $appointment, User $staff): Appointment
    {
        $this->guardFacilityScope($appointment, $staff);
        if (! $appointment->canTransitionTo(Appointment::STATUS_CHECKED_IN)) {
            throw new \InvalidArgumentException("Cannot check in appointment with status [{$appointment->status}].");
        }

        return DB::transaction(function () use ($appointment, $staff) {
            $appointment->update([
                'status' => Appointment::STATUS_CHECKED_IN,
                'checked_in_at' => now(),
                'checked_in_by' => $staff->id,
            ]);
            $appointment->refresh();
            $this->notifications->notifyPatientCheckedIn($appointment);

            return $appointment;
        });
    }

    public function startConsultation(Appointment $appointment, Doctor $doctor): Appointment
    {
        $this->guardDoctorScope($appointment, $doctor);
        if (! $appointment->canTransitionTo(Appointment::STATUS_IN_PROGRESS)) {
            throw new \InvalidArgumentException("Cannot start consultation with status [{$appointment->status}].");
        }

        return DB::transaction(function () use ($appointment) {
            $appointment->update([
                'status' => Appointment::STATUS_IN_PROGRESS,
                'consultation_started_at' => now(),
            ]);

            return $appointment->refresh();
        });
    }

    public function completeConsultation(Appointment $appointment, Doctor $doctor): Appointment
    {
        $this->guardDoctorScope($appointment, $doctor);
        if (! $appointment->canTransitionTo(Appointment::STATUS_COMPLETED)) {
            throw new \InvalidArgumentException("Cannot complete appointment with status [{$appointment->status}].");
        }

        return DB::transaction(function () use ($appointment) {
            $appointment->update([
                'status' => Appointment::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
            $appointment->refresh();
            $this->notifications->notifyPatientAppointmentCompleted($appointment);

            return $appointment;
        });
    }

    public function markNoShow(Appointment $appointment, User $staff): Appointment
    {
        $this->guardFacilityScope($appointment, $staff);
        if (! $appointment->canTransitionTo(Appointment::STATUS_NO_SHOW)) {
            throw new \InvalidArgumentException("Cannot mark no-show for appointment with status [{$appointment->status}].");
        }

        return DB::transaction(function () use ($appointment, $staff) {
            $appointment->update([
                'status' => Appointment::STATUS_NO_SHOW,
                'no_show_at' => now(),
                'no_show_by' => $staff->id,
            ]);

            return $appointment->refresh();
        });
    }

    public function facilityCancel(Appointment $appointment, User $staff, ?string $reason = null): Appointment
    {
        $this->guardFacilityScope($appointment, $staff);
        if (! $appointment->canTransitionTo(Appointment::STATUS_CANCELLED)) {
            throw new \InvalidArgumentException("Cannot cancel appointment with status [{$appointment->status}].");
        }

        return $this->cancel($appointment, $staff, $reason);
    }

    /**
     * Phase 17: Patient-initiated cancellation.
     * IDOR protected: only the owning patient (or a super admin acting on their behalf).
     */
    public function patientCancel(Appointment $appointment, User $patient, ?string $reason = null): Appointment
    {
        if (! $patient->isSuperAdmin() && $appointment->user_id !== $patient->id) {
            throw new \RuntimeException('You are not authorized to cancel this appointment.');
        }
        if (! $appointment->canTransitionTo(Appointment::STATUS_CANCELLED)) {
            throw new \InvalidArgumentException("Cannot cancel appointment with status [{$appointment->status}].");
        }

        return $this->cancel($appointment, $patient, $reason);
    }

    /**
     * Phase 17: Doctor-initiated cancellation of one of their own appointments.
     */
    public function doctorCancel(Appointment $appointment, User $doctorUser, ?string $reason = null): Appointment
    {
        $doctor = $doctorUser->doctor;
        if (! $doctor && ! $doctorUser->isSuperAdmin()) {
            throw new \RuntimeException('Only the doctor who owns the clinic can cancel this appointment.');
        }
        if (! $doctorUser->isSuperAdmin()) {
            $this->guardDoctorScope($appointment, $doctor);
        }
        if (! $appointment->canTransitionTo(Appointment::STATUS_CANCELLED)) {
            throw new \InvalidArgumentException("Cannot cancel appointment with status [{$appointment->status}].");
        }

        return $this->cancel($appointment, $doctorUser, $reason);
    }

    private function cancel(Appointment $appointment, User $actor, ?string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $reason) {
            $this->audit(
                $appointment,
                'appointment.cancelled',
                $actor,
                ['status' => $appointment->status, 'cancelled_by' => $appointment->cancelled_by],
                ['status' => Appointment::STATUS_CANCELLED, 'cancelled_by' => $actor->id],
                $reason,
            );
            $appointment->update([
                'status' => Appointment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ]);
            $this->releaseSlot($appointment->clinic_session_id);
            $appointment->refresh();
            // Single notification path: the listener informs patient AND doctor.
            AppointmentCancelled::dispatch($appointment);

            return $appointment;
        });
    }

    /**
     * Phase 17: Patient rescheduling.
     *
     * Rules enforced here (backend authoritative):
     *  - only the owning patient (or super admin) may reschedule
     *  - destination session must belong to the SAME doctor (preserves historical truth)
     *  - destination session must be confirmed, future and bookable with capacity
     *  - destination slot must be free (locked read, 409 on conflict)
     *  - the old appointment row is MUTATED but its previous context is preserved
     *    in notes + audit log (existing repository convention, no duplicated data)
     */
    public function reschedule(
        Appointment $appointment,
        User $patient,
        ClinicSession $newSession,
        string $newStartTime,
        ?string $reason = null
    ): Appointment {
        if (! $patient->isSuperAdmin() && $appointment->user_id !== $patient->id) {
            throw new \RuntimeException('You are not authorized to reschedule this appointment.');
        }
        if (! in_array($appointment->status, [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED], true)) {
            throw new \InvalidArgumentException("Cannot reschedule appointment with status [{$appointment->status}].");
        }

        if ($newSession->status !== 'confirmed') {
            throw new \InvalidArgumentException('The selected clinic session is not available.');
        }
        if ($newSession->session_date->lt(today())) {
            throw new \InvalidArgumentException('Cannot reschedule to a past date.');
        }
        if (! $newSession->is_bookable) {
            throw new \InvalidArgumentException('This session is no longer available for booking.');
        }
        if ($newSession->doctor_id !== $appointment->doctor_id) {
            throw new \InvalidArgumentException('The selected clinic session does not belong to the same doctor.');
        }

        $newStart = Carbon::parse($newSession->session_date->format('Y-m-d').' '.$newStartTime);
        if ($newStart->format('H:i') !== $newStartTime) {
            throw new \InvalidArgumentException('Invalid appointment time.');
        }

        return DB::transaction(function () use ($appointment, $newSession, $newStart, $newStartTime, $reason, $patient) {
            $lockedSession = ClinicSession::where('id', $newSession->id)->lockForUpdate()->first();

            $conflict = Appointment::where('clinic_session_id', $lockedSession->id)
                ->where('start_time', $newStart->format('H:i:s'))
                ->where('id', '!=', $appointment->id)
                ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw SlotConflictException::taken($newStartTime);
            }

            if ($lockedSession->max_appointments !== null
                && $lockedSession->booked_appointments >= $lockedSession->max_appointments) {
                throw new \InvalidArgumentException('No available slots remain for this session.');
            }

            $oldContext = [
                'session_id' => $appointment->clinic_session_id,
                'facility_id' => $appointment->facility_id,
                'date' => $appointment->appointment_date->toDateString(),
                'start_time' => substr($appointment->start_time, 0, 5),
                'end_time' => substr($appointment->end_time, 0, 5),
            ];

            $this->audit(
                $appointment,
                'appointment.rescheduled',
                $patient,
                $oldContext,
                [
                    'session_id' => $lockedSession->id,
                    'facility_id' => $lockedSession->facility_id,
                    'date' => $lockedSession->session_date->toDateString(),
                    'start_time' => $newStart->format('H:i:s'),
                    'end_time' => $newStart->copy()->addMinutes($lockedSession->slot_duration_minutes)->format('H:i:s'),
                ],
                $reason,
            );

            $sessionChanged = $appointment->clinic_session_id !== $lockedSession->id;
            if ($sessionChanged) {
                $this->releaseSlot($appointment->clinic_session_id);
                $lockedSession->increment('booked_appointments');
            }

            $appointment->update([
                'clinic_session_id' => $lockedSession->id,
                'facility_id' => $lockedSession->facility_id,
                'facility_location_id' => $lockedSession->facility_location_id,
                'appointment_date' => $lockedSession->session_date,
                'start_time' => $newStart->format('H:i:s'),
                'end_time' => $newStart->copy()->addMinutes($lockedSession->slot_duration_minutes)->format('H:i:s'),
            ]);

            if ($sessionChanged) {
                $appointment->update([
                    'notes' => trim(($appointment->notes ? $appointment->notes."\n" : '').
                        '[Rescheduled from session #'.$oldContext['session_id'].
                        ' ('.$oldContext['date'].' '.$oldContext['start_time'].')'.
                        ' on '.now()->toDateTimeString().']'),
                ]);
            }

            $appointment->refresh();
            $this->notifications->notifyPatientAppointmentRescheduled($appointment, $oldContext);

            return $appointment;
        });
    }

    /**
     * Phase 17: Cancel a whole clinic session and every active appointment in it.
     *
     * - appointments are NOT hard-deleted (historical truth preserved)
     * - every active appointment is cancelled with the acting user recorded
     * - booked_appointments is released so cancelled capacity is not leaked
     * - the controller dispatches ClinicSessionCancelled which notifies patients,
     *   doctor, facility staff and followers exactly once
     */
    public function cancelSession(ClinicSession $session, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($session, $actor, $reason) {
            $session->update([
                'status' => ClinicSession::STATUS_CANCELLED,
                'cancellation_reason' => $reason ?? 'Cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            AuditLog::record(
                $actor->id,
                'clinic_session.cancelled',
                ClinicSession::class,
                $session->id,
                $session->facility?->name,
                ['status' => $session->status],
                ['status' => ClinicSession::STATUS_CANCELLED],
                $reason,
            );
        });
    }

    /**
     * Cancel the active appointments for a session and release their slots.
     * Used by both SessionController::cancel and facility rejection flows.
     */
    public function cancelSessionAppointments(ClinicSession $session, User $actor, ?string $reason = null): int
    {
        return DB::transaction(function () use ($session, $actor, $reason) {
            $appointments = $session->appointments()
                ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($appointments as $apt) {
                $apt->update([
                    'status' => Appointment::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason ?? 'Clinic session cancelled',
                    'cancelled_by' => $actor->id,
                ]);
                $this->audit($apt, 'appointment.cancelled.viaSession', $actor, ['status' => $apt->status], ['status' => Appointment::STATUS_CANCELLED], $reason);
                $count++;
            }

            if ($count > 0) {
                ClinicSession::where('id', $session->id)
                    ->where('booked_appointments', '>', 0)
                    ->decrement('booked_appointments', $count);
            }

            // Patient notifications are sent exactly once by the
            // ClinicSessionCancelled event listener (NotifySessionCancelledPatients),
            // which also informs the doctor, facility staff and followers.

            return $count;
        });
    }

    /**
     * Release one booked slot on a session (non-negative guard).
     */
    private function releaseSlot(?int $sessionId): void
    {
        if (! $sessionId) {
            return;
        }
        ClinicSession::where('id', $sessionId)
            ->where('booked_appointments', '>', 0)
            ->decrement('booked_appointments');
    }

    private function guardFacilityScope(Appointment $appointment, User $staff): void
    {
        if ($staff->isSuperAdmin()) {
            return;
        }
        if (! $staff->hasAnyRole(['facility-admin', 'facility-staff'])) {
            throw new \RuntimeException('Only facility staff can perform this action.');
        }
        $authorized = $staff->facilities()->where('facilities.id', $appointment->facility_id)->exists();
        if (! $authorized) {
            throw new \RuntimeException('You are not authorized to manage appointments at this facility.');
        }
    }

    private function guardDoctorScope(Appointment $appointment, Doctor $doctor): void
    {
        if ($appointment->doctor_id !== $doctor->id) {
            throw new \RuntimeException('You are not authorized to manage this appointment.');
        }
    }

    private function audit(Appointment $appointment, string $action, ?User $actor, ?array $before, ?array $after, ?string $reason = null): void
    {
        AuditLog::record(
            $actor?->id,
            $action,
            Appointment::class,
            $appointment->id,
            $appointment->appointment_number,
            $before,
            $after,
            $reason,
        );
    }
}
