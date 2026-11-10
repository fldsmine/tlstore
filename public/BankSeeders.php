<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        // Nigerian banks (country defaults to NG). Foreign payout providers are seeded in Phase 3.
        $banks = [
            // Commercial banks
            ['name' => 'Access Bank Plc', 'code' => '044', 'tag' => 'access', 'type' => 'commercial'],
            ['name' => 'Citibank Nigeria Limited', 'code' => '023', 'tag' => 'citibank', 'type' => 'commercial'],
            ['name' => 'Ecobank Nigeria Plc', 'code' => '050', 'tag' => 'ecobank', 'type' => 'commercial'],
            ['name' => 'Fidelity Bank Plc', 'code' => '070', 'tag' => 'fidelity', 'type' => 'commercial'],
            ['name' => 'First Bank of Nigeria Limited', 'code' => '011', 'tag' => 'firstbank', 'type' => 'commercial'],
            ['name' => 'First City Monument Bank Limited', 'code' => '214', 'tag' => 'fcmb', 'type' => 'commercial'],
            ['name' => 'Guaranty Trust Bank Plc', 'code' => '058', 'tag' => 'gtbank', 'type' => 'commercial'],
            ['name' => 'Heritage Banking Company Ltd.', 'code' => '030', 'tag' => 'heritage', 'type' => 'commercial'],
            ['name' => 'Keystone Bank Limited', 'code' => '082', 'tag' => 'keystone', 'type' => 'commercial'],
            ['name' => 'Polaris Bank Limited', 'code' => '076', 'tag' => 'polaris', 'type' => 'commercial'],
            ['name' => 'Providus Bank', 'code' => '101', 'tag' => 'providus', 'type' => 'commercial'],
            ['name' => 'Stanbic IBTC Bank Plc', 'code' => '221', 'tag' => 'stanbic', 'type' => 'commercial'],
            ['name' => 'Standard Chartered Bank Nigeria Limited', 'code' => '068', 'tag' => 'standardchartered', 'type' => 'commercial'],
            ['name' => 'Sterling Bank Plc', 'code' => '232', 'tag' => 'sterling', 'type' => 'commercial'],
            ['name' => 'Titan Trust Bank Limited', 'code' => '102', 'tag' => 'titan', 'type' => 'commercial'],
            ['name' => 'Union Bank of Nigeria Plc', 'code' => '032', 'tag' => 'union', 'type' => 'commercial'],
            ['name' => 'United Bank For Africa Plc', 'code' => '033', 'tag' => 'uba', 'type' => 'commercial'],
            ['name' => 'Unity Bank Plc', 'code' => '215', 'tag' => 'unity', 'type' => 'commercial'],
            ['name' => 'Wema Bank Plc', 'code' => '035', 'tag' => 'wema', 'type' => 'commercial'],
            ['name' => 'Zenith Bank Plc', 'code' => '057', 'tag' => 'zenith', 'type' => 'commercial'],
            ['name' => 'Jaiz Bank Plc', 'code' => '301', 'tag' => 'jaiz', 'type' => 'commercial'],
            ['name' => 'Suntrust Bank Nigeria Limited', 'code' => '100', 'tag' => 'suntrust', 'type' => 'commercial'],

            // Digital banks & fintech
            ['name' => 'Kuda Microfinance Bank', 'code' => '090267', 'tag' => 'kuda', 'type' => 'digital'],
            ['name' => 'Opay Digital Services Limited', 'code' => '999992', 'tag' => 'opay', 'type' => 'digital'],
            ['name' => 'PalmPay Limited', 'code' => '999991', 'tag' => 'palmpay', 'type' => 'digital'],
            ['name' => 'Moniepoint Microfinance Bank', 'code' => '090405', 'tag' => 'moniepoint', 'type' => 'digital'],
            ['name' => 'VFD Microfinance Bank Limited', 'code' => '090110', 'tag' => 'vfd', 'type' => 'digital'],
            ['name' => 'Carbon Microfinance Bank', 'code' => '090529', 'tag' => 'carbon', 'type' => 'digital'],
            ['name' => 'FairMoney Microfinance Bank', 'code' => '090486', 'tag' => 'fairmoney', 'type' => 'digital'],
            ['name' => 'Sparkle Microfinance Bank', 'code' => '090325', 'tag' => 'sparkle', 'type' => 'digital'],

            // Mobile wallet destinations used by the mobile-money withdrawal option.
            ['name' => 'OPay Wallet', 'code' => 'opay-ng', 'tag' => 'opay-momo', 'type' => 'mobile_money', 'country' => 'NG'],
            ['name' => 'PalmPay Wallet', 'code' => 'palmpay-ng', 'tag' => 'palmpay-momo', 'type' => 'mobile_money', 'country' => 'NG'],
            ['name' => 'Moniepoint Wallet', 'code' => 'moniepoint-ng', 'tag' => 'moniepoint-momo', 'type' => 'mobile_money', 'country' => 'NG'],
            ['name' => 'Paga Wallet', 'code' => 'paga-ng', 'tag' => 'paga-momo', 'type' => 'mobile_money', 'country' => 'NG'],
        ];

        // South Africa uses real banks (universal branch codes where known; synthetic za-* otherwise).
        $southAfrica = [
            ['name' => 'ABSA', 'code' => '632005', 'tag' => 'absa'],
            ['name' => 'Access Bank', 'code' => '410506', 'tag' => 'access'],
            ['name' => 'Afribank', 'code' => 'za-afribank', 'tag' => 'afribank'],
            ['name' => 'African Bank', 'code' => '430000', 'tag' => 'africanbank'],
            ['name' => 'Bidvest Bank', 'code' => '462005', 'tag' => 'bidvest'],
            ['name' => 'Bank of New York Mellon', 'code' => 'za-bnymellon', 'tag' => 'bnymellon'],
            ['name' => 'Capitec Bank', 'code' => '470010', 'tag' => 'capitec'],
            ['name' => 'Discovery Bank', 'code' => '679000', 'tag' => 'discovery'],
            ['name' => 'First National Bank', 'code' => '250655', 'tag' => 'fnb'],
            ['name' => 'Grindrod', 'code' => '584000', 'tag' => 'grindrod'],
            ['name' => 'HSBC', 'code' => '587000', 'tag' => 'hsbc'],
            ['name' => 'Imperial', 'code' => 'za-imperial', 'tag' => 'imperial'],
            ['name' => 'Investec Bank', 'code' => '580105', 'tag' => 'investec'],
            ['name' => 'Mercantile Bank', 'code' => '450905', 'tag' => 'mercantile'],
            ['name' => 'Nedbank Limited', 'code' => '198765', 'tag' => 'nedbank'],
            ['name' => 'Old Mutual Money Account', 'code' => 'za-oldmutual', 'tag' => 'oldmutual'],
            ['name' => 'Sasfin', 'code' => '683000', 'tag' => 'sasfin'],
            ['name' => 'Standard Bank', 'code' => '051001', 'tag' => 'standardbank'],
            ['name' => 'TYME', 'code' => '678910', 'tag' => 'tyme'],
            ['name' => 'Ubank', 'code' => '431010', 'tag' => 'ubank'],
            ['name' => 'Windhoek Bank', 'code' => 'za-windhoek', 'tag' => 'windhoek'],
        ];

        foreach ($southAfrica as $bank) {
            $banks[] = $bank + ['type' => 'commercial', 'country' => 'ZA'];
        }

        // NIBSS/NIP institution codes, keyed by the Nigerian bank tag (the CBN `code` above is a different
        // scheme). Sourced from the client's authoritative NIP list. Banks absent here keep a null
        // institution_code — we don't guess a routing code. Only applied to NG banks (tags aren't globally unique).
        $institutionCodes = [
            'access' => '000014', 'citibank' => '000009', 'ecobank' => '000010', 'fidelity' => '000007',
            'firstbank' => '000016', 'fcmb' => '000003', 'gtbank' => '000013', 'heritage' => '000020',
            'keystone' => '000002', 'polaris' => '000008', 'providus' => '000023', 'stanbic' => '000012',
            'standardchartered' => '000021', 'sterling' => '000001', 'titan' => '000025', 'union' => '000018',
            'uba' => '000004', 'unity' => '000011', 'wema' => '000017', 'zenith' => '000015',
            'jaiz' => '000006', 'suntrust' => '000022',
            'kuda' => '090267', 'opay' => '100004', 'palmpay' => '100033', 'moniepoint' => '090405',
            'vfd' => '999999', 'carbon' => '100026', 'fairmoney' => '090551', 'sparkle' => '090325',
            'lapo' => '090177', 'accion' => '090134', 'abmfb' => '090270', 'aboveonly' => '090260',
            'aella' => '090614', 'bowen' => '090148', 'cemcs' => '090154', 'dot' => '090470',
            'hackman' => '090147', 'hasal' => '090121', 'ibile' => '090118', 'mint' => '090281',
            'npf' => '070001', 'page' => '070008', 'parkway' => '090390', 'peace' => '090402',
            'quickfund' => '090261', 'reliance' => '090173', 'renmoney' => '090198', 'safehaven' => '090286',
            'trustfund' => '090276', 'uc' => '090315', 'unical' => '090193',
            '9mobilepsb' => '120001', 'airtelpsb' => '120004', 'mtnpsb' => '120003',
            'nexim' => '030001', 'boa' => '090367',
            'nova' => '060003', 'coronation' => '060001', 'fbnmerchant' => '060002', 'fsdh' => '400001',
            'greenwich' => '060004', 'rand' => '000024',
        ];

        $countries = ['NG', 'GH', 'KE', 'ZA', 'ZM', 'UG', 'TZ', 'RW', 'GB', 'US', 'CA', 'AU', 'NZ', 'IN', 'AE', 'SA', 'JP', 'CN', 'BR'];
        $gatewayRows = [];
        foreach ($countries as $country) {
            foreach ($this->flutterwaveBanks($country) as $row) {
                $gatewayRows[] = $row;
            }
        }
        foreach (['NG' => 'nigeria', 'GH' => 'ghana', 'KE' => 'kenya', 'ZA' => 'south africa'] as $country => $label) {
            foreach ($this->paystackBanks($country, $label) as $row) {
                $gatewayRows[] = $row;
            }
        }

        $now = now();
        $legacyCountries = ['GH', 'CM', 'KE', 'UG', 'SL', 'MW', 'ZM', 'LR', 'GM', 'TZ', 'RW', 'BW', 'ZW', 'CI', 'BF', 'TG', 'SN', 'BJ', 'ML', 'NE'];
        $legacyTags = ['mtn', 'telecel', 'airtel', 'wave', 'orange', 'nita', 'moov', 'ecocash', 'mpesa', 'tmoney'];
        $legacyCodes = [];
        foreach ($legacyCountries as $legacyCountry) {
            foreach ($legacyTags as $legacyTag) $legacyCodes[] = $legacyTag.'-'.strtolower($legacyCountry);
        }
        Bank::query()->where('type', 'mobile_money')->whereIn('code', $legacyCodes)->update(['is_active' => false, 'updated_at' => $now]);
        foreach ($banks as $bank) {
            $this->storeBank([
                'name' => $bank['name'], 'code' => $bank['code'], 'tag' => $bank['tag'] ?? null,
                'type' => $bank['type'], 'country' => strtoupper($bank['country'] ?? 'NG'),
                'institution_code' => (($bank['country'] ?? 'NG') === 'NG') ? ($institutionCodes[$bank['tag'] ?? ''] ?? null) : null,
            ], $now);
        }
        foreach ($gatewayRows as $bank) {
            $this->storeBank($bank, $now);
        }
    }

    /** Flutterwave publishes banks and mobile-money providers by ISO-2 country. */
    private function flutterwaveBanks(string $country): array
    {
        $secret = config('trustlens.payment_gateways.flutterwave.secret_key');
        if (! is_string($secret) || $secret === '') return [];

        try {
            $response = Http::acceptJson()->withToken($secret)->timeout(20)
                ->get(rtrim((string) config('trustlens.payment_gateways.flutterwave.base_url'), '/').'/banks/'.$country, ['include_provider_type' => 1]);
            if (! $response->successful() || $response->json('status') !== 'success') return [];
            return collect($response->json('data', []))->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null) && filled($item['code'] ?? null))
                ->map(fn (array $item) => $this->gatewayRecord($country, 'flutterwave', $item))->all();
        } catch (\Throwable $exception) {
            Log::warning('Flutterwave bank catalog fetch failed.', ['country' => $country, 'message' => $exception->getMessage()]);
            return [];
        }
    }

    /** Paystack catalogs cover Ghana, Kenya, Nigeria and South Africa only. */
    private function paystackBanks(string $country, string $label): array
    {
        $secret = config('trustlens.payment_gateways.paystack.secret_key');
        if (! is_string($secret) || $secret === '') return [];

        try {
            $rows = [];
            $cursor = null;
            for ($page = 0; $page < 20; $page++) {
                $params = ['country' => $label, 'perPage' => 100, 'use_cursor' => true, 'include_nip_sort_code' => $country === 'NG'];
                if ($cursor) $params['next'] = $cursor;
                $response = Http::acceptJson()->withToken($secret)->timeout(20)->get(rtrim((string) config('trustlens.payment_gateways.paystack.base_url'), '/').'/bank', $params);
                if (! $response->successful() || $response->json('status') !== true) break;
                foreach ($response->json('data', []) as $item) {
                    if (is_array($item) && filled($item['name'] ?? null) && filled($item['code'] ?? null)) $rows[] = $this->gatewayRecord($country, 'paystack', $item);
                }
                $cursor = data_get($response->json(), 'meta.next');
                if (! $cursor) break;
            }
            return $rows;
        } catch (\Throwable $exception) {
            Log::warning('Paystack bank catalog fetch failed.', ['country' => $country, 'message' => $exception->getMessage()]);
            return [];
        }
    }

    private function gatewayRecord(string $country, string $provider, array $item): array
    {
        $providerType = strtolower((string) ($item['type'] ?? $item['provider_type'] ?? ''));
        $mobileMoney = str_contains($providerType, 'mobile') || str_contains($providerType, 'money');
        $code = (string) $item['code'];
        return [
            'name' => trim((string) $item['name']), 'code' => $code, 'tag' => Str::slug((string) $item['name']),
            'type' => $mobileMoney ? 'mobile_money' : 'commercial', 'country' => $country,
            'institution_code' => $country === 'NG' ? ($item['nip_code'] ?? null) : null,
            $provider.'_code' => $code,
        ];
    }

    private function storeBank(array $bank, $now): void
    {
        $country = strtoupper($bank['country']);
        $normalizedName = Str::of($bank['name'])->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
        $existing = Bank::query()->where('country', $country)->get()->first(function (Bank $candidate) use ($normalizedName): bool {
            return Str::of($candidate->name)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString() === $normalizedName;
        });
        $code = (string) ($existing?->code ?: $bank['code']);
        if (! $existing && Bank::query()->where('country', $country)->where('code', $code)->exists()) {
            $code .= '-'.Str::slug($bank['name']);
        }
        $record = [
            'name' => $bank['name'], 'code' => $code,
            'institution_code' => $bank['institution_code'] ?? $existing?->institution_code,
            'tag' => $bank['tag'] ?? $existing?->tag, 'type' => $bank['type'], 'country' => $country,
            'is_active' => true, 'updated_at' => $now,
        ];
        if (isset($bank['paystack_code'])) $record['paystack_code'] = $bank['paystack_code'];
        if (isset($bank['flutterwave_code'])) $record['flutterwave_code'] = $bank['flutterwave_code'];
        if ($existing) {
            $existing->forceFill($record)->save();
            return;
        }
        $record['id'] = (string) Str::uuid7();
        $record['created_at'] = $now;
        Bank::query()->forceCreate($record);
    }
}
