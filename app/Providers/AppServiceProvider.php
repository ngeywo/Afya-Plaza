<?php

namespace App\Providers;

use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Listeners\NotifyAppointmentPatient;
use App\Services\CodeSenderInterface;
use App\Services\LoggingCodeSender;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * ClinicSession* and session notifications are auto-discovered from
     * app/Listeners (handle() convention). These two Appointment events need
     * explicit method binding (onBooked/onCancelled) because a single listener
     * class handles multiple events.
     */
    protected $listen = [
        AppointmentBooked::class => [
            [NotifyAppointmentPatient::class, 'onBooked'],
        ],
        AppointmentCancelled::class => [
            [NotifyAppointmentPatient::class, 'onCancelled'],
        ],
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(CodeSenderInterface::class, LoggingCodeSender::class);
    }

    public function boot(): void
    {
        //
    }
}
