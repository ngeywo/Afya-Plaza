<?php

namespace App\Listeners;

use App\Events\ClinicSessionCancelled;
use App\Services\NotificationService;

class NotifySessionCancelledPatients
{
    public function __construct(
        private NotificationService $notifications
    ) {}

    /**
     * When a clinic session is cancelled, notify all affected patients
     * (those with active bookings) and followers of the doctor.
     */
    public function handle(ClinicSessionCancelled $event): void
    {
        $this->notifications->notifyPatientsSessionCancelled(
            $event->session,
            $event->reason
        );
    }
}
