<?php

namespace App\Features\Wallets\Http\Controllers\Api\V1;

use App\Application\Actions\Wallets\RequestWithdrawal;
use App\Application\Actions\Wallets\TransferTopupFunds;
use App\Application\Receipts\WithdrawalReceiptPdf;
use App\Application\Security\SecurityPinHasher;
use App\Enums\WalletType;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Models\WithdrawalOption;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class WalletController
{
    public function index(Request $request): JsonResponse
    {
        $wallets = Wallet::query()->where('user_id', $request->user()->id)->where('status', 'active')->get();
        $withdrawalSettings = SystemSetting::query()
            ->whereIn('setting_key', ['withdrawal.wallet.referral', 'withdrawal.wallet.bonus'])
            ->get()->keyBy('setting_key');
        $withdrawalEnabled = ['referral' => true, 'bonus' => false];
        foreach (['referral', 'bonus'] as $type) {
            $value = $withdrawalSettings->get('withdrawal.wallet.'.$type)?->value;
            if ($value !== null) {
                $withdrawalEnabled[$type] = is_array($value)
                    ? filter_var($value['active'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    : filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }
        $data = $wallets->map(function (Wallet $wallet) use ($withdrawalEnabled): array {
            $pending = (int) $wallet->ledgerEntries()->where('status', 'pending')->where('direction', 'credit')->sum('amount_minor');

            return [
                'id' => $wallet->id,
                'type' => $wallet->wallet_type->value,
                'label' => $wallet->wallet_type->label(),
                'currency' => $wallet->currency,
                'balance_minor' => (int) $wallet->balance_minor,
                'available_balance_minor' => (int) $wallet->balance_minor,
                'pending_balance_minor' => $pending,
                'withdrawal_enabled' => $withdrawalEnabled[$wallet->wallet_type->value] ?? true,
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
            // Same list as TransferTopupFunds (WalletType::transferTargets()):
            // the legacy mixed `review` wallet is not a transfer target.
            'target_wallet' => ['required', Rule::in(array_map(
                static fn (WalletType $type): string => $type->value,
                WalletType::transferTargets(),
            ))],
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
        return response()->json(['data' => $request->user()->withdrawalRequests()->with(['option', 'wallet', 'userSubscription.plan.group'])->latest()->paginate(30)]);
    }

    public function withdrawalReceipt(Request $request, int $withdrawal, WithdrawalReceiptPdf $receiptPdf)
    {
        $item = $request->user()->withdrawalRequests()
            ->with(['option', 'wallet', 'user:id,full_name', 'userSubscription.plan.group'])
            ->findOrFail($withdrawal);

        $filename = $receiptPdf->fileName($item);

        return response($receiptPdf->render($item), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
