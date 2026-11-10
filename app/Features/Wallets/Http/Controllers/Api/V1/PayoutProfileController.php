<?php

namespace App\Features\Wallets\Http\Controllers\Api\V1;

use App\Models\UserPayoutProfile;
use App\Models\Bank;
use App\Models\WithdrawalOption;
use App\Application\Actions\Wallets\ResolvePayoutAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PayoutProfileController
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->payoutProfile()->with('option')->first();
        return response()->json(['data' => $profile ? [
            'withdrawal_option_id' => $profile->withdrawal_option_id,
            'option' => $profile->option,
            'details' => $profile->details,
        ] : null]);
    }

    public function update(Request $request, ResolvePayoutAccount $resolve): JsonResponse
    {
        $data = $request->validate([
            'withdrawal_option_id' => ['required', 'integer', 'exists:withdrawal_options,id'],
            'details' => ['required', 'array'],
            'details.*' => ['required', 'string', 'max:512'],
            'bank_id' => ['nullable', 'uuid', 'exists:banks,id'],
        ]);
        $option = WithdrawalOption::query()->whereKey($data['withdrawal_option_id'])->where('is_available', true)->first();
        if (! $option) throw ValidationException::withMessages(['withdrawal_option_id' => 'This payment method is unavailable.']);
        $fields = array_values($option->fields ?? []);
        $details = array_intersect_key($data['details'], array_flip($fields));
        $missing = array_values(array_filter($fields, fn (string $field): bool => ! isset($details[$field]) || trim((string) $details[$field]) === ''));
        if ($missing) throw ValidationException::withMessages(['details' => 'Complete these fields: '.implode(', ', $missing)]);

        if ($option->option_id === 'bank_transfer') {
            if (empty($data['bank_id'])) {
                throw ValidationException::withMessages(['bank_id' => 'Select your bank before saving these details.']);
            }
            $bank = Bank::query()->active()->findOrFail($data['bank_id']);
            $resolved = $resolve->handle($request->user(), $bank, (string) $details['account_number']);
            $details['bank_name'] = $bank->name;
            $details['bank_code'] = $bank->code;
            $details['bank_id'] = $bank->id;
            $details['account_name'] = $resolved['account_name'];
        } elseif ($option->option_id === 'mobile_money') {
            if (empty($data['bank_id'])) {
                throw ValidationException::withMessages(['bank_id' => 'Choose your mobile money provider.']);
            }
            $bank = Bank::query()->active()->where('type', 'mobile_money')->findOrFail($data['bank_id']);
            if ($bank->country !== strtoupper((string) $request->user()->country_code)) {
                throw ValidationException::withMessages(['bank_id' => 'Choose a provider available in your country.']);
            }
            if (preg_match('/^[0-9+() .-]{7,24}$/', (string) $details['phone_number']) !== 1) {
                throw ValidationException::withMessages(['details.phone_number' => 'Enter a valid mobile money phone number.']);
            }
            $details['provider'] = $bank->name;
            $details['bank_code'] = $bank->code;
            $details['bank_id'] = $bank->id;
        }

        $profile = UserPayoutProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['withdrawal_option_id' => $option->id, 'details' => $details],
        )->load('option');
        return response()->json(['data' => [
            'withdrawal_option_id' => $profile->withdrawal_option_id,
            'option' => $profile->option,
            'details' => $profile->details,
        ]]);
    }
}
