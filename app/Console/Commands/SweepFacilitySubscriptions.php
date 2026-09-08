<?php

namespace App\Console\Commands;

use App\Services\FacilitySubscriptionService;
use Illuminate\Console\Command;

class SweepFacilitySubscriptions extends Command
{
    protected $signature = 'afya:subscriptions-sweep';

    protected $description = 'Advance facility subscription lifecycles (trial expiry, renewals, grace, suspension, cancellation expiry).';

    public function handle(FacilitySubscriptionService $service): int
    {
        $affected = $service->sweep();

        $this->info('Processed '.count($affected).' subscription lifecycle event(s).');

        foreach ($affected as $row) {
            $this->line("  #{$row['subscription_id']}: {$row['event']} → {$row['status']}");
        }

        return self::SUCCESS;
    }
}
