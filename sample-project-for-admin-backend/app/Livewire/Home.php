<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('Invest in Call Minutes, Earn Daily Returns')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.home');
    }
}
