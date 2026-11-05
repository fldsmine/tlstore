<?php

namespace App\Features\Wallets\Http\Controllers\Api\V1;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountHistoryController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 5);
        $transactions = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        $transactions->through(static function (Transaction $transaction): array {
            $meta = $transaction->meta ?? [];
            $currency = strtoupper((string) ($meta['currency'] ?? 'USD'));

            return [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'direction' => $transaction->direction,
                'amount_minor' => CurrencyMinorUnits::toMinor($transaction->amount, $currency),
                'fee_minor' => CurrencyMinorUnits::toMinor($transaction->fee, $currency),
                'currency' => $currency,
                'currency_minor_units' => $meta['currency_minor_units'] ?? CurrencyMinorUnits::precision($currency),
                'wallet' => $transaction->wallet,
                'status' => $transaction->status,
                'reference' => $transaction->reference,
                'description' => $transaction->description,
                'meta' => $meta,
                'created_at' => $transaction->created_at,
            ];
        });

        return response()->json(['data' => $transactions]);
    }
}
