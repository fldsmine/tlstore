<?php

namespace App\Livewire\Dashboard\Account;

use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Edit Profile')]
class EditProfile extends Component
{
    use WithFileUploads;

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $telegram = '';

    public string $biography = '';

    public $photo = null;

    public bool $saved = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->first_name = (string) $user->first_name;
        $this->last_name = (string) $user->last_name;
        $this->phone = (string) $user->phone;
        $this->telegram = (string) $user->telegram;
        $this->biography = (string) $user->biography;
    }

    protected function rules(): array
    {
        // Enforce phone uniqueness (ignoring this user) only when one is entered, so a duplicate
        // returns a friendly validation error instead of hitting the users_phone_unique DB constraint.
        $phone = ['nullable', 'string', 'max:20'];
        if (trim($this->phone) !== '') {
            $phone[] = Rule::unique('users', 'phone')->ignore(auth()->id());
        }

        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'phone' => $phone,
            'telegram' => ['nullable', 'string', 'max:120'],
            'biography' => ['nullable', 'string', 'max:300'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    protected function messages(): array
    {
        return [
            'phone.unique' => 'That phone number is already linked to another account.',
        ];
    }

    public function updatedPhoto(): void
    {
        $this->validateOnly('photo');
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => trim($this->phone) !== '' ? trim($this->phone) : null,
            'telegram' => $this->telegram,
            'biography' => $this->biography,
        ];

        if ($this->photo) {

            $data['avatar'] = app(ImageCompressionService::class)->compressAndStore($this->photo, 'avatars', null, 82, 512, 512);
        }

        auth()->user()->update($data);

        $this->photo = null;
        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.dashboard.account.edit-profile', ['user' => auth()->user()]);
    }
}
