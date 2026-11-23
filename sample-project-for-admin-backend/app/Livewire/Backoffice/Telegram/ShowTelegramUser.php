<?php

namespace App\Livewire\Backoffice\Telegram;

use App\Models\TelegramIdentity;
use App\Models\TelegramMessage;
use App\Services\Telegram\TelegramBotClient;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Mini App user — Backoffice')]
class ShowTelegramUser extends Component
{
    public string $identityId;

    public string $reply = '';

    public function mount(TelegramIdentity $identity): void
    {
        $this->identityId = $identity->id;
        $this->markRead();
    }

    public function sendReply(TelegramMessenger $messenger): void
    {
        $this->reply = trim($this->reply);
        $data = $this->validate([
            'reply' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        $admin = auth('admin')->user();
        abort_unless($admin !== null, 403);

        $message = $messenger->sendTo($this->identity(), $data['reply'], $admin);
        $this->reset('reply');

        if ($message->status === TelegramMessage::STATUS_SENT) {
            $this->dispatch('toast', message: 'Message delivered on Telegram.', type: 'success');

            return;
        }

        $this->dispatch('toast', message: 'Telegram did not deliver this message. The user may not have started the bot, or the bot token is missing.', type: 'warning');
    }

    public function render(TelegramBotClient $bot): View
    {
        $identity = $this->identity();

        return view('livewire.backoffice.telegram.show-telegram-user', [
            'identity' => $identity,
            'messages' => $identity->messages()->with('admin')->orderBy('created_at')->get(),
            'botConfigured' => $bot->isConfigured(),
        ]);
    }

    private function identity(): TelegramIdentity
    {
        abort_unless(auth('admin')->check(), 403);

        return TelegramIdentity::query()
            ->with('user.package')
            ->findOrFail($this->identityId);
    }

    private function markRead(): void
    {
        TelegramMessage::query()
            ->where('telegram_identity_id', $this->identityId)
            ->where('direction', TelegramMessage::DIRECTION_INBOUND)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
