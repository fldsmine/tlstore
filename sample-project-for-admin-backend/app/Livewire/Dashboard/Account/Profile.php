<?php

namespace App\Livewire\Dashboard\Account;

use App\Models\Package;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Profile')]
class Profile extends Component
{
    /** Step-up gate shown while the user confirms unlinking their Telegram account. */
    public bool $confirmingTelegramUnlink = false;

    public string $telegramUnlinkPassword = '';

    public function confirmTelegramUnlink(): void
    {
        if (! auth()->user()->telegramIdentity()->exists()) {
            return;
        }

        $this->reset('telegramUnlinkPassword');
        $this->confirmingTelegramUnlink = true;
    }

    public function cancelTelegramUnlink(): void
    {
        $this->reset(['confirmingTelegramUnlink', 'telegramUnlinkPassword']);
        $this->resetErrorBag();
    }

    public function unlinkTelegram(TelegramLinkService $links): void
    {
        $user = auth()->user();

        if (! $user->telegramIdentity()->exists()) {
            $this->cancelTelegramUnlink();
            $this->dispatch('toast', message: 'Your account is not linked to Telegram.', type: 'info');

            return;
        }

        // Keep the password prompt on screen so a failed attempt (wrong or missing
        // password) can show the error next to the field instead of silently closing.
        $this->confirmingTelegramUnlink = true;

        // Unlinking changes how the account can be signed into — and it is the first
        // half of "unlink theirs, link yours" — so it costs the account password.
        $this->validate([
            'telegramUnlinkPassword' => ['required', 'current_password'],
        ], [
            'telegramUnlinkPassword.required' => 'Enter your account password to unlink Telegram.',
            'telegramUnlinkPassword.current_password' => 'Your password is incorrect.',
        ]);

        $links->unlink($user);

        $this->cancelTelegramUnlink();
        $this->dispatch('toast', message: 'Telegram unlinked. You can still sign in with your password.', type: 'success');
    }

    public function render(): View
    {
        $user = auth()->user()->loadMissing(['package', 'referrer', 'telegramIdentity']);

        $canUpgrade = Package::query()
            ->when($user->package, fn ($q) => $q->where('tier_level', '>', $user->package->tier_level))
            ->exists();

        return view('livewire.dashboard.account.profile', [
            'user' => $user,
            'hasPackage' => (bool) $user->package,
            'canUpgrade' => $canUpgrade,
            'telegram' => $user->telegramIdentity,
        ]);
    }
}
