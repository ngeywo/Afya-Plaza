<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\MarketplaceNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 10: Persistent (database) notification.
 * Runs on the database queue so a doctor with many followers does not
 * synchronously create thousands of inserts during a clinic update.
 */
class SendDatabaseNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public int $userId,
        public array $payload
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            Log::warning("SendDatabaseNotification: user {$this->userId} not found, skipping.");

            return;
        }

        // Laravel's built-in Notifiable trait persists into the polymorphic
        // notifications table. We use that rather than writing directly.
        $user->notify(new MarketplaceNotification($this->payload));
    }
}
