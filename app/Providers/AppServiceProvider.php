<?php

namespace App\Providers;

use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\ClinicSessionCancelled;
use App\Events\ClinicSessionChanged;
use App\Events\ClinicSessionConfirmed;
use App\Listeners\NotifyAppointmentPatient;
use App\Listeners\NotifyDoctorFollowers;
use App\Listeners\NotifySessionCancelledPatients;
use App\Listeners\NotifySessionChangedPatients;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     */
    protected $listen = [
        ClinicSessionConfirmed::class => [
            NotifyDoctorFollowers::class,
        ],
        ClinicSessionChanged::class => [
            NotifySessionChangedPatients::class,
        ],
        ClinicSessionCancelled::class => [
            NotifySessionCancelledPatients::class,
        ],
        AppointmentBooked::class => [
            [NotifyAppointmentPatient::class, 'onBooked'],
        ],
        AppointmentCancelled::class => [
            [NotifyAppointmentPatient::class, 'onCancelled'],
        ],
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
