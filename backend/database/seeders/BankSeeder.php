<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;
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

            // Microfinance banks
            ['name' => 'LAPO Microfinance Bank', 'code' => '090177', 'tag' => 'lapo', 'type' => 'microfinance'],
            ['name' => 'Accion Microfinance Bank', 'code' => '090134', 'tag' => 'accion', 'type' => 'microfinance'],
            ['name' => 'AB Microfinance Bank', 'code' => '090270', 'tag' => 'abmfb', 'type' => 'microfinance'],
            ['name' => 'Above Only Microfinance Bank', 'code' => '090260', 'tag' => 'aboveonly', 'type' => 'microfinance'],
            ['name' => 'Aella Microfinance Bank', 'code' => '090316', 'tag' => 'aella', 'type' => 'microfinance'],
            ['name' => 'Bowen Microfinance Bank', 'code' => '090148', 'tag' => 'bowen', 'type' => 'microfinance'],
            ['name' => 'CEMCS Microfinance Bank', 'code' => '090154', 'tag' => 'cemcs', 'type' => 'microfinance'],
            ['name' => 'Dot Microfinance Bank', 'code' => '090470', 'tag' => 'dot', 'type' => 'microfinance'],
            ['name' => 'Hackman Microfinance Bank', 'code' => '090147', 'tag' => 'hackman', 'type' => 'microfinance'],
            ['name' => 'Hasal Microfinance Bank', 'code' => '090121', 'tag' => 'hasal', 'type' => 'microfinance'],
            ['name' => 'Ibile Microfinance Bank', 'code' => '090149', 'tag' => 'ibile', 'type' => 'microfinance'],
            ['name' => 'Links Microfinance Bank', 'code' => '090435', 'tag' => 'links', 'type' => 'microfinance'],
            ['name' => 'Mayfair Microfinance Bank', 'code' => '090280', 'tag' => 'mayfair', 'type' => 'microfinance'],
            ['name' => 'Mint Microfinance Bank', 'code' => '090281', 'tag' => 'mint', 'type' => 'microfinance'],
            ['name' => 'NPF Microfinance Bank', 'code' => '090128', 'tag' => 'npf', 'type' => 'microfinance'],
            ['name' => 'Page Microfinance Bank', 'code' => '090161', 'tag' => 'page', 'type' => 'microfinance'],
            ['name' => 'Parkway Microfinance Bank', 'code' => '090317', 'tag' => 'parkway', 'type' => 'microfinance'],
            ['name' => 'Peace Microfinance Bank', 'code' => '090332', 'tag' => 'peace', 'type' => 'microfinance'],
            ['name' => 'Personal Trust Microfinance Bank', 'code' => '090135', 'tag' => 'personaltrust', 'type' => 'microfinance'],
            ['name' => 'QuickFund Microfinance Bank', 'code' => '090278', 'tag' => 'quickfund', 'type' => 'microfinance'],
            ['name' => 'Reliance Microfinance Bank', 'code' => '090173', 'tag' => 'reliance', 'type' => 'microfinance'],
            ['name' => 'Renmoney Microfinance Bank', 'code' => '090322', 'tag' => 'renmoney', 'type' => 'microfinance'],
            ['name' => 'Safe Haven Microfinance Bank', 'code' => '090286', 'tag' => 'safehaven', 'type' => 'microfinance'],
            ['name' => 'Shield Microfinance Bank', 'code' => '090485', 'tag' => 'shield', 'type' => 'microfinance'],
            ['name' => 'TCF Microfinance Bank', 'code' => '090115', 'tag' => 'tcf', 'type' => 'microfinance'],
            ['name' => 'Trustfund Microfinance Bank', 'code' => '090276', 'tag' => 'trustfund', 'type' => 'microfinance'],
            ['name' => 'U&C Microfinance Bank Ltd', 'code' => '090315', 'tag' => 'uc', 'type' => 'microfinance'],
            ['name' => 'Unical Microfinance Bank', 'code' => '090251', 'tag' => 'unical', 'type' => 'microfinance'],
            ['name' => 'Xceed Microfinance Bank', 'code' => '090142', 'tag' => 'xceed', 'type' => 'microfinance'],

            // Payment service banks
            ['name' => '9mobile Payment Service Bank', 'code' => '120001', 'tag' => '9mobilepsb', 'type' => 'payment_service'],
            ['name' => 'Airtel Payments Service Bank', 'code' => '120004', 'tag' => 'airtelpsb', 'type' => 'payment_service'],
            ['name' => 'MTN Mobile Money Payment Service Bank', 'code' => '120003', 'tag' => 'mtnpsb', 'type' => 'payment_service'],

            // Development finance institutions
            ['name' => 'Federal Mortgage Bank of Nigeria', 'code' => '413', 'tag' => 'fmbn', 'type' => 'development'],
            ['name' => 'Nigeria Export Import Bank', 'code' => '601', 'tag' => 'nexim', 'type' => 'development'],
            ['name' => 'Bank of Agriculture', 'code' => '044A', 'tag' => 'boa', 'type' => 'development'],
            ['name' => 'Bank of Industry', 'code' => '070A', 'tag' => 'boi', 'type' => 'development'],

            // Merchant banks
            ['name' => 'NOVA Merchant Bank', 'code' => '305', 'tag' => 'nova', 'type' => 'merchant'],
            ['name' => 'Coronation Merchant Bank', 'code' => '559', 'tag' => 'coronation', 'type' => 'merchant'],
            ['name' => 'FBN Merchant Bank Limited', 'code' => '601A', 'tag' => 'fbnmerchant', 'type' => 'merchant'],
            ['name' => 'FSDH Merchant Bank Limited', 'code' => '501', 'tag' => 'fsdh', 'type' => 'merchant'],
            ['name' => 'Greenwich Merchant Bank', 'code' => '562', 'tag' => 'greenwich', 'type' => 'merchant'],
            ['name' => 'Rand Merchant Bank', 'code' => '502', 'tag' => 'rand', 'type' => 'merchant'],
        ];

        // Foreign payouts go to mobile-money wallets — the same provider set is offered in every foreign
        // country (no per-country tweaking); South Africa is the exception and uses real banks (below).
        $providers = [
            ['name' => 'MTN Mobile Money', 'tag' => 'mtn'],
            ['name' => 'Telecel Cash', 'tag' => 'telecel'],
            ['name' => 'Airtel Money', 'tag' => 'airtel'],
            ['name' => 'Wave', 'tag' => 'wave'],
            ['name' => 'Orange Money', 'tag' => 'orange'],
            ['name' => 'NITA', 'tag' => 'nita'],
            ['name' => 'Moov Money', 'tag' => 'moov'],
            ['name' => 'EcoCash', 'tag' => 'ecocash'],
            ['name' => 'M-Pesa', 'tag' => 'mpesa'],
            ['name' => 'T-Money', 'tag' => 'tmoney'],
        ];

        // Every supported country except Nigeria (real banks) + South Africa (real banks).
        $foreignCountries = ['GH', 'CM', 'KE', 'UG', 'SL', 'MW', 'ZM', 'LR', 'GM', 'TZ', 'RW', 'BW', 'ZW', 'CI', 'BF', 'TG', 'SN', 'BJ', 'ML', 'NE'];

        foreach ($foreignCountries as $country) {
            foreach ($providers as $provider) {
                $banks[] = [
                    'name' => $provider['name'],
                    'code' => $provider['tag'].'-'.strtolower($country), // globally-unique per provider+country
                    'tag' => $provider['tag'],
                    'type' => 'mobile_money',
                    'country' => $country,
                ];
            }
        }

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

        $now = now();

        Bank::upsert(
            array_map(function (array $bank) use ($institutionCodes, $now): array {
                $country = $bank['country'] ?? 'NG'; // the Nigerian list above carries no country key

                return [
                    'id' => (string) Str::uuid7(),
                    'name' => $bank['name'],
                    'code' => $bank['code'],
                    'institution_code' => $country === 'NG' ? ($institutionCodes[$bank['tag']] ?? null) : null,
                    'tag' => $bank['tag'],
                    'type' => $bank['type'],
                    'country' => $country,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $banks),
            ['code'],
            ['name', 'institution_code', 'tag', 'type', 'country'],
        );
    }
}
