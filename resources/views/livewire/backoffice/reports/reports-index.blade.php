<div>
    <x-backoffice.page title="Reports & exports" subtitle="CSV exports bounded to 5,000 rows, newest first, every export audited">
        <x-backoffice.card title="Date range (optional)">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="label whitespace-nowrap" for="from">From</label>
                    <input id="from" type="date" wire:model.live="from" class="input !h-[42px]">
                </div>
                <div class="flex items-center gap-2">
                    <label class="label whitespace-nowrap" for="to">To</label>
                    <input id="to" type="date" wire:model.live="to" class="input !h-[42px]">
                </div>
                <p class="font-sans text-[12px] text-muted">Leave empty for an unbounded time window (still capped at 5,000 rows).</p>
            </div>
        </x-backoffice.card>

        @php
            $params = fn (string $report) => array_filter(['report' => $report, 'from' => $from, 'to' => $to]);
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="Users">
                <p class="font-sans text-[13px] text-muted mb-3">ID, name, username, email, phone, country, role, status, ban state, currency, join date. Banned/suspended filters are applied at your side of the CSV.</p>
                <a href="{{ route('backoffice.reports.export', $params('users')) }}" class="btn btn-primary btn-md"><i class="ri-download-2-line"></i> Download users.csv</a>
            </x-backoffice.card>

            <x-backoffice.card title="Transactions">
                <p class="font-sans text-[13px] text-muted mb-3">Projection rows: type, direction, decimal amount, fee, wallet, status, reference. Amounts are decimal units as stored by the app.</p>
                <a href="{{ route('backoffice.reports.export', $params('transactions')) }}" class="btn btn-primary btn-md"><i class="ri-download-2-line"></i> Download transactions.csv</a>
            </x-backoffice.card>

            <x-backoffice.card title="Ledger entries">
                <p class="font-sans text-[13px] text-muted mb-3">Canonical money: wallet, currency, entry type, direction, integer minor units, idempotency key. Safe to reconcile balances from this file.</p>
                <a href="{{ route('backoffice.reports.export', $params('ledger')) }}" class="btn btn-primary btn-md"><i class="ri-download-2-line"></i> Download ledger.csv</a>
            </x-backoffice.card>

            <x-backoffice.card title="Withdrawals">
                <p class="font-sans text-[13px] text-muted mb-3">Amount/fee/net in minor units, currency, status, provider reference, requested/processed timestamps. <em>Does not include payout bank details.</em></p>
                <a href="{{ route('backoffice.reports.export', $params('withdrawals')) }}" class="btn btn-primary btn-md"><i class="ri-download-2-line"></i> Download withdrawals.csv</a>
            </x-backoffice.card>
        </div>
    </x-backoffice.page>
</div>
