<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Phase 17: Appointment reminders ──────────────────────────────────────────
// Reuses the existing notifications:remind command (Phase 14) on a schedule.
// 24h reminder each morning; 2h reminder every hour near clinic time.
// The NotificationService dedup key ("reminder:{interval}:{appointment}")
// guarantees no patient receives the same reminder twice.
Schedule::command('notifications:remind --interval=24h')->dailyAt('08:00');
Schedule::command('notifications:remind --interval=2h')->hourlyAt(45);
