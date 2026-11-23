<?php

namespace App\Console\Commands;

use App\Models\Chat;
use Illuminate\Console\Command;

class PruneChatsCommand extends Command
{
    protected $signature = 'chats:prune';

    protected $description = 'Delete conversations (and their messages) inactive past the retention window.';

    public function handle(): int
    {
        $days = (int) config('settings.chat.history_retention_days', 7);
        $cutoff = now()->subDays($days);

        $deleted = Chat::query()
            ->where(fn ($q) => $q->where('last_message_at', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('last_message_at')->where('created_at', '<', $cutoff)))
            ->delete(); // chat_messages cascade

        $this->info("Pruned {$deleted} conversation(s) inactive for over {$days} day(s).");

        return self::SUCCESS;
    }
}
