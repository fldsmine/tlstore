<?php

namespace Tests\Unit;

use App\Application\Money\WithdrawalSchedule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * ERR-24: `withdrawal_days` carried two spellings (`['Monthly 28th']` on the
 * model, `['Monthly']` in the customer-facing features block) and the parser
 * only understood weekday names and `Monthly <n>`.
 */
class WithdrawalScheduleTest extends TestCase
{
    private CarbonImmutable $friday28;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-08-28 is a Friday and the 28th of the month.
        $this->friday28 = CarbonImmutable::parse('2026-08-28 10:00:00');
    }

    public function test_empty_or_missing_schedule_allows_every_day(): void
    {
        $this->assertTrue(WithdrawalSchedule::allows([], $this->friday28));
        $this->assertTrue(WithdrawalSchedule::allows(null, $this->friday28));
        $this->assertSame('any day', WithdrawalSchedule::describe([]));
    }

    public function test_monthly_day_of_month_is_matched(): void
    {
        $this->assertTrue(WithdrawalSchedule::allows(['Monthly 28th'], $this->friday28));
        $this->assertTrue(WithdrawalSchedule::allows(['monthly 28'], $this->friday28));
        $this->assertTrue(WithdrawalSchedule::allows(['Monthly 28th'], $this->friday28->addDay()->subDay()));

        $this->assertFalse(WithdrawalSchedule::allows(['Monthly 28th'], $this->friday28->addDay()));
    }

    public function test_bare_monthly_token_never_blocks_a_withdrawal(): void
    {
        $this->assertTrue(WithdrawalSchedule::allows(['Monthly'], $this->friday28));
        $this->assertTrue(WithdrawalSchedule::allows(['monthly'], $this->friday28->addDays(3)));
    }

    public function test_weekday_names_and_abbreviations_work(): void
    {
        $this->assertTrue(WithdrawalSchedule::allows(['Friday'], $this->friday28));
        $this->assertTrue(WithdrawalSchedule::allows(['fri'], $this->friday28));
        $this->assertFalse(WithdrawalSchedule::allows(['Wednesday'], $this->friday28));

        // 2026-09-02 is a Wednesday.
        $this->assertTrue(WithdrawalSchedule::allows(['Wednesday', 'Friday'], CarbonImmutable::parse('2026-09-02')));
    }

    public function test_daily_tokens_always_allow(): void
    {
        foreach (['daily', 'any', 'anytime', 'everyday'] as $token) {
            $this->assertTrue(WithdrawalSchedule::allows([$token], $this->friday28), $token);
        }
    }

    public function test_month_end_token_matches_the_last_day(): void
    {
        $this->assertTrue(WithdrawalSchedule::allows(['monthly last'], CarbonImmutable::parse('2026-08-31')));
        $this->assertFalse(WithdrawalSchedule::allows(['monthly last'], CarbonImmutable::parse('2026-08-30')));
    }

    public function test_unrecognised_tokens_fail_open_so_users_are_never_locked_out(): void
    {
        // A typo in plan data must not make a user's balance unreachable.
        $this->assertTrue(WithdrawalSchedule::allows(['whenever we feel like it'], $this->friday28));
    }

    public function test_mixed_known_and_unknown_tokens_still_block_on_a_mismatch(): void
    {
        $this->assertFalse(WithdrawalSchedule::allows(['Wednesday', 'whenever'], $this->friday28));
    }

    public function test_describe_renders_a_human_list(): void
    {
        $this->assertSame('Friday and Wednesday', WithdrawalSchedule::describe(['Friday', 'Wednesday']));
    }
}
