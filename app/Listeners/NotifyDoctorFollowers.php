<?php

namespace App\Listeners;

use App\Events\ClinicSessionConfirmed;
use App\Services\NotificationService;

class NotifyDoctorFollowers
{
    public function __construct(
        private NotificationService $notifications
    ) {}

    /**
     * When a clinic session is confirmed, notify all followers of the doctor.
     * This listener is registered in AppServiceProvider.
     */
    public function handle(ClinicSessionConfirmed $event): void
    {
        // Only notify if the session was in a "pending" state before.
        // Prevents notifications on every save.
        if (! $event->wasPendingBefore) {
            return;
        }

        $this->notifications->notifyFollowersClinicConfirmed($event->session);
    }
}
