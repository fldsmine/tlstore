<?php

namespace Database\Seeders;

use App\Enums\AnnouncementType;
use App\Enums\BankAccountStatus;
use App\Enums\CloudCallStatus;
use App\Enums\CouponType;
use App\Enums\LoanStatus;
use App\Enums\PayLaterStatus;
use App\Enums\ReferralStatus;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Enums\VtuStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Ad;
use App\Models\AdditionalMinutePlan;
use App\Models\AdShare;
use App\Models\AllocatedNumber;
use App\Models\Announcement;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\CallClaim;
use App\Models\CallType;
use App\Models\CloudCall;
use App\Models\CloudDistribution;
use App\Models\CloudInboxMessage;
use App\Models\CloudNumber;
use App\Models\CloudStoragePlan;
use App\Models\CloudStorageTopUp;
use App\Models\Coupon;
use App\Models\CouponBatch;
use App\Models\DebitCard;
use App\Models\Loan;
use App\Models\MinuteCredit;
use App\Models\MinuteTopUp;
use App\Models\Package;
use App\Models\Referral;
use App\Models\RewardTarget;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VtuOrder;
use App\Models\Withdrawal;
use App\Models\WithdrawalPaymentSheet;
use App\Services\AgentTransferService;
use App\Services\CallLogService;
use App\Services\CloudDistributionService;
use App\Services\CloudInboxService;
use App\Services\CloudLineService;
use App\Services\CurrencyService;
use App\Services\PackageActivationService;
use App\Services\PayLaterService;
use App\Services\Vas\VtuCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $packages = Package::ordered()->get();
        $minutePlans = AdditionalMinutePlan::all();

        // Top earners — dominate the Top Earners leaderboard.
        User::factory()->count(6)->topEarner()->create()->each(
            fn (User $user) => $user->update(['package_id' => $packages->random()->id])
        );

        // Agents — shown on the Agents page; each issues a coupon batch (paid from their agent wallet).
        User::factory()->count(4)->agent()->create()->each(function (User $agent) use ($packages, $minutePlans): void {
            $package = $packages->random();

            $agent->update([
                'package_id' => $package->id,
                'country' => fake()->randomElement(['NG', 'NG', 'GH', 'KE', 'ZA']),
                'agent_balance' => fake()->randomFloat(2, 100, 800),
                'lifetime_earnings' => fake()->randomFloat(2, 300, 1500),
            ]);

            // Code class follows the issuing vendor's country: Nigerian vendors mint nigeria codes, foreign vendors foreign.
            $region = Coupon::regionFor($agent);

            $batch = CouponBatch::create([
                'agent_id' => $agent->id,
                'package_id' => $package->id,
                'type' => CouponType::ACTIVATION->value,
                'region' => $region,
                'quantity' => 3,
            ]);

            for ($i = 0; $i < 3; $i++) {
                Coupon::create([
                    'type' => CouponType::ACTIVATION->value,
                    'agent_id' => $agent->id,
                    'package_id' => $package->id,
                    'batch_id' => $batch->id,
                    'region' => $region,
                    'price' => $package->price,
                ]);
            }

            // A couple of additional-minutes tokens for the Verify Token demo.
            for ($i = 0; $i < 2; $i++) {
                $plan = $minutePlans->random();
                Coupon::create([
                    'type' => CouponType::EXTRA_MINUTES->value,
                    'agent_id' => $agent->id,
                    'additional_minute_plan_id' => $plan->id,
                    'region' => $region,
                    'price' => $plan->price,
                ]);
            }

        });

        // Regular users with a single-level referral chain.
        $previous = User::query()->inRandomOrder()->first();

        User::factory()->count(12)->create()->each(function (User $user) use ($packages, &$previous): void {
            $user->update([
                'package_id' => fake()->boolean(70) ? $packages->random()->id : null,
                'referrer_id' => $previous?->id,
                'lifetime_earnings' => fake()->randomFloat(2, 0, 200),
            ]);

            $previous = $user;
        });

        // Known login for manual testing — fully activated agent with real call activity.
        $testUser = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'transaction_pin' => '1234',  // hashed via cast — fully onboarded for manual testing
            'is_agent' => true,           // so we can work on the agent/vendor portal
            'is_special' => true,         // special vendor — can transfer agent_balance to other vendors
            'show_on_agents_page' => true,
            'telegram' => 'https://t.me/nexteltest',
            'agent_balance' => 500,   // float to fund token generation
            'bills_balance' => 20,    // topped-up bills wallet for VTU demos
        ]);

        // Royal (top tier) so the "everything" user demos full Cloud: calling, contacts and earnings.
        // The Ghana user below stays on the entry tier, which exercises the SMS-only Cloud view.
        app(PackageActivationService::class)->activate($testUser, $packages->last());
        $this->seedCallActivity($testUser->refresh());
        $this->seedTransactions($testUser);
        $this->seedReferralTeam($testUser);
        $this->seedRewardTargets($testUser);
        $this->seedAds($testUser);

        // Demo special-vendor transfer so the AGENT_TRANSFER ledger appears in history.
        $recipientAgent = User::query()->where('is_agent', true)->whereKeyNot($testUser->id)->inRandomOrder()->first();
        if ($recipientAgent) {
            app(AgentTransferService::class)->transfer($testUser->refresh(), $recipientAgent->username, 25.00);
        }

        // A second user that still needs to activate a package — for testing the activation flow.
        User::factory()->create([
            'first_name' => 'Tester',
            'last_name' => 'Two',
            'username' => 'testtwo',
            'email' => 'test2@example.com',
            'transaction_pin' => '1234', // onboarded (reaches the dashboard) but has no package
            'package_id' => null,
        ]);

        // A foreign (Ghana) user — exercises country onboarding + the redemption rule
        // (may redeem foreign-vendor codes, never a Nigerian-vendor code).
        User::factory()->create([
            'first_name' => 'Kwame',
            'last_name' => 'Mensah',
            'username' => 'ghanauser',
            'email' => 'foreign@example.com',
            'transaction_pin' => '1234',
            'country' => 'GH',
            'package_id' => $packages->first()->id,
        ]);

        // Known activation tokens (one per package) for testing the activation modal: ACTIVATE1, ACTIVATE2…
        foreach ($packages as $package) {
            Coupon::create([
                'type' => CouponType::ACTIVATION->value,
                'code' => 'ACTIVATE'.$package->tier_level,
                'package_id' => $package->id,
                'agent_id' => $testUser->id,
                'region' => Coupon::regionFor($testUser),
                'price' => $package->price,
            ]);
        }

        // A known FOREIGN activation token (region=foreign) so the Ghana user can redeem it, while the
        // Nigerian ACTIVATE codes above stay off-limits to foreign accounts (the Phase 1 redemption rule).
        $foreignAgent = User::query()->where('is_agent', true)->where('country', '!=', 'NG')->first();
        Coupon::create([
            'type' => CouponType::ACTIVATION->value,
            'code' => 'FOREIGN1',
            'package_id' => $packages->first()->id,
            'agent_id' => $foreignAgent?->id,
            'region' => Coupon::REGION_FOREIGN,
            'price' => $packages->first()->price,
        ]);

        // Known Cloud-plan tokens (one per active plan): CLOUD1, CLOUD2, CLOUD3 — redeem on the Nextel Cloud screen.
        foreach (CloudStoragePlan::query()->active()->ordered()->get()->values() as $i => $cloudStoragePlan) {
            Coupon::create([
                'type' => CouponType::CLOUD->value,
                'code' => 'CLOUD'.($i + 1),
                'cloud_storage_plan_id' => $cloudStoragePlan->id,
                'agent_id' => $testUser->id,
                'region' => Coupon::regionFor($testUser),
                'price' => $cloudStoragePlan->token_price,
            ]);
        }

        // Demo vendor batches owned by the test agent so the Vendor Dashboard has content.
        $this->seedVendorBatches($testUser, $packages->first(), AdditionalMinutePlan::ordered()->first());

        // Mark a few coupons as redeemed (for the Verify Token "already redeemed" demo).
        Coupon::query()->where('type', CouponType::ACTIVATION->value)->take(2)->get()->each(function (Coupon $coupon): void {
            $coupon->update([
                'user_id' => User::query()->where('is_agent', false)->inRandomOrder()->value('id'),
                'used_at' => now()->subDays(random_int(1, 20)),
            ]);
        });

        // ── Admin backoffice demo data ─────────────────────────────────────────────
        // Spread across $testUser (the "everything" user) and the wider user base so the
        // admin list pages + user-detail tabs all have realistic content.
        $this->seedCallPool();
        $this->seedBankAccounts($testUser);
        $this->seedWithdrawals($testUser);
        $this->seedPaymentSheet();
        $this->seedLoans($testUser);
        $this->seedMinuteCredits($testUser);
        $this->seedRealPayLater($testUser);
        $this->seedAdShareSpread($testUser);
        $this->seedAnnouncements();
        $this->seedDebitCard($testUser);
        $this->seedSponsorNumbers();
        $this->seedCloud($testUser);
        $this->seedBillPayments($testUser);
        $this->seedIndirectReferrals($testUser);
        $this->seedRewardTargetSpread($testUser);
        $this->seedStatusVariety();
    }

    /**
     * Give $testUser + a dozen other users a verified bank account (required before a
     * withdrawal can carry a bank snapshot).
     */
    private function seedBankAccounts(User $testUser): void
    {
        // 12 random Nigerians + EVERY foreign user (so we get multi-currency withdrawals) + the test agent.
        $recipients = User::query()
            ->whereKeyNot($testUser->id)
            ->whereDoesntHave('bankAccount')
            ->where('country', 'NG')
            ->inRandomOrder()
            ->take(8)
            ->get()
            ->merge(User::query()->whereKeyNot($testUser->id)->whereDoesntHave('bankAccount')->where('country', '!=', 'NG')->get())
            ->push($testUser);

        foreach ($recipients as $user) {
            if ($user->bankAccount()->exists()) {
                continue;
            }

            // Each user gets a payout account from their OWN country (foreign = a mobile-money provider).
            $bankId = Bank::query()->forCountry($user->country)->inRandomOrder()->value('id')
                ?? Bank::query()->forCountry('NG')->inRandomOrder()->value('id');

            BankAccount::create([
                'user_id' => $user->id,
                'bank_id' => $bankId,
                'account_number' => (string) fake()->numerify('##########'),
                'account_name' => $user->full_name,
                'status' => BankAccountStatus::VERIFIED->value,
            ]);
        }
    }

    /**
     * Withdrawals across all four statuses for $testUser (≥2 each) plus ~25 scattered
     * across other users who have a bank account, over the last ~60 days.
     */
    private function seedWithdrawals(User $testUser): void
    {
        $wallets = User::SPENDABLE_WALLETS;
        $rejectionReasons = [
            'Account name mismatch',
            'Insufficient KYC verification',
            'Suspected fraudulent activity',
            'Bank account could not be validated',
        ];

        $make = function (User $user, WithdrawalStatus $status) use ($wallets, $rejectionReasons): void {
            $account = $user->bankAccount()->with('bank')->first();

            if (! $account) {
                return;
            }

            $wallet = fake()->randomElement($wallets);
            $amount = (float) fake()->randomFloat(2, 5, 80);
            $fee = 0.1;
            $amountPaid = round($amount - $fee, 2);
            $processed = in_array($status, [WithdrawalStatus::COMPLETED, WithdrawalStatus::REJECTED], true);
            $createdAt = now()->subDays(fake()->numberBetween(0, 60))->subHours(fake()->numberBetween(0, 23));
            $processedAt = $processed ? $createdAt->copy()->addDays(fake()->numberBetween(1, 5)) : null;
            // Pending = not yet debited (debit happens on approval); others were debited at request.
            $deductAt = $status === WithdrawalStatus::PENDING ? 'approval' : 'request';
            $rejectionReason = $status === WithdrawalStatus::REJECTED ? fake()->randomElement($rejectionReasons) : null;
            $reference = Withdrawal::generateReference();

            // Full bank snapshot so payment sheets render from meta (no live relation needed).
            $bankSnapshot = [
                'bank_name' => $account->bank?->name ?? 'Demo Bank',
                'bank_code' => $account->bank?->code ?? '',
                'bank_tag' => $account->bank?->tag,
                'account_number' => $account->account_number,
                'account_name' => $account->account_name,
            ];

            // Local-currency payout snapshot (USD base → the user's local currency at the country rate).
            $localCurrency = currency()->code($user->country);
            $localAmount = currency()->toLocal($amount, $user->country);
            $localPaid = currency()->toLocal($amountPaid, $user->country);
            $currencyMeta = [
                'exchange_rate' => currency()->rate($user->country),
                'local_currency' => $localCurrency,
                'local_symbol' => currency()->symbol($user->country),
            ];

            // Mirror the live ledger: a debited payout has a COMPLETED WITHDRAWAL transaction created the
            // moment money leaves the wallet (request-time here). Pending payouts aren't debited yet → no txn.
            $transactionId = null;
            if ($deductAt === 'request') {
                $transactionId = Transaction::create([
                    'user_id' => $user->id,
                    'type' => TransactionType::WITHDRAWAL->value,
                    'direction' => 'debit',
                    'amount' => $amount,
                    'fee' => $fee,
                    'wallet' => $wallet,
                    'status' => 'completed',
                    'reference' => $reference,
                    'description' => 'Withdrawal to '.$bankSnapshot['bank_name'].' ('.$bankSnapshot['account_number'].')',
                    'meta' => array_merge($bankSnapshot, $currencyMeta, [
                        'wallet_label' => User::WALLET_LABELS[$wallet] ?? null,
                        'net' => $amountPaid,
                    ]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ])->id;
            }

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'reference' => $reference,
                'wallet' => $wallet,
                'amount' => $amount,
                'fee' => $fee,
                'amount_paid' => $amountPaid,
                'local_currency' => $localCurrency,
                'local_amount' => $localAmount,
                'local_amount_paid' => $localPaid,
                'status' => $status->value,
                'processed_at' => $processedAt,
                'transaction_id' => $transactionId,
                'meta' => array_merge($bankSnapshot, $currencyMeta, ['deduct_at' => $deductAt], $rejectionReason ? ['rejection_reason' => $rejectionReason] : []),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // A rejected payout that had already been debited is refunded via an ADJUSTMENT credit; the
            // original WITHDRAWAL debit stays completed (matches ListWithdrawals::rejectWithdrawal()).
            if ($status === WithdrawalStatus::REJECTED && $transactionId !== null) {
                Transaction::create([
                    'user_id' => $user->id,
                    'type' => TransactionType::ADJUSTMENT->value,
                    'direction' => 'credit',
                    'amount' => $amount,
                    'wallet' => $wallet,
                    'status' => 'completed',
                    'reference' => 'ADJ-'.strtoupper(Str::random(10)),
                    'description' => 'Refund: withdrawal '.$reference.' rejected',
                    'meta' => ['withdrawal_id' => $withdrawal->id, 'kind' => 'reject_refund', 'reason' => $rejectionReason],
                    'created_at' => $processedAt,
                    'updated_at' => $processedAt,
                ]);
            }
        };

        // $testUser: at least two of every status.
        foreach (WithdrawalStatus::cases() as $status) {
            $make($testUser, $status);
            $make($testUser, $status);
        }

        // ~25 scattered across other users who have a bank account.
        $others = User::query()
            ->whereKeyNot($testUser->id)
            ->whereHas('bankAccount')
            ->inRandomOrder()
            ->get();

        if ($others->isEmpty()) {
            return;
        }

        for ($i = 0; $i < 25; $i++) {
            $make($others->random(), fake()->randomElement(WithdrawalStatus::cases()));
        }

        // Guarantee foreign-currency withdrawals (incl. a PROCESSING one) so the admin can demo the
        // per-currency filter + per-currency export (a sheet must be single-currency).
        User::query()->where('country', '!=', 'NG')->whereHas('bankAccount')->get()->each(function (User $foreign) use ($make): void {
            $make($foreign, WithdrawalStatus::PROCESSING);
            $make($foreign, fake()->randomElement(WithdrawalStatus::cases()));
        });
    }

    /**
     * Cash loans across all three statuses for $testUser plus ~15 scattered.
     */
    private function seedLoans(User $testUser): void
    {
        $make = function (User $user, LoanStatus $status): void {
            $amount = (float) fake()->randomFloat(2, 10, 100);
            $appliedAt = now()->subDays(fake()->numberBetween(1, 40));

            Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'total_repayment' => round($amount * 1.1, 2),
                'due_date' => today()->addDays(fake()->numberBetween(7, 30)),
                'status' => $status->value,
                'created_at' => $appliedAt,
                'updated_at' => $appliedAt,
            ]);
        };

        foreach (LoanStatus::cases() as $status) {
            $make($testUser, $status);
        }

        $others = User::query()->whereKeyNot($testUser->id)->inRandomOrder()->get();

        for ($i = 0; $i < 15; $i++) {
            $make($others->random(), fake()->randomElement(LoanStatus::cases()));
        }
    }

    /**
     * Pay Later / minute credits across all states for $testUser — including one ACTIVE row
     * that is already overdue (due_date in the past) — plus ~12 scattered.
     */
    private function seedMinuteCredits(User $testUser): void
    {
        $plans = AdditionalMinutePlan::all();

        $make = function (User $user, PayLaterStatus $status, bool $overdue = false) use ($plans): void {
            $plan = $plans->isNotEmpty() ? $plans->random() : null;
            $appliedAt = now()->subDays(fake()->numberBetween(3, 30));

            $dueDate = match (true) {
                $overdue => today()->subDays(fake()->numberBetween(1, 10)),
                $status === PayLaterStatus::DEFAULTED => today()->subDays(fake()->numberBetween(1, 20)),
                default => today()->addDays(fake()->numberBetween(3, 14)),
            };

            MinuteCredit::create([
                'user_id' => $user->id,
                'additional_minute_plan_id' => $plan?->id,
                'plan_name' => $plan?->name ?? '500 Minutes Plan',
                'amount' => (float) ($plan?->price ?? fake()->randomFloat(2, 1, 5)),
                'minutes' => (float) ($plan?->daily_call_minutes ?? fake()->randomFloat(2, 100, 600)),
                'due_date' => $dueDate,
                'status' => $status->value,
                'repaid_at' => $status === PayLaterStatus::REPAID ? now()->subDays(fake()->numberBetween(0, 5)) : null,
                'created_at' => $appliedAt,
                'updated_at' => $appliedAt,
            ]);
        };

        // $testUser: one overdue ACTIVE, one REPAID, one DEFAULTED, plus a healthy ACTIVE.
        $make($testUser, PayLaterStatus::ACTIVE, overdue: true);
        $make($testUser, PayLaterStatus::ACTIVE);
        $make($testUser, PayLaterStatus::REPAID);
        $make($testUser, PayLaterStatus::DEFAULTED);

        $others = User::query()->whereKeyNot($testUser->id)->inRandomOrder()->get();

        for ($i = 0; $i < 12; $i++) {
            $make($others->random(), fake()->randomElement(PayLaterStatus::cases()));
        }
    }

    /**
     * Spread ad shares across the wider user base so the admin Ads / Ad-Shares pages are
     * populated. seedAds() already created the ads + a couple of shares for $testUser.
     */
    private function seedAdShareSpread(User $testUser): void
    {
        $ads = Ad::all();

        if ($ads->isEmpty()) {
            return;
        }

        $sharers = User::query()
            ->whereKeyNot($testUser->id)
            ->inRandomOrder()
            ->take(12)
            ->get();

        foreach ($sharers as $user) {
            // 1–3 distinct ads per user, never duplicating a (user, ad, version) pair.
            $picks = $ads->shuffle()->take(fake()->numberBetween(1, 3));

            foreach ($picks as $ad) {
                $exists = AdShare::query()
                    ->where('user_id', $user->id)
                    ->where('ad_id', $ad->id)
                    ->where('ad_version', $ad->version)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $ad->increment('shares');

                AdShare::create([
                    'user_id' => $user->id,
                    'ad_id' => $ad->id,
                    'ad_version' => $ad->version,
                    'amount' => (float) $ad->reward,
                    'shared_at' => now()->subDays(fake()->numberBetween(0, 30)),
                ]);
            }
        }
    }

    /**
     * A handful of announcements covering every type, mixed active flags, and live /
     * scheduled / expired windows for the admin Announcements page + the user banner.
     */
    private function seedAnnouncements(): void
    {
        $announcements = [
            [
                'type' => AnnouncementType::INFO,
                'title' => 'Scheduled maintenance this weekend',
                'body' => 'The platform will undergo brief maintenance on Sunday between 1am and 3am. Withdrawals may be delayed.',
                'is_active' => true,
                'starts_at' => null, // live now (no window)
                'ends_at' => null,
            ],
            [
                'type' => AnnouncementType::SUCCESS,
                'title' => 'New referral bonus rates are live',
                'body' => 'We have increased direct and indirect Connect Bonuses. Invite more friends and earn more!',
                'is_active' => true,
                'starts_at' => now()->subDays(2),
                'ends_at' => now()->addDays(5), // currently live within window
            ],
            [
                'type' => AnnouncementType::WARNING,
                'title' => 'Upcoming Pay Later policy update',
                'body' => 'Starting next month, defaulting on a Pay Later credit will pause new minute purchases until repaid.',
                'is_active' => true,
                'starts_at' => now()->addDays(7),
                'ends_at' => now()->addDays(21), // scheduled (not yet live)
            ],
            [
                'type' => AnnouncementType::DANGER,
                'title' => 'Beware of phishing attempts',
                'body' => 'We will never ask for your transaction PIN. Report any suspicious messages to support immediately.',
                'is_active' => false,
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->subDays(10), // expired + inactive
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::create([
                'type' => $announcement['type']->value,
                'title' => $announcement['title'],
                'body' => $announcement['body'],
                'is_active' => $announcement['is_active'],
                'starts_at' => $announcement['starts_at'],
                'ends_at' => $announcement['ends_at'],
            ]);
        }
    }

    /**
     * Give $testUser an active debit card (PIN is hashed via the model cast).
     */
    private function seedDebitCard(User $testUser): void
    {
        if ($testUser->debitCard()->exists()) {
            return;
        }

        DebitCard::create([
            'user_id' => $testUser->id,
            'brand' => 'Verve',
            'card_number' => DebitCard::generateNumber(),
            'cvv' => DebitCard::generateCvv(),
            'expiry_month' => fake()->numberBetween(1, 12),
            'expiry_year' => fake()->numberBetween(2027, 2030),
            'card_pin' => '1234', // hashed by the model's 'card_pin' => 'hashed' cast
            'status' => 'active',
        ]);
    }

    /**
     * Sample sponsor numbers (demo contacts users call to earn) + their TTS voice notes. Definitional
     * Cloud PLANS live in CloudSeeder; these demo numbers are test data, so they live here.
     */
    private function seedSponsorNumbers(): void
    {
        // [name, brand, earning_per_call] — every active sponsor is callable by every Cloud user.
        $numbers = [
            ['OPay Credit Alert', 'OPay', 0.15],
            ['Jumia Weekend Deal', 'Jumia', 0.11],
            ['AXA Mansard Health Plan', 'AXA Mansard', 0.12],
            ['PalmCredit Fast Cash', 'PalmCredit', 0.15],
            ['Cowrywise Savings Nudge', 'Cowrywise', 0.22],
            ['Kuda Spend Report', 'Kuda', 0.20],
            ['BetKing Bonus Drop', 'BetKing', 0.25],
            ['Binance Crypto Alert', 'Binance', 0.45],
            ['Risevest Dollar Portfolio', 'Risevest', 0.42],
            ['Moniepoint Merchant Offer', 'Moniepoint', 0.38],
        ];

        $callers = ['Adaeze', 'Chidi', 'Amaka', 'Bola', 'Emeka', 'Ngozi', 'Tunde', 'Chinyere', 'Yusuf', 'Blessing', 'Ifeanyi', 'Amara', 'Biodun'];
        $femaleCallers = ['Adaeze', 'Amaka', 'Bola', 'Ngozi', 'Chinyere', 'Blessing', 'Amara'];

        // Common device TTS voices, gendered to fit each caller; falls back to the browser default if not installed.
        $femaleVoices = ['Samantha', 'Karen', 'Moira', 'Microsoft Zira', 'Google UK English Female'];
        $maleVoices = ['Alex', 'Daniel', 'Microsoft David', 'Microsoft Mark', 'Google UK English Male'];

        foreach ($numbers as $i => [$name, $brand, $earning]) {
            $caller = $callers[$i % count($callers)];
            $voicePool = in_array($caller, $femaleCallers, true) ? $femaleVoices : $maleVoices;
            $voice = $voicePool[$i % count($voicePool)];

            $number = CloudNumber::updateOrCreate(['name' => $name], [
                'brand' => $brand,
                'caller_name' => $caller,
                'line_number' => '0700'.str_pad((string) (1000 + $i), 7, '0', STR_PAD_LEFT),
                'popup_message' => "{$name} is live! Claim your exclusive {$brand} offer before it ends.",
                'claim_url' => 'https://nextelconnect.example/sponsors/'.Str::slug($brand),
                'earning_per_call' => $earning,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);

            // TTS sponsor messages — only ONE note is active at a time (the active one plays on the call).
            $number->notes()->delete();
            $number->notes()->createMany([
                ['title' => 'Intro', 'message' => "Hello! This is {$brand} calling through Nextel Cloud. Thanks for picking up — we have something exciting for you today.", 'voice' => $voice, 'sort_order' => 1, 'is_active' => true],
                ['title' => 'Offer', 'message' => "{$name}. Act now to claim your reward — visit {$brand} to get started. Thanks for your time, and have a great day!", 'voice' => $voice, 'sort_order' => 2, 'is_active' => false],
            ]);
        }
    }

    /**
     * Nextel Cloud for the "everything" user: redeemed storage packs, saved sponsor contacts, a full
     * week of call history and a threaded inbox. The sponsored fan-out reaches every account holding
     * a Cloud line, so the Ghana (SMS-only) user is given its line here too and shares the threads.
     */
    private function seedCloud(User $testUser): void
    {
        $lifetimePlan = CloudStoragePlan::query()->where('duration_days', 0)->orderByDesc('extra_contacts')->first();

        if (! $lifetimePlan) {
            return;
        }

        $lines = app(CloudLineService::class);
        $lines->ensureLine($testUser->refresh());

        $smsUser = User::query()->where('email', 'foreign@example.com')->first();

        if ($smsUser) {
            $lines->ensureLine($smsUser);
        }

        $this->seedCloudPacks($testUser, $lifetimePlan);

        $numbers = CloudNumber::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($numbers->isEmpty()) {
            return;
        }

        $saved = $numbers->take(8)->values();
        $testUser->cloudNumbers()->sync($saved->pluck('id'));

        $this->seedCloudCalls($testUser, $saved);
        $this->seedCloudInbox($testUser, $smsUser, $saved);

        // Promoter + shareholder distributions on two saved contacts → claimable (pull-model) bonuses
        // for test@ (each is a single row; savers pull them when they open Cloud).
        $distributions = app(CloudDistributionService::class);
        $distributions->distribute($saved[0], CloudDistribution::TYPE_PROMOTER, 0.75, 'Weekly promoter payout');
        $distributions->distribute($saved[1], CloudDistribution::TYPE_SHAREHOLDER, 1.20, 'Q3 shareholder distribution');
    }

    /**
     * Redeemed contact-storage packs: one lifetime, one expiring shortly, and one already lapsed —
     * so the "unexpired only" slot maths visibly drops the third from the contact limit.
     */
    private function seedCloudPacks(User $testUser, CloudStoragePlan $lifetimePlan): void
    {
        $expiring = CloudStoragePlan::query()->where('duration_days', '>', 0)->orderBy('duration_days')->first();
        $lapsed = CloudStoragePlan::query()->where('duration_days', '>', 0)->orderByDesc('duration_days')->first();

        // One pack row per plan, so a single time-limited plan can't fill both slots.
        if ($lapsed?->id === $expiring?->id) {
            $lapsed = null;
        }

        // [plan, expires_at, redeemed_at] — a null expiry never lapses.
        $packs = [
            [$lifetimePlan, null, now()->subDays(46)],
            [$expiring, now()->addDays(12), now()->subDays(max(1, (int) $expiring?->duration_days - 12))],
            [$lapsed, now()->subDays(5), now()->subDays((int) $lapsed?->duration_days + 5)],
        ];

        foreach ($packs as [$plan, $expiresAt, $redeemedAt]) {
            if (! $plan) {
                continue;
            }

            CloudStorageTopUp::updateOrCreate(
                ['user_id' => $testUser->id, 'cloud_storage_plan_id' => $plan->id],
                [
                    'extra_contacts' => (int) $plan->extra_contacts,
                    'extra_sms' => (int) $plan->extra_sms,
                    'expires_at' => $expiresAt,
                    'created_at' => $redeemedAt,
                    'updated_at' => $redeemedAt,
                ],
            );
        }
    }

    /**
     * A week of call history with matching CLOUD_EARNING rows, so no day of the sparkline is empty.
     * Each day draws distinct contacts (a contact only pays once a day) and today stays light so the
     * daily/hourly caps still read as having room.
     *
     * @param  Collection<int, CloudNumber>  $saved
     */
    private function seedCloudCalls(User $testUser, Collection $saved): void
    {
        $minutesIntoToday = (int) today()->diffInMinutes(now());

        foreach (range(6, 0) as $daysAgo) {
            $isToday = $daysAgo === 0;

            foreach ($saved->shuffle()->take($isToday ? 2 : fake()->numberBetween(2, 4)) as $number) {
                $completed = $isToday || fake()->boolean(80);
                $earned = $completed ? (float) $number->earning_per_call : 0.0;
                $duration = $completed ? fake()->numberBetween(12, 45) : fake()->numberBetween(1, 6);

                $at = $isToday
                    ? today()->addMinutes(fake()->numberBetween(0, max(1, $minutesIntoToday - 5)))
                    : now()->subDays($daysAgo)->setTime(fake()->numberBetween(8, 21), fake()->numberBetween(0, 59));

                $call = CloudCall::create([
                    'user_id' => $testUser->id,
                    'cloud_number_id' => $number->id,
                    'number_name' => $number->name,
                    'direction' => 'outgoing',
                    'status' => $completed ? CloudCallStatus::COMPLETED : CloudCallStatus::DECLINED,
                    'earned' => $earned,
                    'duration_seconds' => $duration,
                    'started_at' => $at,
                    'ended_at' => $at->copy()->addSeconds($duration),
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                if ($earned <= 0) {
                    continue;
                }

                $testUser->increment('cloud_call_balance', $earned);
                $testUser->increment('lifetime_earnings', $earned);

                Transaction::create([
                    'user_id' => $testUser->id,
                    'type' => TransactionType::CLOUD_EARNING,
                    'direction' => 'credit',
                    'amount' => $earned,
                    'wallet' => 'cloud_call',
                    'status' => 'completed',
                    'reference' => 'CLD-'.strtoupper(Str::random(10)),
                    'description' => 'Cloud call · '.$number->name,
                    'meta' => ['cloud_call_id' => $call->id, 'number' => $number->name],
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }
    }

    /**
     * The inbox as conversations: a handful of sponsor brands each send a short run of messages, so
     * the threaded view has real back-and-forth rather than one orphan message per sender. Nextel's
     * own system notices form their own thread.
     *
     * @param  Collection<int, CloudNumber>  $saved
     */
    private function seedCloudInbox(User $testUser, ?User $smsUser, Collection $saved): void
    {
        $inbox = app(CloudInboxService::class);
        $senders = $saved->take(5)->values();

        // One entry per sender = one conversation; messages run oldest → newest within it.
        $threads = [
            [
                ['Your pre-approved offer is waiting.', 'You have a pre-approved offer sitting on your Nextel line — no paperwork, funds in minutes.'],
                ['We are still holding your limit.', 'Your reserved limit stays open for another 48 hours. Read this message to keep it live.'],
                ['Last reminder on your offer.', 'Final reminder — read this message to lock in your rate before it resets tonight.'],
            ],
            [
                ['A data bundle has been reserved for you.', 'Enjoy a discounted bundle reserved just for your line. Redeem it before it expires.'],
                ['Your reserved bundle expires tomorrow.', 'One day left on the bundle we set aside for you. Read to keep it on your line.'],
            ],
            [
                ['Get 5% cashback on your next top-up.', 'Top up any network this week and earn 5% straight back into your wallet.'],
                ['Your cashback is now 8% this weekend.', 'We raised your cashback rate for the weekend only. Read the details to opt in.'],
                ['Cashback weekend ends tonight.', 'Last few hours to top up at the boosted rate. Read this message to claim your reward.'],
            ],
            [
                ['New high-yield savings plan for members.', 'Grow idle cash at up to 18% p.a. Read to see how other members are saving smarter.'],
                ['Your savings projection is ready.', 'We modelled what your balance could earn over 12 months. Read to see the numbers.'],
                ['Rates change on Monday.', 'Lock in the current rate before Monday. Read this message to secure your position.'],
                ['You are on the priority list.', 'A slot opened on the priority tier and your line qualifies. Read to accept it.'],
            ],
            [
                ['Flash sale — 40% off today only.', 'Stock up before midnight. Read this message to unlock your member-only discount.'],
                ['Your cart is still waiting.', 'The items you looked at are back in stock at the sale price. Read to reserve them.'],
                ['Sale closes in two hours.', 'Final call on the flash sale. Read this message to claim the member price.'],
            ],
        ];

        // Hours since each conversation's newest message — spreads the thread list believably.
        $recency = [110, 63, 31, 11, 3];

        foreach ($threads as $index => $messages) {
            $sender = $senders[$index % $senders->count()];
            $newest = $recency[$index] ?? 6;
            $last = count($messages) - 1;

            foreach ($messages as $position => [$subject, $body]) {
                $sentAt = now()->subHours($newest + ($last - $position) * fake()->numberBetween(14, 30));
                $message = $inbox->sendSponsored($sender, $subject, $body, round(0.04 + $index * 0.02 + $position * 0.01, 2));

                // The fan-out stamps every copy with now() — backdate the broadcast and its copies together.
                $message->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt])->save();
                CloudInboxMessage::query()
                    ->where('cloud_message_id', $message->id)
                    ->update(['created_at' => $sentAt, 'updated_at' => $sentAt]);
            }
        }

        $testUser->refresh();

        $this->seedCloudNotices($testUser, [
            [96, 'Welcome to Nextel Cloud', "Your virtual line {$testUser->cloud_line_formatted} is active. Read sponsored messages and pick up sponsored calls to start earning."],
            [50, 'Withdrawal successful', 'Your withdrawal of '.money(5).' has been processed to your saved bank account.'],
            [20, 'Cloud call reward booked', 'You earned '.money(0.2).' from a sponsored call. Keep picking up calls to hit your daily cap.'],
            [4, 'A storage pack expires soon', 'One of your contact storage packs expires in 12 days. Redeem another token any time to keep the slots.'],
        ]);

        $this->seedCloudReadState($testUser, claims: 2);

        if ($smsUser === null) {
            return;
        }

        $smsUser->refresh();

        $this->seedCloudNotices($smsUser, [
            [72, 'Welcome to Nextel Cloud', "Your virtual line {$smsUser->cloud_line_formatted} is active. Read sponsored messages to start earning."],
            [26, 'Sponsored messages are earning', 'Every sponsored message you read pays into your Cloud wallet. Read them all to top it up.'],
            [6, 'Unlock Cloud calling', 'Upgrade to the Royal eSIM to save contacts and earn from sponsored calls as well as messages.'],
        ]);

        $this->seedCloudReadState($smsUser, claims: 1);
    }

    /**
     * Nextel's system thread for one user.
     *
     * @param  list<array{0: int, 1: string, 2: string}>  $notices  [hoursAgo, subject, body]
     */
    private function seedCloudNotices(User $user, array $notices): void
    {
        $inbox = app(CloudInboxService::class);

        foreach ($notices as [$hoursAgo, $subject, $body]) {
            $sentAt = now()->subHours($hoursAgo);

            $inbox->notify($user, $subject, $body)?->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt])->save();
        }
    }

    /**
     * Age the inbox: older messages read, the recent tail mostly unread (so the badge has a count),
     * and a couple of sponsored messages claimed so the claimed state + CLM- ledger rows show up.
     */
    private function seedCloudReadState(User $user, int $claims): void
    {
        $messages = $user->cloudInbox()->reorder('created_at')->get();
        $unreadFrom = (int) floor($messages->count() * 0.65);

        foreach ($messages as $index => $message) {
            $readAt = $message->created_at->copy()->addMinutes(fake()->numberBetween(2, 240));

            $message->forceFill([
                'read_at' => $index < $unreadFrom || fake()->boolean(35)
                    ? ($readAt->isFuture() ? now() : $readAt)
                    : null,
            ])->save();
        }

        $inbox = app(CloudInboxService::class);

        $user->cloudInbox()->unclaimed()->reorder()->inRandomOrder()->take($claims)->get()
            ->each(fn (CloudInboxMessage $message) => $inbox->claim($user->refresh(), $message));
    }

    /**
     * Bill payments (VTU): a lived-in spread of airtime/data/TV/electricity/education orders across
     * every status the receipt renders (delivered, pending, failed, reversed), including a prepaid
     * electricity token + a WAEC pin, each backed by a matching VTU_PURCHASE ledger row. This fills
     * the Bill Payments history, the admin VTU orders list, and the receipt screen with real data.
     * Delivered orders sit on other wallets so the tester's bills VTU allowance stays open.
     */
    private function seedBillPayments(User $testUser): void
    {
        $currency = app(CurrencyService::class);
        $name = strtoupper($testUser->full_name);
        $phone = '080'.fake()->numerify('########');

        // [service, service_id, ngn, wallet, status, daysAgo, extras]
        $orders = [
            [VtuOrder::SERVICE_AIRTIME, 'mtn', 100, 'reward', VtuStatus::DELIVERED, 15, ['phone' => $phone]],
            [VtuOrder::SERVICE_DATA, 'mtn-data', 1000, 'bills', VtuStatus::DELIVERED, 12, ['phone' => $phone, 'variation_code' => 'mtn-1500', 'plan_label' => '1.5GB — 30 days']],
            [VtuOrder::SERVICE_TV, 'dstv', 4400, 'ads_revenue', VtuStatus::DELIVERED, 9, ['billers_code' => '1023456789', 'variation_code' => 'dstv-compact', 'plan_label' => 'DStv Compact', 'customer_name' => $name]],
            [VtuOrder::SERVICE_ELECTRICITY, 'ikeja-electric', 3000, 'referral_bonus', VtuStatus::DELIVERED, 6, ['billers_code' => '45010421234', 'variation_code' => 'prepaid', 'customer_name' => $name, 'token' => '0123-4567-8901-2345-6789']],
            [VtuOrder::SERVICE_EDUCATION, 'waec', 900, 'reward', VtuStatus::DELIVERED, 4, ['phone' => $phone, 'variation_code' => 'waecdirect', 'plan_label' => 'WAEC Result Checker PIN', 'token' => 'WRC-8842-1097-5521']],
            [VtuOrder::SERVICE_AIRTIME, 'airtel', 50, 'bills', VtuStatus::PENDING, 1, ['phone' => $phone]],
            [VtuOrder::SERVICE_DATA, 'glo-data', 500, 'bills', VtuStatus::FAILED, 3, ['phone' => $phone, 'variation_code' => 'glo-1000', 'plan_label' => 'Glo 1GB — 30 days']],
            [VtuOrder::SERVICE_AIRTIME, '9mobile', 80, 'bills', VtuStatus::REVERSED, 2, ['phone' => $phone]],
        ];

        foreach ($orders as [$service, $serviceId, $ngn, $wallet, $status, $daysAgo, $extras]) {
            $ngn = (float) $ngn;
            $usd = round((float) $currency->toUsd($ngn, 'NG'), 4) ?: round($ngn / 1600, 4);
            $when = now()->subDays($daysAgo)->subMinutes(fake()->numberBetween(5, 720));
            $requestId = $when->copy()->timezone('Africa/Lagos')->format('YmdHi').strtolower(Str::random(12));
            $delivered = $status === VtuStatus::DELIVERED;
            $token = $delivered ? ($extras['token'] ?? null) : null;

            $description = match ($service) {
                VtuOrder::SERVICE_AIRTIME => 'Airtime purchase ('.strtoupper($serviceId).') '.($extras['phone'] ?? ''),
                VtuOrder::SERVICE_DATA => 'Data purchase ('.strtoupper($serviceId).') '.($extras['phone'] ?? ''),
                VtuOrder::SERVICE_TV => 'TV subscription ('.strtoupper($serviceId).')',
                VtuOrder::SERVICE_ELECTRICITY => 'Electricity ('.strtoupper($serviceId).')',
                default => 'Education ('.strtoupper($serviceId).')',
            };

            $transaction = Transaction::create([
                'user_id' => $testUser->id,
                'type' => TransactionType::VTU_PURCHASE,
                'direction' => 'debit',
                'amount' => $usd,
                'fee' => 0,
                'wallet' => $wallet,
                'status' => $delivered ? 'completed' : ($status->isPending() ? 'pending' : 'failed'),
                'reference' => $requestId,
                'description' => trim($description),
                'meta' => array_filter([
                    'service' => $service,
                    'service_id' => $serviceId,
                    'amount_ngn' => $ngn,
                    'recipient' => $extras['billers_code'] ?? $extras['phone'] ?? null,
                    'network' => $this->vtuNetworkName($service, $serviceId),
                    'wallet_label' => User::WALLET_LABELS[$wallet] ?? null,
                    'token' => $token,
                ], fn ($v) => $v !== null),
                'created_at' => $when,
                'updated_at' => $when,
            ]);

            VtuOrder::create([
                'user_id' => $testUser->id,
                'service' => $service,
                'service_id' => $serviceId,
                'wallet' => $wallet,
                'variation_code' => $extras['variation_code'] ?? null,
                'billers_code' => $extras['billers_code'] ?? null,
                'phone' => $extras['phone'] ?? null,
                'amount_ngn' => $ngn,
                'amount_usd' => $usd,
                'fee' => 0,
                'request_id' => $requestId,
                'status' => $status,
                'external_id' => $status->isPending() ? null : (string) fake()->numerify('##############'),
                'token' => $token,
                'refunded' => $status->isRefunded(),
                'transaction_id' => $transaction->id,
                'meta' => array_filter([
                    'provider_label' => VtuCatalog::provider($service, $serviceId)['label'] ?? $serviceId,
                    'plan_label' => $extras['plan_label'] ?? null,
                    'customer_name' => $extras['customer_name'] ?? null,
                ], fn ($v) => $v !== null),
                'created_at' => $when,
                'updated_at' => $when,
            ]);
        }
    }

    /**
     * Carrier display name for airtime/data serviceIDs (mirrors VtuPurchaseService::networkName) so a
     * seeded ledger row renders the network logo like a real purchase. Null for TV/electricity/education.
     */
    private function vtuNetworkName(string $service, string $serviceId): ?string
    {
        if (! in_array($service, [VtuOrder::SERVICE_AIRTIME, VtuOrder::SERVICE_DATA], true)) {
            return null;
        }

        return match (str_replace('-data', '', strtolower($serviceId))) {
            'mtn' => 'MTN',
            'glo' => 'Glo',
            'airtel' => 'Airtel',
            '9mobile', 'etisalat' => '9mobile',
            default => null,
        };
    }

    /**
     * Extend $testUser's network so SOME of their direct referrals each have 1–3 referees
     * of their own (the "indirect" sub-tab + Reports indirect breakdown), and add a few
     * Direct/indirect REFERRAL_BONUS transactions so the Reports bonus breakdown has data.
     */
    private function seedIndirectReferrals(User $testUser): void
    {
        /** @var Collection<int, User> $directs */
        $directs = $testUser->referrals()->get();

        if ($directs->isEmpty()) {
            return;
        }

        // Fresh users to act as second-level (indirect) referees.
        $pool = User::factory()
            ->count(8)
            ->create()
            ->each(fn (User $u) => $u->update([
                'lifetime_earnings' => fake()->randomFloat(2, 0, 80),
            ]));

        $cursor = 0;

        foreach ($directs->take(4) as $direct) {
            $count = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $count && $cursor < $pool->count(); $i++) {
                $referee = $pool[$cursor++];

                $referee->update([
                    'referrer_id' => $direct->id,
                    'created_at' => now()->subDays(fake()->numberBetween(1, 25)),
                ]);

                Referral::create([
                    'referrer_id' => $direct->id,
                    'referee_id' => $referee->id,
                    'status' => $this->referralStatusFor($referee),
                ]);
            }
        }

        // Direct/indirect Connect Bonus transactions for $testUser. The Reports breakdown keys off
        // meta.level, so [description, level] are seeded as a pair rather than sniffed from the copy.
        $bonuses = [
            ['Direct Connect Bonus from james252', 'direct', 1.0, now()->subDays(18)],
            ['Direct Connect Bonus from ngozi_a', 'direct', 1.0, now()->subDays(11)],
            ['Direct Connect Bonus from tunde99', 'direct', 1.5, now()->subDays(6)],
            ['Indirect Connect Bonus from amaka_o', 'indirect', 0.3, now()->subDays(14)],
            ['Indirect Connect Bonus from chidi_k', 'indirect', 0.25, now()->subDays(5)],
        ];

        foreach ($bonuses as [$description, $level, $amount, $when]) {
            Transaction::create([
                'user_id' => $testUser->id,
                'type' => TransactionType::REFERRAL_BONUS,
                'direction' => 'credit',
                'amount' => $amount,
                'wallet' => 'referral_bonus',
                'status' => 'completed',
                'reference' => 'REF-'.strtoupper(Str::random(10)),
                'description' => $description,
                'meta' => ['level' => $level],
                'created_at' => $when,
            ]);
        }
    }

    /**
     * Seed a few token batches (activation, extra minutes, cloud) for the test agent —
     * with some codes redeemed — so the Vendor Dashboard demonstrates real data.
     */
    private function seedVendorBatches(User $agent, Package $package, ?AdditionalMinutePlan $plan): void
    {
        $cloudStoragePlan = CloudStoragePlan::query()->active()->ordered()->first();

        $batches = [
            ['type' => CouponType::ACTIVATION, 'package_id' => $package->id, 'plan_id' => null, 'cloud_storage_plan_id' => null, 'price' => (float) $package->price, 'qty' => 10, 'used' => 3],
            ['type' => CouponType::EXTRA_MINUTES, 'package_id' => null, 'plan_id' => $plan?->id, 'cloud_storage_plan_id' => null, 'price' => (float) ($plan->price ?? 3000), 'qty' => 8, 'used' => 2],
            ['type' => CouponType::CLOUD, 'package_id' => null, 'plan_id' => null, 'cloud_storage_plan_id' => $cloudStoragePlan?->id, 'price' => (float) ($cloudStoragePlan->token_price ?? 5), 'qty' => 6, 'used' => 2],
        ];

        $region = Coupon::regionFor($agent);

        foreach ($batches as $spec) {
            if ($spec['type'] === CouponType::EXTRA_MINUTES && ! $plan) {
                continue;
            }

            if ($spec['type'] === CouponType::CLOUD && ! $cloudStoragePlan) {
                continue;
            }

            $batch = CouponBatch::create([
                'agent_id' => $agent->id,
                'package_id' => $spec['package_id'],
                'additional_minute_plan_id' => $spec['plan_id'],
                'cloud_storage_plan_id' => $spec['cloud_storage_plan_id'],
                'type' => $spec['type']->value,
                'region' => $region,
                'quantity' => $spec['qty'],
            ]);

            for ($i = 0; $i < $spec['qty']; $i++) {
                Coupon::create([
                    'type' => $spec['type']->value,
                    'agent_id' => $agent->id,
                    'package_id' => $spec['package_id'],
                    'additional_minute_plan_id' => $spec['plan_id'],
                    'cloud_storage_plan_id' => $spec['cloud_storage_plan_id'],
                    'batch_id' => $batch->id,
                    'region' => $region,
                    'price' => $spec['price'],
                ]);
            }

            $batch->coupons()->take($spec['used'])->get()->each(fn (Coupon $coupon) => $coupon->update([
                'used_at' => now()->subDays(fake()->numberBetween(0, 10)),
                'user_id' => User::query()->where('is_agent', false)->inRandomOrder()->value('id'),
            ]));
        }
    }

    /**
     * Generate ~N call records across the last 30 days and set the earning wallets
     * from them, so the dashboard charts/minutes/balance reflect real data.
     */
    private function seedCallActivity(User $user, int $count = 70): void
    {
        $callTypes = CallType::all();

        if ($callTypes->isEmpty()) {
            return;
        }

        $claimedCall = 0.0;
        $networks = array_keys(AllocatedNumber::NETWORKS);

        // Historical claims = the user's banked call earnings over the last 30 days
        // (today's claimable calls come live from the shared pool).
        for ($i = 0; $i < $count; $i++) {
            $type = $callTypes->random();
            $minutes = fake()->randomFloat(2, 0.5, 18);
            $rate = (float) $type->rate_per_minute ?: (float) fake()->randomElement([20, 35, 50, 75]);
            $earnings = round($minutes * $rate, 6);
            $pct = (float) $type->claimable_percentage ?: 50.0;
            $amount = round($earnings * $pct / 100, 6);

            CallClaim::create([
                'user_id' => $user->id,
                'call_record_id' => null,
                'call_type_id' => $type->id,
                'duration_minutes' => $minutes,
                'amount' => $amount,
                'network' => fake()->randomElement($networks),
                'claimed_at' => fake()->dateTimeBetween('-29 days', 'now'),
            ]);

            $claimedCall += $amount;
        }

        $lifetime = $claimedCall;

        $referral = (float) $user->referral_bonus_balance + fake()->randomFloat(2, 5, 40);

        // Ads revenue is built from real ad shares in seedAds().
        $user->forceFill([
            'shareholder_balance' => round($claimedCall, 6),
            'ads_revenue_balance' => 0,
            'referral_bonus_balance' => round($referral, 6),
            'lifetime_earnings' => round($lifetime + $referral, 6),
        ])->save();
    }

    /**
     * Build the transaction feed (= "activities") — call earnings carry the number's
     * network logo, plus the money events (withdrawals, ad revenue, referral, etc.).
     */
    private function seedTransactions(User $user): void
    {
        $ref = fn (): string => 'TXN-'.strtoupper(Str::random(10));

        $user->callClaims()->latest('claimed_at')->take(22)->get()
            ->each(fn (CallClaim $claim) => Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::CALL_EARNING,
                'direction' => 'credit',
                'amount' => (float) $claim->amount,
                'wallet' => 'shareholder',
                'status' => 'completed',
                'reference' => $ref(),
                'description' => 'Call Earning',
                'meta' => ['network' => $claim->network],
                'created_at' => $claim->claimed_at,
            ]));

        $events = [
            [TransactionType::PACKAGE_ACTIVATION, 10.0, 'completed', 'Basic Package activation', now()->subDays(28), null],
            [TransactionType::REFERRAL_BONUS, 1.0, 'completed', 'Direct referral bonus', now()->subDays(9), 'referral_bonus'],
            [TransactionType::REFERRAL_BONUS, 0.3, 'completed', 'Indirect Connect Bonus from chidi_k', now()->subDays(3), 'referral_bonus'],
            [TransactionType::MINUTES_TOPUP, fake()->randomFloat(2, 2, 6), 'completed', 'Additional minutes top-up', now()->subDays(7), null],
            // WITHDRAWAL ledger rows are seeded as COMPLETED debits linked to real Withdrawal records in
            // seedWithdrawals() — not here — so references resolve and nothing lingers in pending.
        ];

        foreach ($events as [$type, $amount, $status, $desc, $when, $wallet]) {
            Transaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'direction' => $type->isCredit() ? 'credit' : 'debit',
                'amount' => $amount,
                'fee' => 0,
                'wallet' => $wallet,
                'status' => $status,
                'reference' => $ref(),
                'description' => $desc,
                'meta' => null,
                'created_at' => $when,
            ]);
        }
    }

    /**
     * Seed daily Target Rewards across all states: claimed history, a missed day or two, one
     * unclaimed reward to collect, and today's in-progress target (~60%) fed by today's
     * referral-bonus transactions.
     */
    private function seedRewardTargets(User $user): void
    {
        // Claimed history — banked into the Rewards wallet.
        $claimed = collect([8, 5, 2])->map(fn (int $daysAgo) => RewardTarget::factory()->claimed()->for($user)->create([
            'date' => now()->subDays($daysAgo)->toDateString(),
            'created_at' => now()->subDays($daysAgo)->setTime(8, 0),
        ]));

        // Missed days.
        foreach ([7, 3] as $daysAgo) {
            RewardTarget::factory()->missed()->for($user)->create([
                'date' => now()->subDays($daysAgo)->toDateString(),
                'created_at' => now()->subDays($daysAgo)->setTime(8, 0),
            ]);
        }

        // An unclaimed reward from yesterday — ready to claim.
        RewardTarget::factory()->achieved()->for($user)->create([
            'date' => now()->subDay()->toDateString(),
            'created_at' => now()->subDay()->setTime(8, 0),
        ]);

        // Today: an active $50 target with ~60% progress from today's referral bonuses.
        $setAt = now()->setTime(8, 0);
        RewardTarget::factory()->for($user)->create([
            'date' => today()->toDateString(),
            'target_amount' => 50,
            'created_at' => $setAt,
        ]);

        foreach ([12, 8, 10] as $i => $amount) {
            Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::REFERRAL_BONUS,
                'direction' => 'credit',
                'amount' => $amount,
                'wallet' => 'referral_bonus',
                'status' => 'completed',
                'reference' => 'REF-'.strtoupper(Str::random(10)),
                'description' => 'Direct referral bonus',
                'meta' => ['level' => 'direct'],
                'created_at' => $setAt->copy()->addHours($i + 1),
            ]);
        }

        // Bank the already-claimed rewards into the Rewards wallet for the demo.
        $user->forceFill(['reward_balance' => round((float) $claimed->sum('reward_amount'), 2)])->save();
    }

    /**
     * Seed admin-managed ads, and have the test user already earn from a couple of them
     * (real ad_shares + AD_REVENUE transactions credited into the Ads wallet), leaving the
     * rest un-earned so the instant share→earn flow is demoable.
     */
    private function seedAds(User $user): void
    {
        $ads = Ad::factory()->count(5)->create();

        $ads->take(2)->each(function (Ad $ad) use ($user): void {
            $when = now()->subDays(random_int(1, 6));
            $amount = (float) $ad->reward;

            $ad->increment('shares');

            AdShare::create([
                'user_id' => $user->id,
                'ad_id' => $ad->id,
                'ad_version' => $ad->version,
                'amount' => $amount,
                'shared_at' => $when,
            ]);

            $user->creditEarnings('ads_revenue_balance', $amount);

            Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::AD_REVENUE,
                'direction' => 'credit',
                'amount' => $amount,
                'wallet' => 'ads_revenue',
                'status' => 'completed',
                'reference' => 'ADV-'.strtoupper(Str::random(10)),
                'description' => 'Ad share — '.$ad->name,
                'meta' => ['ad_id' => $ad->id, 'ad_name' => $ad->name, 'version' => $ad->version],
                'created_at' => $when,
            ]);
        });
    }

    /**
     * Give the user a referral team — direct (referred by them) + indirect (referred by their referees).
     */
    private function seedReferralTeam(User $user): void
    {
        $pool = User::query()->where('is_agent', false)->whereKeyNot($user->id)->inRandomOrder()->take(8)->get();
        $directs = $pool->take(5);
        $indirects = $pool->slice(5);

        foreach ($directs as $d) {
            $d->update(['referrer_id' => $user->id, 'created_at' => now()->subDays(random_int(2, 40))]);
            Referral::create([
                'referrer_id' => $user->id,
                'referee_id' => $d->id,
                'status' => $this->referralStatusFor($d),
            ]);
        }

        foreach ($indirects as $ind) {
            $parent = $directs->random();
            $ind->update(['referrer_id' => $parent->id, 'created_at' => now()->subDays(random_int(1, 20))]);
            Referral::create([
                'referrer_id' => $parent->id,
                'referee_id' => $ind->id,
                'status' => $this->referralStatusFor($ind),
            ]);
        }
    }

    /**
     * A referral is ACTIVE only when its referee has activated a package — that purchase is
     * what triggers the bonus. Activate ~70% of still-package-less referees first so the demo
     * shows a realistic active/pending mix, then derive the status from package ownership.
     */
    private function referralStatusFor(User $referee): string
    {
        if (! $referee->package_id && fake()->boolean(70)) {
            $referee->update(['package_id' => Package::query()->inRandomOrder()->value('id')]);
        }

        return $referee->package_id
            ? ReferralStatus::ACTIVE->value
            : ReferralStatus::PENDING->value;
    }

    /**
     * Generate today's shared call pool (call_records) up-front so the admin Call Logs screen and
     * the user Earnings/Shareholder pages have data immediately — without waiting for the lazy
     * first-view generation.
     */
    private function seedCallPool(): void
    {
        app(CallLogService::class)->generatePool();
    }

    /**
     * A real, end-to-end Pay Later credit for $testUser: PayLaterService::apply() creates the
     * MinuteTopUp + its additional-source Call IDs + an ACTIVE credit. We backdate it ~20 days and
     * push the due date into the past (so it reads as Overdue — a default candidate), then TAG a
     * slice of the user's banked call earnings to this top-up. An admin can then "Mark defaulted"
     * and watch the clawback reverse exactly those additional-minute earnings (base stays untouched).
     */
    private function seedRealPayLater(User $testUser): void
    {
        $plan = AdditionalMinutePlan::query()->where('call_id_bonus', '>', 0)->orderBy('daily_call_minutes')->first()
            ?? AdditionalMinutePlan::query()->orderBy('daily_call_minutes')->first();

        if (! $plan) {
            return;
        }

        $credit = app(PayLaterService::class)->apply($testUser, $plan);
        $topUp = $credit->minuteTopUp;

        $takenAt = now()->subDays(20);

        $topUp?->forceFill(['created_at' => $takenAt, 'updated_at' => $takenAt])->save();
        $topUp?->allocatedNumbers()->update(['created_at' => $takenAt, 'updated_at' => $takenAt]);

        $credit->forceFill([
            'created_at' => $takenAt,
            'updated_at' => $takenAt,
            'due_date' => today()->subDays(3),       // overdue → Overdue pill + ready to default
            'status' => PayLaterStatus::ACTIVE->value,
        ])->save();

        // Mark ~15 of the user's banked claims as funded by THIS plan's additional minutes so the
        // forfeit clawback has exact, real data to reverse (the balance already includes them).
        if ($topUp) {
            $testUser->callClaims()
                ->whereNull('minute_top_up_id')
                ->latest('claimed_at')
                ->take(15)
                ->get()
                ->each(fn (CallClaim $claim) => $claim->update(['minute_top_up_id' => $topUp->id]));
        }
    }

    /**
     * Batch a couple of PROCESSING withdrawals onto a withdrawal payment sheet (marking them
     * exported) so the admin Payment Sheets screen has a downloadable sheet to show.
     */
    private function seedPaymentSheet(): void
    {
        $processing = Withdrawal::query()
            ->where('status', WithdrawalStatus::PROCESSING->value)
            ->whereNull('exported_at')
            ->take(5)
            ->get();

        if ($processing->isEmpty()) {
            return;
        }

        $when = now()->subDays(2);

        $sheet = WithdrawalPaymentSheet::create([
            'processor' => 'general',
            'sequence_number' => WithdrawalPaymentSheet::getNextSequenceNumber(),
            'total_amount' => round((float) $processing->sum('amount_paid'), 2),
            'withdrawal_ids' => $processing->pluck('id')->all(),
            'created_at' => $when,
            'updated_at' => $when,
        ]);

        Withdrawal::query()->whereIn('id', $sheet->withdrawal_ids)->update(['exported_at' => $when]);
    }

    /**
     * Spread daily Target Rewards (claimed / achieved / missed / active) across a handful of other
     * users so the admin Reward Targets page has volume beyond $testUser.
     */
    private function seedRewardTargetSpread(User $testUser): void
    {
        $users = User::query()
            ->whereKeyNot($testUser->id)
            ->where('is_agent', false)
            ->inRandomOrder()
            ->take(8)
            ->get();

        $states = ['claimed', 'achieved', 'missed', 'active'];

        foreach ($users as $i => $user) {
            $daysAgo = fake()->numberBetween(0, 12);
            $factory = RewardTarget::factory()->for($user);

            $factory = match ($states[$i % count($states)]) {
                'claimed' => $factory->claimed(),
                'achieved' => $factory->achieved(),
                'missed' => $factory->missed(),
                default => $factory,
            };

            $factory->create([
                'date' => now()->subDays($daysAgo)->toDateString(),
                'created_at' => now()->subDays($daysAgo)->setTime(8, 0),
            ]);
        }
    }

    /**
     * A suspended user and a withdrawals-disabled user, so the admin Users filters + the
     * per-user kill-switch both have something to demonstrate.
     */
    private function seedStatusVariety(): void
    {
        User::factory()->create([
            'first_name' => 'Suspended',
            'last_name' => 'User',
            'username' => 'suspendeduser',
            'email' => 'suspended@example.com',
            'status' => UserStatus::SUSPENDED->value,
            'package_id' => Package::query()->inRandomOrder()->value('id'),
            'lifetime_earnings' => fake()->randomFloat(2, 20, 150),
        ]);

        User::factory()->create([
            'first_name' => 'Blocked',
            'last_name' => 'Payouts',
            'username' => 'blockedpayouts',
            'email' => 'blocked@example.com',
            'withdrawals_disabled' => true,
            'package_id' => Package::query()->inRandomOrder()->value('id'),
            'shareholder_balance' => fake()->randomFloat(2, 30, 120),
            'lifetime_earnings' => fake()->randomFloat(2, 50, 200),
        ]);
    }
}
