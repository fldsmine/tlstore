<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBankAccountRequest;
use App\Http\Requests\Api\V1\VerifyBankAccountRequest;
use App\Http\Resources\Api\V1\BankAccountResource;
use App\Http\Resources\Api\V1\BankResource;
use App\Models\Bank;
use App\Services\BankAccountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    /** Banks available for the requester's country. */
    public function banks(Request $request): JsonResponse
    {
        $banks = Bank::query()
            ->active()
            ->forCountry((string) $request->user()->country)
            ->orderBy('name')
            ->get();

        return ApiResponse::success('Banks retrieved successfully.', [
            'banks' => BankResource::collection($banks),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $account = $request->user()->bankAccount()->with('bank')->first();

        if (! $account) {
            return ApiResponse::success('No payout account set.', ['bank_account' => null]);
        }

        return ApiResponse::success('Payout account retrieved successfully.', [
            'bank_account' => new BankAccountResource($account),
        ]);
    }

    /** Nigerian bank name resolution (NAN verification) before saving. */
    public function verify(VerifyBankAccountRequest $request, BankAccountService $accounts): JsonResponse
    {
        $result = $accounts->verifyNigerianAccount(
            (string) $request->validated('bank_id'),
            (string) $request->validated('account_number'),
        );

        if (! $result['ok']) {
            return ApiResponse::error($result['error'] ?? 'Could not verify this account.', 422, [
                'account_number' => [$result['error'] ?? 'Could not verify this account.'],
            ]);
        }

        return ApiResponse::success('Account verified successfully.', [
            'account_name' => $result['name'],
        ]);
    }

    public function store(StoreBankAccountRequest $request, BankAccountService $accounts): JsonResponse
    {
        $account = $this->persist($request, $accounts);

        return ApiResponse::created('Payout account saved successfully.', [
            'bank_account' => new BankAccountResource($account->load('bank')),
        ]);
    }

    /** Same rules as store — vendors may replace; everyone else is blocked once set. */
    public function update(StoreBankAccountRequest $request, BankAccountService $accounts): JsonResponse
    {
        $account = $this->persist($request, $accounts);

        return ApiResponse::success('Payout account updated successfully.', [
            'bank_account' => new BankAccountResource($account->load('bank')),
        ]);
    }

    private function persist(StoreBankAccountRequest $request, BankAccountService $accounts): \App\Models\BankAccount
    {
        $user = $request->user();

        return $user->isNigerian()
            ? $accounts->saveNigerian($user, (string) $request->validated('bank_id'), (string) $request->validated('account_number'))
            : $accounts->saveForeign($user, (string) $request->validated('bank_id'), (string) $request->validated('account_number'), trim((string) $request->validated('account_name')));
    }
}
