<?php

namespace App\Services;

use App\Enums\DoctorRelationshipStatus;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorFacility;
use App\Models\DoctorFacilitySchedule;
use App\Models\DoctorFacilityService;
use App\Models\Facility;
use App\Models\User;
use App\Support\TimeWindows;
use Illuminate\Support\Facades\DB;

/**
 * Phase 23 (Unified Doctor Identity / Facility Management): the authoritative
 * lifecycle for doctor-facility relationships.
 *
 * One canonical doctor per professional registration/licence (identity), licensed
 * under a facility ONLY through an ACTIVE relationship (authorization). Every
 * transition records who/what/when/target/result in an audit trail. Duplicate
 * active relationships are impossible (unique doctor_id+facility_id).
 */
class DoctorRelationshipService
{
    public function __construct(private EntitlementService $entitlements) {}

    /**
     * Facility invites a doctor to practice at their facility.
     * Creates an INVITED relationship (facility already agrees).
     */
    public function invite(Doctor $doctor, Facility $facility, User $facilityAdmin): DoctorFacility
    {
        $this->guardFacilityActor($facility, $facilityAdmin);
        $this->entitlements->assertCanAddDoctor($facility);
        $existing = $this->findRelationship($doctor, $facility);
        if ($existing) {
            throw new \RuntimeException('A relationship with this doctor already exists ('.$existing->statusLabel().').');
        }

        return DB::transaction(function () use ($doctor, $facility, $facilityAdmin) {
            $relationship = DoctorFacility::create([
                'doctor_id' => $doctor->id,
                'facility_id' => $facility->id,
                'status' => DoctorRelationshipStatus::INVITED,
                'invited_by' => $facilityAdmin->id,
                'is_active' => false,
                'accepts_appointments' => false,
            ]);

            AuditLog::record(
                $facilityAdmin->id,
                'doctor_facility.invited',
                DoctorFacility::class,
                $relationship->id,
                "{$doctor->display_name} @ {$facility->name}",
                null,
                ['status' => DoctorRelationshipStatus::INVITED->value, 'invited_by' => $facilityAdmin->id],
            );

            return $relationship;
        });
    }

    /**
     * Doctor accepts the facility invitation → ACTIVE.
     */
    public function acceptInvite(DoctorFacility $relationship, User $doctorUser, ?string $note = null): DoctorFacility
    {
        $this->guardDoctorOwner($relationship, $doctorUser);
        $this->transition($relationship, DoctorRelationshipStatus::ACTIVE, $doctorUser, 'doctor_facility.accepted_invite', $note);

        return $relationship->fresh();
    }

    /**
     * Doctor requests to join a facility → PENDING (facility approves later).
     */
    public function requestToJoin(Doctor $doctor, Facility $facility, User $doctorUser): DoctorFacility
    {
        $this->guardDoctorIdentity($doctor, $doctorUser);
        $this->entitlements->assertCanAddDoctor($facility);
        $existing = $this->findRelationship($doctor, $facility);
        if ($existing) {
            throw new \RuntimeException('A relationship request with this facility already exists ('.$existing->statusLabel().').');
        }

        return DB::transaction(function () use ($doctor, $facility, $doctorUser) {
            $relationship = DoctorFacility::create([
                'doctor_id' => $doctor->id,
                'facility_id' => $facility->id,
                'status' => DoctorRelationshipStatus::PENDING,
                'requested_by' => $doctorUser->id,
                'is_active' => false,
                'accepts_appointments' => false,
            ]);

            AuditLog::record(
                $doctorUser->id,
                'doctor_facility.join_requested',
                DoctorFacility::class,
                $relationship->id,
                "{$doctor->display_name} → {$facility->name}",
                null,
                ['status' => DoctorRelationshipStatus::PENDING->value, 'requested_by' => $doctorUser->id],
            );

            return $relationship;
        });
    }

    /**
     * Facility approves a doctor's PENDING join request → ACTIVE.
     */
    public function approveRequest(DoctorFacility $relationship, User $facilityAdmin, ?string $note = null): DoctorFacility
    {
        $this->guardFacilityActor($relationship->facility, $facilityAdmin);
        $this->transition($relationship, DoctorRelationshipStatus::ACTIVE, $facilityAdmin, 'doctor_facility.request_approved', $note);

        return $relationship->fresh();
    }

    /**
     * Either counterpart declines/refuses the INVITED or PENDING relationship.
     */
    public function decline(DoctorFacility $relationship, User $actor, string $reason): DoctorFacility
    {
        $this->guardCounterpart($relationship, $actor);
        if (! $reason || trim($reason) === '') {
            throw new \InvalidArgumentException('A decline reason is required.');
        }

        return DB::transaction(function () use ($relationship, $actor, $reason) {
            $this->requireTransition($relationship, DoctorRelationshipStatus::DECLINED);

            $relationship->update([
                'status' => DoctorRelationshipStatus::DECLINED,
                'declined_at' => now(),
                'decline_reason' => $reason,
                'is_active' => false,
            ]);

            AuditLog::record(
                $actor->id,
                'doctor_facility.declined',
                DoctorFacility::class,
                $relationship->id,
                $relationship->doctor?->display_name.' @ '.$relationship->facility?->name,
                ['status' => $relationship->getOriginal('status')],
                ['status' => DoctorRelationshipStatus::DECLINED->value],
                $reason,
            );

            return $relationship;
        });
    }

    /**
     * Suspend the relationship (platform, facility, or doctor initiated).
     */
    public function suspend(DoctorFacility $relationship, User $actor, string $reason): DoctorFacility
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: false);

        return DB::transaction(function () use ($relationship, $actor, $reason) {
            $this->requireTransition($relationship, DoctorRelationshipStatus::SUSPENDED);

            $relationship->update([
                'status' => DoctorRelationshipStatus::SUSPENDED,
                'is_active' => false,
                'notes_governance' => $reason,
            ]);

            AuditLog::record(
                $actor->id,
                'doctor_facility.suspended',
                DoctorFacility::class,
                $relationship->id,
                $relationship->doctor?->display_name.' @ '.$relationship->facility?->name,
                ['status' => $relationship->getOriginal('status')],
                ['status' => DoctorRelationshipStatus::SUSPENDED->value],
                $reason,
            );

            return $relationship;
        });
    }

    /**
     * Reactivate a SUSPENDED or INACTIVE relationship → ACTIVE.
     */
    public function reactivate(DoctorFacility $relationship, User $actor, ?string $reason = null): DoctorFacility
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: false);

        // An INACTIVE (ended) relationship freed its seat; reactivating re-claims
        // one, so re-check facility doctor capacity. SUSPENDED never freed the seat.
        if ($relationship->status === DoctorRelationshipStatus::INACTIVE) {
            $this->entitlements->assertCanAddDoctor($relationship->facility);
        }

        return DB::transaction(function () use ($relationship, $actor, $reason) {
            $this->requireTransition($relationship, DoctorRelationshipStatus::ACTIVE);

            $relationship->update([
                'status' => DoctorRelationshipStatus::ACTIVE,
                'is_active' => true,
                'approved_at' => $relationship->approved_at ?? now(),
                'approved_by' => $relationship->approved_by ?? $actor->id,
                'started_at' => $relationship->started_at ?? now(),
                'ended_at' => null,
                'ended_at_governance' => null,
                'ended_by' => null,
                'notes_governance' => $reason ? trim(($relationship->notes_governance ? $relationship->notes_governance."\n" : '').$reason) : $relationship->notes_governance,
            ]);

            $this->ensureDefaultServices($relationship);

            AuditLog::record(
                $actor->id,
                'doctor_facility.reactivated',
                DoctorFacility::class,
                $relationship->id,
                $relationship->doctor?->display_name.' @ '.$relationship->facility?->name,
                ['status' => $relationship->getOriginal('status')],
                ['status' => DoctorRelationshipStatus::ACTIVE->value],
                $reason,
            );

            return $relationship;
        });
    }

    /**
     * End (terminate) the relationship — spec alias for INACTIVE.
     */
    public function end(DoctorFacility $relationship, User $actor, string $reason): DoctorFacility
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: false);

        return DB::transaction(function () use ($relationship, $actor, $reason) {
            $this->requireTransition($relationship, DoctorRelationshipStatus::INACTIVE);

            $relationship->update([
                'status' => DoctorRelationshipStatus::INACTIVE,
                'is_active' => false,
                'ended_at' => now(),
                'ended_at_governance' => now(),
                'ended_by' => $actor->id,
                'notes_governance' => $reason,
            ]);

            // Existing sessions are historical truth — never deleted, never rewritten.
            AuditLog::record(
                $actor->id,
                'doctor_facility.ended',
                DoctorFacility::class,
                $relationship->id,
                $relationship->doctor?->display_name.' @ '.$relationship->facility?->name,
                ['status' => $relationship->getOriginal('status')],
                ['status' => DoctorRelationshipStatus::INACTIVE->value],
                $reason,
            );

            return $relationship;
        });
    }

    /**
     * Update relationship settings (fee, appointments). Both doctor and facility
     * admin (scoped to their facility) are allowed.
     */
    public function updateSettings(DoctorFacility $relationship, User $actor, array $data): DoctorFacility
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: true);

        $allowed = [];
        if (array_key_exists('consultation_fee', $data)) {
            $fee = $data['consultation_fee'];
            if (! is_numeric($fee) || (float) $fee < 0) {
                throw new \InvalidArgumentException('Consultation fee must be a non-negative amount.');
            }
            $allowed['consultation_fee'] = $fee;
        }
        if (array_key_exists('accepts_appointments', $data)) {
            $allowed['accepts_appointments'] = (bool) $data['accepts_appointments'];
        }
        if (array_key_exists('notes', $data)) {
            $allowed['notes'] = (string) $data['notes'];
        }

        if (empty($allowed)) {
            throw new \InvalidArgumentException('Nothing to update.');
        }

        return DB::transaction(function () use ($relationship, $actor, $allowed) {
            $before = ['consultation_fee' => $relationship->consultation_fee, 'accepts_appointments' => $relationship->accepts_appointments];
            $relationship->update($allowed);

            AuditLog::record(
                $actor->id,
                'doctor_facility.settings_updated',
                DoctorFacility::class,
                $relationship->id,
                $relationship->doctor?->display_name.' @ '.$relationship->facility?->name,
                $before,
                $allowed,
            );

            return $relationship;
        });
    }

    /**
     * Ensure every ACTIVE relationship has at least a default Consultation service.
     */
    public function ensureDefaultServices(DoctorFacility $relationship): void
    {
        $hasConsultation = $relationship->services()
            ->where('service_name', DoctorFacilityService::CONSULTATION)
            ->exists();

        if (! $hasConsultation) {
            DoctorFacilityService::create([
                'doctor_facility_id' => $relationship->id,
                'service_name' => DoctorFacilityService::CONSULTATION,
                'price' => $relationship->consultation_fee ?? $relationship->doctor?->consultation_fee ?? 0,
                'duration_minutes' => 30,
                'is_active' => true,
            ]);
        }
    }

    public function findRelationship(Doctor $doctor, Facility $facility): ?DoctorFacility
    {
        return DoctorFacility::where('doctor_id', $doctor->id)
            ->where('facility_id', $facility->id)
            ->first();
    }

    private function transition(DoctorFacility $relationship, DoctorRelationshipStatus $next, User $actor, string $action, ?string $note): void
    {
        $this->requireTransition($relationship, $next);

        $relationship->update([
            'status' => $next,
            'is_active' => $next === DoctorRelationshipStatus::ACTIVE,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'started_at' => $relationship->started_at ?? ($next === DoctorRelationshipStatus::ACTIVE ? now() : null),
            'ended_at' => null,
        ]);

        if ($next === DoctorRelationshipStatus::ACTIVE && $note) {
            $relationship->update(['notes_governance' => $note]);
        }

        $this->ensureDefaultServices($relationship);

        AuditLog::record(
            $actor->id,
            $action,
            DoctorFacility::class,
            $relationship->id,
            $relationship->doctor?->display_name.' @ '.$relationship->facility?->name,
            ['status' => $relationship->getOriginal('status')],
            ['status' => $next->value],
            $note,
        );
    }

    private function requireTransition(DoctorFacility $relationship, DoctorRelationshipStatus $next): void
    {
        if (! $relationship->status || ! $relationship->status->canTransitionTo($next)) {
            throw new \RuntimeException("Cannot change relationship from [{$relationship->statusLabel()}] to [{$next->label()}].");
        }
    }

    private function guardFacilityActor(Facility $facility, User $actor): void
    {
        if ($actor->isSuperAdmin() || $actor->hasRole('platform-admin')) {
            return;
        }
        if ($actor->hasAnyRole(['facility-admin', 'facility-staff'])
            && $actor->facilities()->where('facilities.id', $facility->id)->exists()) {
            return;
        }

        throw new \RuntimeException('You are not authorized to manage this facility.');
    }

    private function guardDoctorIdentity(Doctor $doctor, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }
        if (! $actor->doctor || $actor->doctor->id !== $doctor->id) {
            throw new \RuntimeException('You are not authorized to act for this doctor profile.');
        }
    }

    private function guardDoctorOwner(DoctorFacility $relationship, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }
        if (! $actor->doctor || $actor->doctor->id !== $relationship->doctor_id) {
            throw new \RuntimeException('You are not authorized to manage this relationship.');
        }
    }

    private function guardCounterpart(DoctorFacility $relationship, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }
        $isDoctor = $actor->doctor && $actor->doctor->id === $relationship->doctor_id;
        $isFacility = $actor->hasAnyRole(['facility-admin', 'facility-staff'])
            && $actor->facilities()->where('facilities.id', $relationship->facility_id)->exists();

        if (! $isDoctor && ! $isFacility) {
            throw new \RuntimeException('You are not authorized to manage this relationship.');
        }
    }

    private function guardAnyAuthorizedParty(DoctorFacility $relationship, User $actor, bool $includePending): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }
        $isDoctor = $actor->doctor && $actor->doctor->id === $relationship->doctor_id;
        $isFacility = $actor->hasAnyRole(['facility-admin', 'facility-staff'])
            && $actor->facilities()->where('facilities.id', $relationship->facility_id)->exists();

        if (! $isDoctor && ! $isFacility) {
            throw new \RuntimeException('You are not authorized to manage this relationship.');
        }
    }

    // ─── Phase 23: Facility management (schedules & services) ──────────────────

    /**
     * Create a weekly schedule entry for a doctor at a facility. Checks the
     * transition buffer against the doctor's schedules at OTHER facilities.
     */
    public function storeSchedule(DoctorFacility $relationship, User $actor, array $data): DoctorFacilitySchedule
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: true);

        $day = (int) $data['day_of_week'];
        $start = substr((string) $data['start_time'], 0, 5);
        $end = substr((string) $data['end_time'], 0, 5);

        $this->assertValidTimeWindow($start, $end);
        $this->assertWeeklyBufferClear($relationship, day: $day, start: $start, end: $end, excludeScheduleId: null);

        return DB::transaction(function () use ($relationship, $actor, $day, $start, $end, $data) {
            $schedule = $relationship->schedules()->create([
                'day_of_week' => $day,
                'start_time' => $start.':00',
                'end_time' => $end.':00',
                'slot_duration_minutes' => $data['slot_duration_minutes'] ?? 30,
                'max_appointments' => $data['max_appointments'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'effective_from' => $data['effective_from'] ?? null,
                'effective_until' => $data['effective_until'] ?? null,
            ]);

            AuditLog::record(
                $actor->id,
                'doctor_facility.schedule_created',
                DoctorFacilitySchedule::class,
                $schedule->id,
                "{$relationship->doctor?->display_name} @ {$relationship->facility?->name}",
                null,
                ['day_of_week' => $day, 'start_time' => $start.':00', 'end_time' => $end.':00'],
            );

            return $schedule;
        });
    }

    public function updateSchedule(DoctorFacility $relationship, DoctorFacilitySchedule $schedule, User $actor, array $data): DoctorFacilitySchedule
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: true);
        if ($schedule->doctor_facility_id !== $relationship->id) {
            throw new \RuntimeException('Schedule does not belong to this relationship.');
        }

        $day = (int) ($data['day_of_week'] ?? $schedule->day_of_week);
        $start = substr((string) ($data['start_time'] ?? $schedule->start_time), 0, 5);
        $end = substr((string) ($data['end_time'] ?? $schedule->end_time), 0, 5);
        $this->assertValidTimeWindow($start, $end);
        $this->assertWeeklyBufferClear($relationship, day: $day, start: $start, end: $end, excludeScheduleId: $schedule->id);

        $schedule->update([
            'day_of_week' => $day,
            'start_time' => $start.':00',
            'end_time' => $end.':00',
            'slot_duration_minutes' => $data['slot_duration_minutes'] ?? $schedule->slot_duration_minutes,
            'max_appointments' => array_key_exists('max_appointments', $data) ? $data['max_appointments'] : $schedule->max_appointments,
            'is_active' => $data['is_active'] ?? $schedule->is_active,
            'effective_from' => array_key_exists('effective_from', $data) ? $data['effective_from'] : $schedule->effective_from,
            'effective_until' => array_key_exists('effective_until', $data) ? $data['effective_until'] : $schedule->effective_until,
        ]);

        AuditLog::record(
            $actor->id,
            'doctor_facility.schedule_updated',
            DoctorFacilitySchedule::class,
            $schedule->id,
            "{$relationship->doctor?->display_name} @ {$relationship->facility?->name}",
            null,
            ['day_of_week' => $day, 'start_time' => $start.':00', 'end_time' => $end.':00', 'is_active' => $schedule->is_active],
        );

        return $schedule->fresh();
    }

    public function deleteSchedule(DoctorFacility $relationship, DoctorFacilitySchedule $schedule, User $actor): void
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: true);
        if ($schedule->doctor_facility_id !== $relationship->id) {
            throw new \RuntimeException('Schedule does not belong to this relationship.');
        }

        AuditLog::record(
            $actor->id,
            'doctor_facility.schedule_deleted',
            DoctorFacilitySchedule::class,
            $schedule->id,
            "{$relationship->doctor?->display_name} @ {$relationship->facility?->name}",
            null,
            null,
            'Schedule removed',
        );
        $schedule->delete();
    }

    public function addService(DoctorFacility $relationship, User $actor, array $data): DoctorFacilityService
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: true);

        $name = trim((string) $data['service_name']);
        if ($name === '') {
            throw new \InvalidArgumentException('Service name is required.');
        }
        if ((float) $data['price'] < 0) {
            throw new \InvalidArgumentException('Service price must be a non-negative amount.');
        }
        if ($relationship->services()->where('service_name', $name)->exists()) {
            throw new \RuntimeException("Service [{$name}] already exists for this facility.");
        }

        return DB::transaction(function () use ($relationship, $actor, $name, $data) {
            $service = DoctorFacilityService::create([
                'doctor_facility_id' => $relationship->id,
                'service_name' => $name,
                'price' => $data['price'],
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'is_active' => $data['is_active'] ?? true,
            ]);

            AuditLog::record(
                $actor->id,
                'doctor_facility.service_created',
                DoctorFacilityService::class,
                $service->id,
                "{$name} — {$relationship->doctor?->display_name} @ {$relationship->facility?->name}",
                null,
                ['service_name' => $name, 'price' => $data['price']],
            );

            return $service;
        });
    }

    public function updateService(DoctorFacility $relationship, DoctorFacilityService $service, User $actor, array $data): DoctorFacilityService
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: true);
        if ($service->doctor_facility_id !== $relationship->id) {
            throw new \RuntimeException('Service does not belong to this relationship.');
        }
        if (isset($data['price']) && (float) $data['price'] < 0) {
            throw new \InvalidArgumentException('Service price must be a non-negative amount.');
        }

        $service->update([
            'service_name' => $data['service_name'] ?? $service->service_name,
            'price' => $data['price'] ?? $service->price,
            'duration_minutes' => $data['duration_minutes'] ?? $service->duration_minutes,
            'is_active' => $data['is_active'] ?? $service->is_active,
        ]);

        AuditLog::record(
            $actor->id,
            'doctor_facility.service_updated',
            DoctorFacilityService::class,
            $service->id,
            $service->service_name,
            null,
            ['price' => $service->price, 'is_active' => $service->is_active],
        );

        return $service->fresh();
    }

    public function deleteService(DoctorFacility $relationship, DoctorFacilityService $service, User $actor): void
    {
        $this->guardAnyAuthorizedParty($relationship, $actor, includePending: true);
        if ($service->doctor_facility_id !== $relationship->id) {
            throw new \RuntimeException('Service does not belong to this relationship.');
        }
        if ($service->bookings()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            throw new \RuntimeException('This service has active bookings and cannot be removed.');
        }

        AuditLog::record(
            $actor->id,
            'doctor_facility.service_deleted',
            DoctorFacilityService::class,
            $service->id,
            $service->service_name,
            null,
            null,
            'Service removed',
        );
        $service->delete();
    }

    private function assertValidTimeWindow(string $start, string $end): void
    {
        if ($start >= $end) {
            throw new \InvalidArgumentException('Start time must be before end time.');
        }
    }

    /**
     * Enforce the transition buffer for weekly commitments: a doctor cannot book
     * two recurring clinics at different facilities on the same weekday if they
     * violate the travel/transition buffer.
     */
    private function assertWeeklyBufferClear(DoctorFacility $relationship, int $day, string $start, string $end, ?int $excludeScheduleId): void
    {
        $conflict = DoctorFacilitySchedule::query()
            ->whereHas('doctorFacility', fn ($q) => $q->where('doctor_id', $relationship->doctor_id))
            ->where('day_of_week', $day)
            ->where('is_active', true)
            ->where('id', '!=', $excludeScheduleId)
            ->with('doctorFacility')
            ->get()
            ->filter(fn (DoctorFacilitySchedule $match) => $match->doctor_facility_id !== $relationship->id)
            ->first(fn (DoctorFacilitySchedule $match) => $this->windowsOverlapWithinBuffer($match, $start, $end));

        if ($conflict) {
            $facility = $conflict->doctorFacility?->facility;
            throw new \RuntimeException(
                "Weekly schedule conflicts with another facility's clinic within the transition buffer. Facility: ".($facility?->name ?? 'Unknown').' ('.substr($conflict->start_time, 0, 5).' - '.substr($conflict->end_time, 0, 5).')'
            );
        }
    }

    private function windowsOverlapWithinBuffer(DoctorFacilitySchedule $existing, string $start, string $end): bool
    {
        $buffer = (int) config('services.scheduling.transition_buffer_minutes', 30);

        return TimeWindows::overlaps(
            substr($existing->start_time, 0, 5),
            substr($existing->end_time, 0, 5),
            $start,
            $end,
            $buffer,
        );
    }
}
