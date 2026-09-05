<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PhScheduled appointment reminder job.
 * This job is dispatched by the Laravel scheduler to send reminders
 * to patients before their upcoming appointments.
 * 
 * Two intervals are supported:
 * - 24 hours before: "Reminder: Your appointment is tomorrow"
 * - 2 hours before: "Reminder: Your appointment is in 2 hours"
 */
class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public int $appointmentId,
        public string $intervalLabel  // '24h' or '2h'
    ) {}

    public function handle(NotificationService $notifications): void
    {
        $appointment = Appointment::with(['doctor', 'facility'])
            ->find($this->appointmentId);

        if (!$appointment) {
            Log::warning("SendAppointmentReminder: appointment {$this->appointmentId} not found, skipping.");
            return;
        }

        // Only send reminders for active upcoming appointments
        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            Log::info("SendAppointmentReminder: appointment {$this->appointmentId} status is {$appointment->status}, skipping.");
            return;
        }

        // Only send if the appointment is still in the future
        $appointmentDateTime = \Carbon\Carbon::parse(
            $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->start_time
        );
        
        if ($appointmentDateTime->isPast()) {
            Log::info("SendAppointmentReminder: appointment {$this->appointmentId} is in the past, skipping.");
            return;
        }

        try {
            $notifications->notifyPatientAppointmentReminder($appointment, $this->intervalLabel);
            Log::info("SendAppointmentReminder: sent {$this->intervalLabel} reminder for appointment {$this->appointmentId}");
        } catch (\Exception $e) {
            Log::error("SendAppointmentReminder: failed to send {$this->intervalLabel} reminder for appointment {$this->appointmentId}: {$e->getMessage()}");
            throw $e;
        }
    }
}