<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MarketplaceNotification extends Notification
{
    use Queueable;

    public function __construct(public array $payload) {}

    /**
     * Delivery channels. Database only for Phase 10 (mail/queue extension
     * is a Phase 11 candidate). The mail driver is set to "log" in this
     * environment, so adding mail() here would write to laravel.log.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Payload stored in the notifications.data JSON column.
     */
    public function toDatabase($notifiable): array
    {
        return $this->payload;
    }
}
