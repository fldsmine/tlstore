<?php

namespace App\Features\Wallets\Http\Controllers\Api\V1;

use App\Application\Actions\Wallets\ResolvePayoutAccount;
use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PayoutAccountValidationController
{
    public function resolve(Request $request, ResolvePayoutAccount $resolve): JsonResponse
    {
        $data = $request->validate([
            'bank_id' => ['required', 'uuid', 'exists:banks,id'],
            'account_number' => ['required', 'string', 'regex:/^[0-9]{6,20}$/'],
        ]);
        $result = $resolve->handle($request->user(), Bank::query()->findOrFail($data['bank_id']), $data['account_number']);
        return response()->json(['data' => $result]);
    }
}
