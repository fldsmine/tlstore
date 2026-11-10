<?php

namespace App\Features\Wallets\Http\Controllers\Api\V1;

use App\Application\Actions\Wallets\RequestWithdrawal;
use App\Application\Actions\Wallets\TransferTopupFunds;
use App\Application\Security\SecurityPinHasher;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Models\WithdrawalOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WalletController
{
    public function index(Request $request): JsonResponse
    {
        $wallets = Wallet::query()->where('user_id', $request->user()->id)->where('status', 'active')->get();
        $data = $wallets->map(function (Wallet $wallet): array {
            $pending = (int) $wallet->ledgerEntries()->where('status', 'pending')->where('direction', 'credit')->sum('amount_minor');

            return [
                'id' => $wallet->id,
                'type' => $wallet->wallet_type->value,
                'currency' => $wallet->currency,
                'balance_minor' => (int) $wallet->balance_minor,
                'available_balance_minor' => (int) $wallet->balance_minor,
                'pending_balance_minor' => $pending,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function ledger(Request $request, Wallet $wallet): JsonResponse
    {
        abort_unless($wallet->user_id === $request->user()->id, 404);

        return response()->json(['data' => $wallet->ledgerEntries()->latest('id')->paginate(30)]);
    }

    public function transfer(Request $request, TransferTopupFunds $transfer): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'target_wallet' => ['required', 'in:review,share,referral,bonus'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);
        $transfer->handle($request->user(), $data['amount_minor'], $data['target_wallet'], $data['idempotency_key']);

        return response()->json(['message' => 'Top-up funds transferred.']);
    }

    public function withdrawalOptions(): JsonResponse
    {
        return response()->json(['data' => WithdrawalOption::query()->where('is_available', true)->orderBy('order')->get()]);
    }

    public function requestWithdrawal(Request $request, RequestWithdrawal $withdrawal, SecurityPinHasher $pinHasher): JsonResponse
    {
        $requiresPin = filled($request->user()->security_pin);
        $data = $request->validate([
            'wallet_id' => ['required', 'integer'],
            'withdrawal_option_id' => ['required', 'integer'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'payout_details' => ['required', 'array'],
            'payout_details.*' => ['required', 'string', 'max:512'],
            'idempotency_key' => ['required', 'string', 'max:80'],
            'security_pin' => [$requiresPin ? 'required' : 'nullable', 'digits:4'],
        ]);
        if ($requiresPin && ! $pinHasher->check((string) $data['security_pin'], $request->user()->security_pin)) {
            return response()->json(['message' => 'The withdrawal PIN is incorrect.'], 422);
        }
        $result = $withdrawal->handle(
            $request->user(), $data['wallet_id'], $data['withdrawal_option_id'],
            $data['amount_minor'], $data['payout_details'], $data['idempotency_key'],
        );

        return response()->json(['data' => $result, 'message' => 'Withdrawal request submitted for admin settlement.'], 201);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->withdrawalRequests()->with(['option', 'userSubscription.plan.group'])->latest()->paginate(30)]);
    }
}
