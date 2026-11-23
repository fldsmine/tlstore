<?php

namespace App\Console\Commands;

use App\Contracts\BillPaymentProvider;
use App\Services\Vas\BillProviderManager;
use App\Services\Vas\CatalogCache;
use Illuminate\Console\Command;
use Throwable;

class CacheBillCatalog extends Command
{
    protected $signature = 'vas:cache-catalog {--provider= : Warm only this provider (vtpass|flutterwave)}';

    protected $description = 'Refresh the cached bill-provider catalogues (billers + items) so the VAS UI stays fast';

    public function handle(BillProviderManager $providers): int
    {
        $names = $this->option('provider') ? [(string) $this->option('provider')] : $providers->names();

        foreach ($names as $name) {
            $provider = $providers->driver($name);

            if (! $provider->configured()) {
                $this->warn("{$name}: not configured — skipped");

                continue;
            }

            try {
                [$services, $items] = $this->warm($provider);
                $this->info("{$name}: {$services} services, {$items} items cached");
            } catch (Throwable $e) {
                $this->error("{$name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Refetch and recache the provider's billers and each biller's items.
     *
     * @return array{0: int, 1: int} [services, items]
     */
    private function warm(BillPaymentProvider $provider): array
    {
        $services = 0;
        $items = 0;

        CatalogCache::refreshing(function () use ($provider, &$services, &$items): void {
            foreach ($provider->services() as $service => $data) {
                $services++;

                foreach ($data['providers'] ?? [] as $entry) {
                    $items += count($provider->variations($service, (string) $entry['id']));
                }
            }
        });

        return [$services, $items];
    }
}
