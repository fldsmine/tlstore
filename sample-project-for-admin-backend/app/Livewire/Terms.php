<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('Terms & Conditions')]
class Terms extends Component
{
    public function render(): View
    {
        return view('livewire.terms');
    }
}
