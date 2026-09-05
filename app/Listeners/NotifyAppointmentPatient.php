<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Services\NotificationService;

class NotifyAppointmentPatient
{
    public function __construct(
        private NotificationService $notifications
    ) {}

    public function onBooked(AppointmentBooked $event): void
    {
        $this->notifications->notifyPatientAppointmentBooked($event->appointment);
        $this->notifications->notifyDoctorNewBooking($event->appointment);
        $this->notifications->notifyFacilityNewBooking($event->appointment);
    }

    public function onCancelled(AppointmentCancelled $event): void
    {
        $this->notifications->notifyPatientAppointmentCancelled($event->appointment);
        $this->notifications->notifyDoctorAppointmentCancelled($event->appointment);
    }
}
