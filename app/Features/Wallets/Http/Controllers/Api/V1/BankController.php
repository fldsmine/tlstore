<?php

namespace App\Features\Wallets\Http\Controllers\Api\V1;

use App\Models\Bank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BankController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['sometimes', 'string', 'size:2'],
            'search' => ['sometimes', 'nullable', 'string', 'max:80'],
            'type' => ['sometimes', 'nullable', 'string', 'in:commercial,digital,microfinance,payment_service,mobile_money'],
        ]);
        $country = strtoupper($data['country'] ?? (string) $request->user()->country_code);
        $banks = Bank::query()->active()->forCountry($country)
            ->when(filled($data['search'] ?? null), function ($query) use ($data): void {
                $search = trim($data['search']);
                $query->where(fn ($builder) => $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('tag', 'like', '%'.$search.'%'));
            })
            ->when(filled($data['type'] ?? null), fn ($query) => $query->where('type', $data['type']))
            ->orderBy('name')->limit(500)->get(['id', 'name', 'code', 'paystack_code', 'flutterwave_code', 'institution_code', 'tag', 'type', 'country']);

        return response()->json(['data' => $banks]);
    }
}
