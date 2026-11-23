<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('Our Reliable Distributors')]
class Agents extends Component
{
    #[Url]
    public string $tab = 'nigeria';

    public function selectTab(string $tab): void
    {
        $this->tab = $tab === 'foreign' ? 'foreign' : 'nigeria';
    }

    public function agents(): array
    {
        return User::query()
            ->where('is_agent', true)
            ->where('show_on_agents_page', true)
            ->when(
                $this->tab === 'foreign',
                fn (Builder $q): Builder => $q->where('country', '!=', 'NG'),
                fn (Builder $q): Builder => $q->where('country', 'NG'),
            )
            ->inRandomOrder()
            ->get(['id', 'username', 'phone', 'telegram', 'avatar', 'country'])
            ->map(function (User $user): array {
                $digits = preg_replace('/\D/', '', (string) $user->phone);

                $whatsapp = $user->country === 'NG'
                    ? (str_starts_with($digits, '0') ? '234'.substr($digits, 1) : $digits)
                    : $digits;

                $telegram = trim((string) $user->telegram);
                $telegramUrl = $telegram === ''
                    ? null
                    : (str_starts_with($telegram, 'http') ? $telegram : 'https://t.me/'.ltrim($telegram, '@'));

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'whatsapp' => $whatsapp,
                    'telegram' => $telegramUrl,
                    'avatar' => $user->avatar,
                ];
            })
            ->all();
    }

    public function render(): View
    {
        $base = User::query()->where('is_agent', true)->where('show_on_agents_page', true);

        return view('livewire.agents', [
            'nigeriaCount' => (clone $base)->where('country', 'NG')->count(),
            'foreignCount' => (clone $base)->where('country', '!=', 'NG')->count(),
        ]);
    }
}
