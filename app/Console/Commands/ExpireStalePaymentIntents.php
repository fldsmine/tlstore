<?php

namespace App\Console\Commands;

use App\Application\Payments\PaymentIntentLifecycle;
use Illuminate\Console\Command;

class ExpireStalePaymentIntents extends Command
{
    protected $signature = 'payments:expire-intents';

    protected $description = 'Expire pending gateway checkouts whose window has passed and close their transaction projections';

    public function handle(PaymentIntentLifecycle $lifecycle): int
    {
        $expired = $lifecycle->expireStale();

        $this->info($expired === 1
            ? 'Expired 1 stale payment intent.'
            : "Expired {$expired} stale payment intents.");

        return self::SUCCESS;
    }
}
