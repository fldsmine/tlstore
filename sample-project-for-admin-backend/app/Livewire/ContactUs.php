<?php

namespace App\Livewire;

use App\Mail\ContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('Contact Us')]
class ContactUs extends Component
{
    public string $name = '';

    public string $email = '';

    public string $message = '';

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'message' => ['required', 'string', 'min:5'],
        ]);

        try {
            Mail::to(config('settings.contact.email'))
                ->send(new ContactMessage($this->name, $this->email, $this->message));
        } catch (\Throwable $e) {
            Log::warning('Contact message dispatch failed: '.$e->getMessage());
        }

        $this->reset('name', 'email', 'message');

        session()->flash('contact-success', "Thanks! We'll get back to you within 24 hours.");
    }

    public function render(): View
    {
        return view('livewire.contact-us');
    }
}
