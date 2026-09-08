<?php

namespace App\Services;

use App\Models\ClinicSession;
use App\Support\TimeWindows;
use Carbon\Carbon;

/**
 * Phase 23 (Cross-Facility Scheduling): buffer-aware conflict detection for a
 * doctor's clinic sessions across every facility they practice at.
 *
 * A doctor is one canonical person — they cannot hold clinics at two facilities
 * at the same time. A configurable travel/transition buffer (default 30 min)
 * extends the protected window so end-of-clinic + commute + sign-in doesn't
 * collide with the next facility's clinic.
 */
class ScheduleConflictService
{
    private int $bufferMinutes;

    public function __construct()
    {
        $this->bufferMinutes = (int) config('services.scheduling.transition_buffer_minutes', 30);
    }

    /**
     * Find a session that collides with the proposed window for this doctor on
     * this date, honoring the transition buffer. Pass $excludeSessionId when
     * updating an existing session so it never conflicts with itself.
     */
    public function findConflict(
        int $doctorId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeSessionId = null,
        array $statuses = ['pending', 'confirmed'],
    ): ?ClinicSession {
        return ClinicSession::with('facility')
            ->where('doctor_id', $doctorId)
            ->whereDate('session_date', $date)
            ->whereIn('status', $statuses)
            ->when($excludeSessionId, fn ($q) => $q->where('id', '!=', $excludeSessionId))
            ->get()
            ->first(fn (ClinicSession $existing) => $this->overlaps($existing, $startTime, $endTime));
    }

    /**
     * True when the proposed [start, end) window trespasses the existing
     * [start, end) window after each window is padded by the transition buffer.
     */
    public function overlaps(ClinicSession $existing, string $startTime, string $endTime): bool
    {
        return TimeWindows::overlaps(
            substr($existing->start_time, 0, 5),
            substr($existing->end_time, 0, 5),
            Carbon::parse($startTime)->format('H:i'),
            Carbon::parse($endTime)->format('H:i'),
            $this->bufferMinutes,
        );
    }

    public function bufferMinutes(): int
    {
        return $this->bufferMinutes;
    }

    /**
     * Human-friendly conflict explanation for API responses.
     */
    public function describe(ClinicSession $collision, string $startTime, string $endTime): array
    {
        return [
            'facility' => $collision->facility?->name,
            'facility_id' => $collision->facility_id,
            'session_id' => $collision->id,
            'time' => substr($collision->start_time, 0, 5).' - '.substr($collision->end_time, 0, 5),
            'proposed' => substr($startTime, 0, 5).' - '.substr($endTime, 0, 5),
            'buffer_minutes' => $this->bufferMinutes,
            'code' => 'DOCTOR_COLLISION',
        ];
    }
}
