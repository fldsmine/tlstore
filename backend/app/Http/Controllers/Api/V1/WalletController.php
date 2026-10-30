<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $wallets = collect(User::WALLET_COLUMNS)
            ->map(fn (string $column, string $wallet): array => [
                'wallet' => $wallet,
                'label' => User::WALLET_LABELS[$wallet] ?? $wallet,
                'balance_usd' => round((float) $user->{$column}, 6),
                'spendable' => in_array($wallet, User::SPENDABLE_WALLETS, true),
            ])
            ->values();

        $wallets->push([
            'wallet' => 'legacy_shareholder',
            'label' => 'Legacy Nextel Shares (locked)',
            'balance_usd' => round((float) $user->legacy_shareholder_balance, 6),
            'spendable' => false,
        ]);

        return ApiResponse::success('Wallets retrieved successfully.', [
            'wallets' => $wallets,
            'total_balance_usd' => $user->total_balance,
            'lifetime_earnings_usd' => (float) $user->lifetime_earnings,
        ]);
    }
}
