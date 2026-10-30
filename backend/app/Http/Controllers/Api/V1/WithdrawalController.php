<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WithdrawRequest;
use App\Http\Resources\Api\V1\WithdrawalResource;
use App\Services\WithdrawalService;
use App\Support\ApiResponse;
use App\Support\TransactionPinGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $paginator = $request->user()->withdrawals()
            ->latest('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return ApiResponse::paginated($paginator, WithdrawalResource::class, 'Withdrawals retrieved successfully.');
    }

    public function show(Request $request, string $withdrawal): JsonResponse
    {
        $record = $request->user()->withdrawals()->whereKey($withdrawal)->firstOrFail();

        return ApiResponse::success('Withdrawal retrieved successfully.', [
            'withdrawal' => new WithdrawalResource($record),
        ]);
    }

    public function store(WithdrawRequest $request, WithdrawalService $withdrawals, TransactionPinGuard $pins): JsonResponse
    {
        $user = $request->user();
        $wallet = (string) $request->validated('wallet');
        $amount = (float) $request->validated('amount');

        if ($pins->throttled($user)) {
            return ApiResponse::error('Too many incorrect attempts. Try again in '.$pins->availableIn($user).' seconds.', 429)
                ->withHeaders(['Retry-After' => (string) $pins->availableIn($user)]);
        }

        if (! $pins->verify($user, (string) $request->validated('pin'))) {
            return ApiResponse::error('Your transaction PIN is incorrect.', 422, [
                'pin' => ['Your transaction PIN is incorrect.'],
            ]);
        }

        if ($amount > $user->walletBalance($wallet) + 0.000001) {
            return ApiResponse::error('The amount exceeds your available balance.', 422, [
                'amount' => ['The amount exceeds your available balance.'],
            ]);
        }

        // Business rules throw ValidationException → 422 envelope.
        $withdrawals->guardWithdrawable($user, $wallet, $amount);

        $withdrawalCode = $request->input('withdrawal_code');

        $withdrawal = $withdrawals->request($user, $wallet, $amount, $withdrawalCode);

        return ApiResponse::created('Withdrawal request received. It is being processed.', [
            'withdrawal' => new WithdrawalResource($withdrawal),
        ]);
    }
}
