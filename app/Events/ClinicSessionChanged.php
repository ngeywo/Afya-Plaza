<?php

namespace App\Events;

use App\Models\ClinicSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClinicSessionChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ClinicSession $session,
        public array $changes,
        public array $oldValues,
    ) {}
}
