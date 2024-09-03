<?php

namespace App\Livewire;

use App\Models\Card;
use Livewire\Component;
use Livewire\WithPagination;

class CardIndex extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.card-index', [
            'cards' => Card::paginate(25),
        ]);
    }
}
