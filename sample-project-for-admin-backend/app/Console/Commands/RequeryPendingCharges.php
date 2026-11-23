<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Jobs\RequeryChargeJob;
use App\Models\ProductOrder;
use App\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:requery-pending {--days=7 : Requery pending charges created within this many days (raise it to settle older hung ones)}')]
#[Description('Dispatch a unique requery job for each still-pending gateway charge (including loan and guarantor-form fees) and guest eSIM order.')]
class RequeryPendingCharges extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        // One unique job per charge; each settles against its OWN pinned provider, so a single
        // sweep covers all three gateways and no charge is requeried twice concurrently.
        $references = Transaction::query()
            ->whereIn('type', [
                TransactionType::BILLS_TOPUP->value,
                TransactionType::WEBSITE_RENEWAL->value,
                TransactionType::STORAGE_PURCHASE->value,
                TransactionType::PACKAGE_PURCHASE->value,
                TransactionType::LOAN_APPLICATION_FEE->value,
                TransactionType::LOAN_GUARANTOR_FORM_FEE->value,
            ])
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(3))
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('id')
            ->limit(200)
            ->pluck('reference');

        // Guest orders from the product pages have no Transactions row — sweep them the same way.
        $orderReferences = ProductOrder::query()
            ->where('status', ProductOrder::STATUS_PENDING)
            ->where('created_at', '<=', now()->subMinutes(3))
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('id')
            ->limit(200)
            ->pluck('reference');

        $references = $references->merge($orderReferences)->unique()->values();

        $references->each(fn (string $reference) => RequeryChargeJob::dispatch($reference));

        $this->info("Dispatched {$references->count()} charge requery job(s).");

        return self::SUCCESS;
    }
}
