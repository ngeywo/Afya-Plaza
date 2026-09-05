<?php

namespace App\Listeners;

use App\Events\ClinicSessionChanged;
use App\Services\NotificationService;

class NotifySessionChangedPatients
{
    public function __construct(
        private NotificationService $notifications
    ) {}

    public function handle(ClinicSessionChanged $event): void
    {
        $this->notifications->notifyPatientsSessionChanged(
            $event->session,
            $event->changes,
            $event->oldValues
        );

        $this->notifications->notifyDoctorSessionChanged(
            $event->session,
            $event->changes,
            $event->oldValues
        );
    }
}
