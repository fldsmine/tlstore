<?php

namespace App\Application\Telegram;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\WithdrawalRequest;

/**
 * Builds the messages the platform posts to the Telegram group/channel.
 *
 * Everything interpolated from the database is escaped, so a user whose name
 * contains `<` cannot break Telegram's HTML parser (which would reject the
 * whole message) or inject formatting.
 */
final class TelegramNotifier
{
    public function withdrawalRequest(WithdrawalRequest $withdrawal): string
    {
        $lines = [
            '🚨 <b>NEW WITHDRAWAL REQUEST</b>',
            '━━━━━━━━━━━━━━━━━━━━',
            '👤 <b>User:</b> '.$this->user($withdrawal),
            '💵 <b>Amount:</b> '.$this->amount($withdrawal),
        ];

        $method = $this->method($withdrawal);
        if ($method !== null) {
            $lines[] = '🏦 <b>Method:</b> '.$method;
        }

        $account = $this->account($withdrawal);
        if ($account !== null) {
            $lines[] = '🔢 <b>Account:</b> '.$account;
        }

        $lines[] = '👜 <b>Wallet:</b> '.TelegramHtml::escapeText($this->walletLabel($withdrawal));
        $lines[] = '🔖 <b>Reference:</b> <code>'.TelegramHtml::escapeText($this->reference($withdrawal)).'</code>';
        $lines[] = '🕒 <b>Requested:</b> '.TelegramHtml::escapeText(
            $withdrawal->requested_at?->format('d M Y, g:i A') ?? now()->format('d M Y, g:i A'),
        );
        $lines[] = '';
        $lines[] = '<i>Receipt attached. Review the request in the admin console before paying out.</i>';

        return implode("\n", $lines);
    }

    public function withdrawalDecision(WithdrawalRequest $withdrawal): string
    {
        $status = is_object($withdrawal->status) ? $withdrawal->status->value : (string) $withdrawal->status;
        $paid = in_array($status, ['paid', 'completed'], true);

        $lines = [
            $paid ? '✅ <b>WITHDRAWAL PAID</b>' : '❌ <b>WITHDRAWAL '.strtoupper(TelegramHtml::escapeText($status)).'</b>',
            '━━━━━━━━━━━━━━━━━━━━',
            '👤 <b>User:</b> '.$this->user($withdrawal),
            '💵 <b>Amount:</b> '.$this->amount($withdrawal),
            '🔖 <b>Reference:</b> <code>'.TelegramHtml::escapeText($this->reference($withdrawal)).'</code>',
            '📌 <b>Status:</b> '.TelegramHtml::escapeText(ucfirst($status)),
        ];

        if ($paid) {
            $lines[] = '🏦 <b>Payout reference:</b> '.TelegramHtml::escapeText(
                (string) ($withdrawal->provider_reference ?: 'not provided'),
            );
        } elseif (filled($withdrawal->failure_reason)) {
            $lines[] = '📝 <b>Reason:</b> '.TelegramHtml::escapeText((string) $withdrawal->failure_reason);
        }

        $lines[] = '🕒 <b>Updated:</b> '.TelegramHtml::escapeText(
            $withdrawal->processed_at?->format('d M Y, g:i A') ?? now()->format('d M Y, g:i A'),
        );

        return implode("\n", $lines);
    }

    public function testMessage(): string
    {
        return implode("\n", [
            '✅ <b>Telegram integration is working</b>',
            '━━━━━━━━━━━━━━━━━━━━',
            'This test message travelled from the TL Store admin console to this chat.',
            '🕒 '.TelegramHtml::escapeText(now()->format('d M Y, g:i A')),
            '🤖 <b>Bot:</b> '.TelegramHtml::escapeText((string) config('app.name', 'TLStore')),
        ]);
    }

    /** Admin announcements are sent exactly as composed (already sanitised). */
    public function announcement(string $html): string
    {
        return TelegramHtml::sanitize($html);
    }

    private function user(WithdrawalRequest $withdrawal): string
    {
        $name = trim((string) ($withdrawal->user?->full_name ?? '')) ?: 'Unknown user';
        $username = trim((string) ($withdrawal->user?->username ?? ''));
        $label = TelegramHtml::escapeText($name);

        return $username !== '' ? $label.' (@'.TelegramHtml::escapeText($username).')' : $label;
    }

    private function amount(WithdrawalRequest $withdrawal): string
    {
        $currency = strtoupper((string) $withdrawal->currency);
        $precision = CurrencyMinorUnits::precision($currency);
        $major = (int) $withdrawal->amount_minor / (10 ** $precision);

        return TelegramHtml::escapeText($currency.' '.number_format($major, $precision, '.', ','));
    }

    private function method(WithdrawalRequest $withdrawal): ?string
    {
        $details = is_array($withdrawal->payout_details) ? $withdrawal->payout_details : [];
        $parts = array_filter([
            $withdrawal->option?->name,
            $details['bank_name'] ?? $details['bank'] ?? null,
        ], static fn ($value) => is_scalar($value) && trim((string) $value) !== '');

        return $parts === [] ? null : TelegramHtml::escapeText(implode(' · ', array_map('strval', $parts)));
    }

    /** Account numbers are masked: the channel is a notification feed, not a payout desk. */
    private function account(WithdrawalRequest $withdrawal): ?string
    {
        $details = is_array($withdrawal->payout_details) ? $withdrawal->payout_details : [];
        $account = $details['account_number'] ?? $details['account'] ?? null;
        if (! is_scalar($account)) {
            return null;
        }

        $digits = preg_replace('/\s+/', '', (string) $account);
        if ($digits === '') {
            return null;
        }

        $masked = strlen($digits) > 4 ? str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4) : $digits;

        return '<code>'.TelegramHtml::escapeText($masked).'</code>';
    }

    private function walletLabel(WithdrawalRequest $withdrawal): string
    {
        $type = $withdrawal->wallet?->wallet_type;
        $type = $type instanceof \BackedEnum ? (string) $type->value : (string) ($type ?? 'wallet');

        return match ($type) {
            'share' => 'Share wallet',
            'review_group_b' => 'Play Store review wallet',
            'review_group_c' => 'App Store review wallet',
            'referral' => 'Referral wallet',
            'bonus' => 'Bonus wallet',
            'review' => 'Review wallet',
            'topup_fund' => 'Top-up fund wallet',
            default => ucfirst(str_replace('_', ' ', $type)).' wallet',
        };
    }

    private function reference(WithdrawalRequest $withdrawal): string
    {
        $reference = trim((string) ($withdrawal->provider_reference ?? ''));

        return $reference !== '' ? $reference : 'WD-'.str_pad((string) $withdrawal->id, 6, '0', STR_PAD_LEFT);
    }
}
