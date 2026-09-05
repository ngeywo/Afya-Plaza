<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'notifications:remind
                            {--interval=24h}';

    protected $description = 'Send appointment reminders to patients';

    public function __construct(
        private NotificationService $notifications
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $interval = $this->option('interval');
        if ($interval === '24h') {
            $count = $this->send24HourReminders();
        } elseif ($interval === '2h') {
            $count = $this->send2HourReminders();
        } else {
            $this->error("Unknown interval: {$interval}. Use 24h or 2h.");
            return 1;
        }
        $this->info("Sent {$count} reminder(s).");
        return 0;
    }

    private function send24HourReminders(): int
    {
        $tomorrow = Carbon::tomorrow()->endOfDay();
        $today = Carbon::today();
        return $this->remindAppointments($today, $tomorrow, '24h');
    }

    private function send2HourReminders(): int
    {
        $now = Carbon::now();
        $windowEnd = $now->copy()->addHours(3)->endOfMinute();
        return $this->remindAppointments($now->copy()->addMinutes(60), $windowEnd, '2h');
    }

    private function remindAppointments(Carbon $from, Carbon $to, string $interval): int
    {
        $count = 0;
        Appointment::with(['doctor', 'facility'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->chunkById(100, function ($apts) use (&$count, $interval) {
                foreach ($apts as $apt) {
                    $this->notifications->notifyPatientAppointmentReminder($apt, $interval);
                    $count++;
                }
            });
        return $count;
    }
}
