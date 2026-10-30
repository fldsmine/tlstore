<?php

namespace App\Livewire\Backoffice\PaymentManagers;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Livewire\Backoffice\PaymentManagers\Concerns\GuardsPaymentManagerPages;
use App\Services\Payments\MerchantWalletService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Backoffice → payment manager → Balance history: every credit/debit that moved the merchant wallet,
 * with currency/direction/date filters.
 *
 * The merchant services normalise their own pagination into the same contract: a `has_more` flag
 * plus a per-row `pointer` the caller passes back as `starting_after`. Korapay uses opaque cursors,
 * Flutterwave uses page numbers — either way paging is a cursor stack here, so "Previous" replays
 * the previous cursor exactly.
 *
 * The concrete per-provider component supplies the merchant service, whether a page size can be
 * chosen, and the view.
 */
abstract class BalanceHistory extends Component
{
    use AuthorizesAdminActions, GuardsPaymentManagerPages;

    /** Guarded allow-list so ?limit= can't be used to hammer the API. */
    private const LIMITS = [10, 25, 50, 100];

    #[Url]
    public string $currency = 'NGN';

    #[Url]
    public string $direction = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    #[Url]
    public int $limit = 25;

    /** @var list<array{starting_after: ?string, ending_before: ?string}> */
    public array $cursors = [];

    public int $cursorIndex = 0;

    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public bool $hasMore = false;

    public ?string $error = null;

    public bool $loaded = false;

    public function mount(): void
    {
        $this->load();
    }

    public function updatedCurrency(): void
    {
        $this->resetCursors();
        $this->load();
    }

    public function updatedDirection(): void
    {
        $this->resetCursors();
        $this->load();
    }

    public function updatedLimit(): void
    {
        $this->limit = in_array((int) $this->limit, self::LIMITS, true) ? (int) $this->limit : 25;
        $this->resetCursors();
        $this->load();
    }

    public function applyFilters(): void
    {
        $this->assertAdmin();

        $this->validate([
            'dateFrom' => ['nullable', 'date_format:Y-m-d'],
            'dateTo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dateFrom'],
        ], [
            'dateTo.after_or_equal' => 'The "to" date must be on or after the "from" date.',
        ]);

        $this->resetCursors();
        $this->load();
    }

    public function clearFilters(): void
    {
        $this->assertAdmin();

        $this->reset('dateFrom', 'dateTo', 'direction');
        $this->currency = 'NGN';
        $this->resetCursors();
        $this->load();
    }

    public function nextPage(): void
    {
        $this->assertAdmin();

        $last = $this->entries === [] ? null : (string) end($this->entries)['pointer'];

        if (! $this->hasMore || $last === '') {
            return;
        }

        $this->cursorIndex++;
        $this->cursors[$this->cursorIndex] = ['starting_after' => $last, 'ending_before' => null];
        $this->load();
    }

    public function previousPage(): void
    {
        $this->assertAdmin();

        if ($this->cursorIndex === 0) {
            return;
        }

        $this->cursorIndex--;
        $this->load();
    }

    private function resetCursors(): void
    {
        $this->cursors = [['starting_after' => null, 'ending_before' => null]];
        $this->cursorIndex = 0;
    }

    private function load(): void
    {
        if ($this->cursors === []) {
            $this->resetCursors();
        }

        // `limit` is shareable via ?limit=, so the allow-list has to be enforced on the way in too —
        // and written back, so the "per page" select always reflects what was actually requested.
        $this->limit = in_array((int) $this->limit, self::LIMITS, true) ? (int) $this->limit : 25;

        $cursor = $this->cursors[$this->cursorIndex] ?? ['starting_after' => null, 'ending_before' => null];

        $result = $this->manager()->balanceHistory([
            'currency' => $this->currency !== '' ? $this->currency : null,
            'direction' => $this->direction !== '' ? $this->direction : null,
            'date_from' => $this->dateFrom !== '' ? $this->dateFrom.'-00-00-00' : null,
            'date_to' => $this->dateTo !== '' ? $this->dateTo.'-23-59-59' : null,
            'limit' => $this->limit,
            'starting_after' => $cursor['starting_after'],
            'ending_before' => $cursor['ending_before'],
        ]);

        if ($result['ok']) {
            $this->entries = $result['entries'];
            $this->hasMore = $result['has_more'];
            $this->error = null;
        } else {
            $this->entries = [];
            $this->hasMore = false;
            $this->error = $result['message'];
        }

        $this->loaded = true;
    }

    public function render(): View
    {
        return view($this->viewName(), [
            'currencies' => $this->manager()->payoutCurrencies(),
            'limits' => self::LIMITS,
            'showLimit' => $this->supportsPageSize(),
        ]);
    }

    /* ------------------------------------------------------------------ per-provider hooks */

    /** The merchant service that talks to this provider. */
    abstract protected function manager(): MerchantWalletService;

    /** Whether this provider's history endpoint accepts a page size (Korapay does, Flutterwave doesn't). */
    protected function supportsPageSize(): bool
    {
        return false;
    }

    /** The blade this component renders (each provider has its own thin wrapper blade). */
    abstract protected function viewName(): string;
}
