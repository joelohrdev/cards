<?php

namespace App\Livewire;

use App\Models\Card;
use Livewire\Component;

class CardIndexItem extends Component
{
    public Card $card;

    public function render()
    {
        return view('livewire.card-index-item');
    }
}
