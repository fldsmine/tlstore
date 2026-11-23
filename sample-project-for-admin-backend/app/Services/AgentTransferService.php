<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AgentTransferService
{
    public function transfer(User $from, string $toUsername, float $usd, string $idempotencyKey = ''): Transaction
    {
        $usd = round($usd, 6);

        if (! $from->isSpecialVendor()) {
            throw ValidationException::withMessages(['amount' => 'Only special vendors can transfer funds.']);
        }

        if ($usd <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }

        $recipient = User::query()
            ->where('username', trim($toUsername))
            ->where('is_agent', true)
            ->where('status', UserStatus::ACTIVE->value)
            ->first();

        if (! $recipient) {
            throw ValidationException::withMessages(['recipientUsername' => 'No active vendor found with that username.']);
        }

        if ($recipient->id === $from->id) {
            throw ValidationException::withMessages(['recipientUsername' => 'You cannot transfer to yourself.']);
        }

        $key = preg_replace('/[^A-Za-z0-9]/', '', $idempotencyKey) ?: Str::random(16);
        $transferRef = 'AGT-'.strtoupper(substr($key, 0, 16));

        return DB::transaction(function () use ($from, $recipient, $usd, $transferRef): Transaction {

            $ids = [$from->id, $recipient->id];
            sort($ids);
            $locked = User::query()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            $sender = $locked[$from->id];
            $receiver = $locked[$recipient->id];

            $existing = Transaction::query()->where('reference', $transferRef.'-S')->first();
            if ($existing) {
                return $existing;
            }

            if (! $sender->isSpecialVendor() || $sender->status === UserStatus::SUSPENDED) {
                throw ValidationException::withMessages(['amount' => 'Your account cannot transfer funds right now.']);
            }

            if ($receiver->status === UserStatus::SUSPENDED) {
                throw ValidationException::withMessages(['recipientUsername' => 'That vendor cannot receive transfers right now.']);
            }

            if ((float) $sender->agent_balance < $usd) {
                throw ValidationException::withMessages(['amount' => 'Insufficient vendor balance for this transfer.']);
            }

            $sender->decrement('agent_balance', $usd);
            $receiver->increment('agent_balance', $usd);

            $debit = Transaction::create([
                'user_id' => $sender->id,
                'type' => TransactionType::AGENT_TRANSFER,
                'direction' => 'debit',
                'amount' => $usd,
                'wallet' => 'agent',
                'status' => 'completed',
                'reference' => $transferRef.'-S',
                'description' => 'Transfer to @'.$receiver->username."'s Agent Wallet",
                'meta' => [
                    'transfer_ref' => $transferRef,
                    'role' => 'sender',
                    'counterparty_id' => $receiver->id,
                    'counterparty_username' => $receiver->username,
                ],
            ]);

            Transaction::create([
                'user_id' => $receiver->id,
                'type' => TransactionType::AGENT_TRANSFER,
                'direction' => 'credit',
                'amount' => $usd,
                'wallet' => 'agent',
                'status' => 'completed',
                'reference' => $transferRef.'-R',
                'description' => 'Transfer from @'.$sender->username.' to your Agent Wallet',
                'meta' => [
                    'transfer_ref' => $transferRef,
                    'role' => 'recipient',
                    'counterparty_id' => $sender->id,
                    'counterparty_username' => $sender->username,
                ],
            ]);

            return $debit;
        });
    }
}
