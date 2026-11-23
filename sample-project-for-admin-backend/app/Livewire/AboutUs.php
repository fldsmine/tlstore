<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('About Us')]
class AboutUs extends Component
{
    public function render(): View
    {
        return view('livewire.about-us');
    }
}
