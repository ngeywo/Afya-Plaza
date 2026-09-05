<?php

namespace App\Services;

use App\Jobs\SendDatabaseNotification;
use App\Models\ClinicSession;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function notifyFollowersClinicConfirmed(ClinicSession $session): void
    {
        $doctor = $session->doctor;
        if (!$doctor) return;
        $followerIds = $doctor->followers()->pluck('users.id')->all();
        if (empty($followerIds)) return;
        $facility = $session->facility;
        $facilityName = $facility?->name ?? 'a clinic';
        $facilityCity = $facility?->city ?? '';
        $city = $facilityCity ? " in {$facilityCity}" : '';
        $title = "Dr. {$doctor->display_name} will be at {$facilityName}{$city} soon.";
        $when = $session->session_date->format('M j, Y');
        $times = substr($session->start_time, 0, 5) . ' - ' . substr($session->end_time, 0, 5);
        $message = "{$when} {$times}.";
        foreach ($followerIds as $userId) {
            $this->push($userId, [
                'category' => 'clinic_confirmed',
                'title' => $title,
                'message' => $message,
                'doctor_id' => $doctor->id,
                'doctor_slug' => $doctor->slug,
                'doctor_name' => $doctor->display_name,
                'clinic_session_id' => $session->id,
                'facility_id' => $session->facility_id,
                'facility_name' => $facilityName,
                'facility_city' => $facilityCity,
                'date' => $session->session_date->format('Y-m-d'),
                'start_time' => substr($session->start_time, 0, 5),
                'end_time' => substr($session->end_time, 0, 5),
            ], "clinic_confirmed:{$session->id}");
        }
    }

    public function notifyPatientsSessionCancelled(ClinicSession $session, string $reason): void
    {
        $doctor = $session->doctor;
        $facility = $session->facility;
        $when = $session->session_date->format('M j, Y');
        $times = substr($session->start_time, 0, 5) . ' - ' . substr($session->end_time, 0, 5);
        $bookedUserIds = $session->appointments()
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('user_id')->unique()->all();
        foreach ($bookedUserIds as $userId) {
            // Lookup the actual affected appointment id so the patient can be
            // routed straight to the appointment detail page.
            $apptId = $session->appointments()
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'confirmed'])
                ->value('id');
            $this->push($userId, [
                'category' => 'appointment_session_cancelled',
                'title' => 'Your clinic appointment has been cancelled.',
                'message' => "{$facility?->name} {$when} {$times}. Please review alternatives.",
                'priority' => 'critical',
                'doctor_id' => $doctor?->id,
                'doctor_slug' => $doctor?->slug,
                'clinic_session_id' => $session->id,
                'appointment_id' => $apptId,
                'facility_id' => $session->facility_id,
                'facility_name' => $facility?->name,
                'date' => $session->session_date->format('Y-m-d'),
                'start_time' => substr($session->start_time, 0, 5),
                'reason' => $reason,
            ], "session_cancelled:{$session->id}:{$userId}");
        }
        // Notify the doctor that their session was cancelled.
        if ($doctor && $doctor->user_id) {
            $this->push($doctor->user_id, [
                'category' => 'doctor_session_cancelled',
                'title' => "Your clinic at {$facility?->name} was cancelled.",
                'message' => "{$when} {$times}. Reason: {$reason}",
                'priority' => 'important',
                'clinic_session_id' => $session->id,
                'facility_id' => $session->facility_id,
                'facility_name' => $facility?->name,
                'date' => $session->session_date->format('Y-m-d'),
                'reason' => $reason,
            ], "doctor_session_cancelled:{$session->id}");
        }
        // Notify facility admins for the affected facility.
        if ($session->facility_id) {
            $facilityAdminIds = \App\Models\User::whereHas('facilities', function ($q) use ($session) {
                $q->where('facilities.id', $session->facility_id);
            })->pluck('users.id')->all();
            foreach ($facilityAdminIds as $adminId) {
                $this->push($adminId, [
                    'category' => 'facility_session_cancelled',
                    'title' => "A clinic at {$facility?->name} was cancelled.",
                    'message' => "Dr. {$doctor?->display_name} — {$when} {$times}. Reason: {$reason}",
                    'priority' => 'important',
                    'doctor_id' => $doctor?->id,
                    'doctor_name' => $doctor?->display_name,
                    'clinic_session_id' => $session->id,
                    'facility_id' => $session->facility_id,
                    'facility_name' => $facility?->name,
                    'date' => $session->session_date->format('Y-m-d'),
                    'reason' => $reason,
                ], "facility_session_cancelled:{$session->id}:{$adminId}");
            }
        }
        if ($doctor) {
            $followerIds = $doctor->followers()->pluck('users.id')->all();
            foreach ($followerIds as $userId) {
                if (in_array($userId, $bookedUserIds, true)) continue;
                $this->push($userId, [
                    'category' => 'clinic_cancelled',
                    'title' => "Dr. {$doctor->display_name}\'s clinic at {$facility?->name} has been cancelled.",
                    'doctor_id' => $doctor->id,
                    'doctor_slug' => $doctor->slug,
                    'clinic_session_id' => $session->id,
                    'facility_id' => $session->facility_id,
                    'facility_name' => $facility?->name,
                    'date' => $session->session_date->format('Y-m-d'),
                    'reason' => $reason,
                ], "session_cancelled:{$session->id}:{$userId}");
            }
        }
    }

    public function notifyPatientAppointmentBooked(Appointment $appointment): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility', 'clinicSession']);
        $doctor = $apt->doctor;
        $facility = $apt->facility;
        $session = $apt->clinicSession;
        if (!$doctor || !$facility) return;
        $when = $apt->appointment_date->format('l, M j, Y');
        $time = substr($apt->start_time, 0, 5);
        $this->push($apt->user_id, [
            'category' => 'appointment_confirmed',
            'title' => "Your appointment with Dr. {$doctor->display_name} is confirmed.",
            'message' => "{$when} at {$facility->name}.",
            'appointment_id' => $apt->id,
            'doctor_id' => $doctor->id,
            'doctor_slug' => $doctor->slug,
            'clinic_session_id' => $session?->id,
            'facility_id' => $facility->id,
            'facility_name' => $facility->name,
            'date' => $apt->appointment_date->format('Y-m-d'),
        ], "appointment_booked:{$apt->id}");
    }

    public function notifyPatientAppointmentCancelled(Appointment $appointment): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility', 'clinicSession']);
        $doctor = $apt->doctor;
        $facility = $apt->facility;
        $session = $apt->clinicSession;
        $when = $apt->appointment_date->format('M j, Y');
        $time = substr($apt->start_time, 0, 5);
        $this->push($apt->user_id, [
            'category' => 'appointment_cancelled',
            'title' => 'Your appointment has been cancelled.',
            'message' => "Dr. {$doctor?->display_name} on {$when} at {$facility?->name}.",
            'appointment_id' => $apt->id,
            'doctor_id' => $doctor?->id,
            'doctor_slug' => $doctor?->slug,
            'clinic_session_id' => $session?->id,
            'facility_id' => $facility?->id,
            'facility_name' => $facility?->name,
            'date' => $apt->appointment_date->format('Y-m-d'),
        ], "appointment_cancelled:{$apt->id}");
    }

    /**
     * Phase 17: Notify the patient that their appointment was rescheduled.
     * The old context is the snapshot of what was booked before the change.
     */
    public function notifyPatientAppointmentRescheduled(Appointment $appointment, array $oldContext): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility']);
        $doctor = $apt->doctor;
        $facility = $apt->facility;
        $when = $apt->appointment_date->format('l, M j, Y');
        $time = substr($apt->start_time, 0, 5);

        $oldDate = $oldContext['date'] ?? '';
        $oldTime = $oldContext['start_time'] ?? '';
        $oldFacility = $oldContext['facility_name'] ?? '';
        $change = trim(implode(' ', array_filter([
            $oldDate && $oldTime ? "was {$oldDate} at {$oldTime}." : '',
            $oldFacility ? " Location changed from {$oldFacility}." : '',
        ])));

        $this->push($apt->user_id, [
            'category' => 'appointment_rescheduled',
            'title' => "Your appointment with Dr. {$doctor?->display_name} has been rescheduled.",
            'message' => "New time: {$when} at {$time} — {$facility?->name}. {$change}",
            'priority' => 'important',
            'appointment_id' => $apt->id,
            'doctor_id' => $doctor?->id,
            'doctor_slug' => $doctor?->slug,
            'clinic_session_id' => $apt->clinic_session_id,
            'facility_id' => $facility?->id,
            'facility_name' => $facility?->name,
            'date' => $apt->appointment_date->format('Y-m-d'),
            'start_time' => $time,
            'old_context' => $oldContext,
        ], "appointment_rescheduled:{$apt->id}");
    }

    /**
     * Phase 11: Notify the patient that they have been checked in.
     */
    public function notifyPatientCheckedIn(Appointment $appointment): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility']);
        $facility = $apt->facility;
        $time = substr($apt->start_time, 0, 5);
        $this->push($apt->user_id, [
            'category' => 'appointment_checked_in',
            'title' => "You've been checked in at {$facility?->name}.",
            'message' => "Please wait — Dr. {$apt->doctor?->display_name} will see you shortly.",
            'appointment_id' => $apt->id,
            'doctor_id' => $apt->doctor?->id,
            'doctor_slug' => $apt->doctor?->slug,
            'facility_id' => $facility?->id,
            'facility_name' => $facility?->name,
            'date' => $apt->appointment_date->format('Y-m-d'),
        ], "appointment_checked_in:{$apt->id}");
    }

    /**
     * Phase 11: Notify the patient that their consultation is complete.
     */
    public function notifyPatientAppointmentCompleted(Appointment $appointment): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility']);
        $time = substr($apt->start_time, 0, 5);
        $this->push($apt->user_id, [
            'category' => 'appointment_completed',
            'title' => 'Your visit is complete.',
            'message' => "Thank you for visiting Dr. {$apt->doctor?->display_name}. Take care.",
            'appointment_id' => $apt->id,
            'doctor_id' => $apt->doctor?->id,
            'doctor_slug' => $apt->doctor?->slug,
            'facility_id' => $apt->facility?->id,
            'facility_name' => $apt->facility?->name,
            'date' => $apt->appointment_date->format('Y-m-d'),
        ], "appointment_completed:{$apt->id}");
    }

    // ─── Phase 14: Doctor Notifications ─────────────────────────────────────────

    /**
     * Phase 14: Notify the doctor when a new appointment is booked with them.
     */
    public function notifyDoctorNewBooking(Appointment $appointment): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility', 'user']);
        $doctor = $apt->doctor;
        if (!$doctor || !$doctor->user_id) return;
        $when = $apt->appointment_date->format('M j, Y');
        $time = substr($apt->start_time, 0, 5);
        $this->push($doctor->user_id, [
            'category' => 'doctor_new_booking',
            'title' => 'New appointment booked.',
            'message' => "{$apt->user?->name} — {$when} at {$time}. {$apt->facility?->name}.",
            'priority' => 'important',
            'appointment_id' => $apt->id,
            'patient_name' => $apt->user?->name,
            'doctor_id' => $doctor->id,
            'facility_id' => $apt->facility?->id,
            'facility_name' => $apt->facility?->name,
            'date' => $when,
            'start_time' => $time,
        ], "doctor_new_booking:{$apt->id}");
    }

    /**
     * Phase 14: Notify the doctor when an appointment of theirs is cancelled by the patient.
     */
    public function notifyDoctorAppointmentCancelled(Appointment $appointment): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility', 'user']);
        $doctor = $apt->doctor;
        if (!$doctor || !$doctor->user_id) return;
        $when = $apt->appointment_date->format('M j, Y');
        $time = substr($apt->start_time, 0, 5);
        $reason = $apt->cancellation_reason ? " Reason: {$apt->cancellation_reason}." : '';
        $this->push($doctor->user_id, [
            'category' => 'doctor_appointment_cancelled',
            'title' => 'Appointment cancelled.',
            'message' => "{$apt->user?->name} — {$when} at {$time}.{$reason}",
            'priority' => 'important',
            'appointment_id' => $apt->id,
            'patient_name' => $apt->user?->name,
            'doctor_id' => $doctor->id,
            'facility_id' => $apt->facility?->id,
            'facility_name' => $apt->facility?->name,
            'date' => $when,
            'start_time' => $time,
        ], "doctor_appointment_cancelled:{$apt->id}");
    }

    // ─── Phase 14: Session-Change Notifications ──────────────────────────────────

    /**
     * Phase 14: Notify the doctor when one of their sessions is updated.
     */
    public function notifyDoctorSessionChanged(ClinicSession $session, array $changes, array $oldValues): void
    {
        $doctor = $session->doctor;
        if (!$doctor || !$doctor->user_id) return;
        $facility = $session->facility;
        $facilityName = $facility?->name ?? 'the clinic';
        $when = $session->session_date->format('M j, Y');
        $parts = [];
        if (isset($changes['facility_id'])) { $parts[] = 'location changed to ' . $facilityName; }
        if (isset($changes['start_time'])) { $parts[] = 'start time now ' . substr($session->start_time, 0, 5); }
        if (isset($changes['session_date'])) { $parts[] = 'date updated to ' . $when; }
        $this->push($doctor->user_id, [
            'category' => 'doctor_session_changed',
            'title' => 'Your clinic session was updated.',
            'message' => "{$facilityName} on {$when}. " . implode('; ', $parts) . ".",
            'priority' => 'important',
            'clinic_session_id' => $session->id,
            'facility_id' => $session->facility_id,
            'facility_name' => $facilityName,
            'date' => $when,
            'changes' => array_keys($changes),
        ], "doctor_session_changed:{$session->id}");
    }

    /**
     * Phase 14: Notify patients with active bookings when a clinic session changes.
     * Preserves appointment historical context; does not overwrite records.
     */
    public function notifyPatientsSessionChanged(ClinicSession $session, array $changes, array $oldValues): void
    {
        $doctor = $session->doctor;
        $facility = $session->facility;
        $when = $session->session_date->format('M j, Y');
        $parts = [];
        if (isset($changes['facility_id'])) {
            $oldFac = \App\Models\Facility::find($oldValues['facility_id'] ?? null);
            $parts[] = 'Previous location: ' . ($oldFac?->name ?? 'previous clinic');
            $parts[] = 'Updated location: ' . ($facility?->name ?? 'new clinic');
        }
        if (isset($changes['session_date'])) { $parts[] = 'Date updated to ' . $when; }
        if (isset($changes['start_time'])) { $parts[] = 'Start time now ' . substr($session->start_time, 0, 5); }
        $changeSummary = implode('. ', $parts);
        $affectedAppointments = $session->appointments()->whereIn('status', ['pending', 'confirmed'])->get();
        foreach ($affectedAppointments as $appt) {
            $this->push($appt->user_id, [
                'category' => 'appointment_session_changed',
                'title' => 'Your clinic appointment has been updated.',
                'message' => "Dr. {$doctor?->display_name} at {$facility?->name}. {$changeSummary}. Please review your appointment.",
                'priority' => 'important',
                'appointment_id' => $appt->id,
                'doctor_id' => $doctor?->id,
                'doctor_slug' => $doctor?->slug,
                'clinic_session_id' => $session->id,
                'facility_id' => $session->facility_id,
                'facility_name' => $facility?->name,
                'date' => $when,
                'start_time' => substr($session->start_time, 0, 5),
                'changes' => array_keys($changes),
                'old_values' => $oldValues,
            ], "session_changed:{$session->id}:{$appt->user_id}");
        }
    }

    // ─── Phase 14: Facility Admin Notifications ──────────────────────────────────

    /**
     * Phase 14: Notify facility admins when a new appointment is booked at their facility.
     */
    public function notifyFacilityNewBooking(Appointment $appointment): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility', 'user']);
        $facility = $apt->facility;
        if (!$facility) return;
        $when = $apt->appointment_date->format('M j, Y');
        $time = substr($apt->start_time, 0, 5);
        $adminIds = \App\Models\User::whereHas('facilities', function ($q) use ($facility) {
            $q->where('facilities.id', $facility->id);
        })->pluck('users.id')->all();
        foreach ($adminIds as $adminId) {
            $this->push($adminId, [
                'category' => 'facility_new_booking',
                'title' => 'New appointment at ' . $facility->name,
                'message' => "Dr. {$apt->doctor?->display_name} — {$when} at {$time}.",
                'priority' => 'informational',
                'appointment_id' => $apt->id,
                'doctor_id' => $apt->doctor?->id,
                'doctor_name' => $apt->doctor?->display_name,
                'facility_id' => $facility->id,
                'facility_name' => $facility->name,
                'date' => $when,
                'start_time' => $time,
            ], "facility_new_booking:{$apt->id}:{$adminId}");
        }
    }

    // ─── Phase 14: Appointment Reminders ───────────────────────────────────────

    /**
     * Phase 14: Send an appointment reminder to a patient.
     * The dedup key is per-interval so 24h and 2h reminders can both fire.
     */
    public function notifyPatientAppointmentReminder(Appointment $appointment, string $intervalLabel): void
    {
        $apt = $appointment->loadMissing(['doctor', 'facility']);
        $when = $apt->appointment_date->format('M j, Y');
        $time = substr($apt->start_time, 0, 5);
        $this->push($apt->user_id, [
            'category' => 'appointment_reminder',
            'title' => 'Appointment reminder',
            'message' => "You have an appointment with Dr. {$apt->doctor?->display_name} at {$apt->facility?->name} on {$when} at {$time}.",
            'priority' => 'important',
            'appointment_id' => $apt->id,
            'doctor_id' => $apt->doctor?->id,
            'doctor_slug' => $apt->doctor?->slug,
            'clinic_session_id' => $apt->clinic_session_id,
            'facility_id' => $apt->facility?->id,
            'facility_name' => $apt->facility?->name,
            'date' => $when,
            'start_time' => $time,
            'reminder_interval' => $intervalLabel,
        ], "reminder:{$intervalLabel}:{$apt->id}");
    }

    protected function push(int $userId, array $payload, ?string $dedupKey = null): void
    {
        if ($dedupKey) {
            $payload['_dedup'] = $dedupKey;
            $exists = DB::table('notifications')
                ->where('notifiable_type', \App\Models\User::class)
                ->where('notifiable_id', $userId)
                ->where('data', 'like', '%"_dedup":"' . $dedupKey . '"%')
                ->exists();
            if ($exists) return;
        }
        SendDatabaseNotification::dispatch($userId, $payload);
    }
}

    