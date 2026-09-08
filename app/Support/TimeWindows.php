<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Shared time-window math used by the scheduling conflict checks (session
 * stores, weekly schedules, recurring clinics). Zero-padded "H:i" strings.
 */
class TimeWindows
{
    public static function overlaps(string $existingStart, string $existingEnd, string $proposedStart, string $proposedEnd, int $bufferMinutes = 0): bool
    {
        $existingStart = Carbon::parse($existingStart);
        $existingEnd = Carbon::parse($existingEnd);
        $proposedStart = Carbon::parse($proposedStart);
        $proposedEnd = Carbon::parse($proposedEnd);

        $guardedExistingStart = $existingStart->copy()->subMinutes($bufferMinutes);
        $guardedExistingEnd = $existingEnd->copy()->addMinutes($bufferMinutes);

        return $proposedStart->lt($guardedExistingEnd) && $proposedEnd->gt($guardedExistingStart);
    }
}
