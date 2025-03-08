<?php

namespace App\Livewire\Card;

use App\Livewire\Forms\CardForm;
use Illuminate\View\View;
use Livewire\Component;

class Create extends Component
{
    public CardForm $form;

    public function render(): View
    {
        return view('livewire.card.create');
    }
}
