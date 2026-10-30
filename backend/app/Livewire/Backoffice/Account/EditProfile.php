<?php

namespace App\Livewire\Backoffice\Account;

use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backoffice')]
#[Title('My Profile')]
class EditProfile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    public $photo = null;

    public function mount(): void
    {
        $admin = auth('admin')->user();
        $this->name = (string) $admin->name;
        $this->email = (string) $admin->email;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore(auth('admin')->id())],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function updatedPhoto(): void
    {
        $this->validateOnly('photo');
    }

    public function save(): void
    {
        $this->validate();

        $admin = auth('admin')->user();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->photo) {
            $data['avatar'] = app(ImageCompressionService::class)->compressAndStore($this->photo, 'admin-avatars', null, 82, 512, 512);
        }

        $admin->update($data);

        $this->photo = null;
        $this->dispatch('toast', message: 'Profile updated successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.account.edit-profile', ['admin' => auth('admin')->user()]);
    }
}
