<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase 18: Send appointment reminder notifications.
 *
 * Usage:
 *   php artisan notifications:remind --interval=24h
 *   php artisan notifications:remind --interval=2h
 *
 * Scheduled in routes/console.php to run:
 *   - 24h reminder daily at 08:00
 *   - 2h reminder hourly at :45
 */
class SendNotificationReminders extends Command
{
    protected $signature = 'notifications:remind {--interval=24h : Reminder interval (24h or 2h)}';

    protected $description = 'Send appointment reminder notifications to patients';

    public function handle(NotificationService $notifications): int
    {
        $interval = $this->option('interval');
        if (! in_array($interval, ['24h', '2h'])) {
            $this->error('Invalid interval. Use --interval=24h or --interval=2h');

            return Command::FAILURE;
        }

        $now = now();
        $count = 0;

        // Get upcoming active appointments
        $appointments = Appointment::with(['doctor', 'facility'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('appointment_date', '>=', $now->toDateString())
            ->get();

        foreach ($appointments as $appointment) {
            $appointmentDateTime = Carbon::parse(
                $appointment->appointment_date->format('Y-m-d').' '.$appointment->start_time
            );

            $minutesUntil = $now->diffInMinutes($appointmentDateTime);
            $hoursUntil = $minutesUntil / 60;

            $shouldSend = false;

            if ($interval === '24h') {
                // 24-hour reminder (23-25 hours away)
                $shouldSend = $hoursUntil >= 23 && $hoursUntil <= 25;
            } elseif ($interval === '2h') {
                // 2-hour reminder (1.5-2.5 hours away)
                $shouldSend = $hoursUntil >= 1.5 && $hoursUntil <= 2.5;
            }

            if ($shouldSend) {
                try {
                    $notifications->notifyPatientAppointmentReminder($appointment, $interval);
                    $count++;
                } catch (\Exception $e) {
                    Log::error("SendNotificationReminders: failed for appointment {$appointment->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Sent {$count} {$interval} reminders.");
        Log::info("SendNotificationReminders: sent {$count} {$interval} reminders.");

        return Command::SUCCESS;
    }
}
