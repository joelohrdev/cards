<?php

namespace App\Livewire;

use App\Models\Card;
use Livewire\Component;
use Livewire\WithPagination;

class CardIndex extends Component
{
    use Searchable, WithPagination;

    public function render()
    {
        $query = Card::query();
        $query = $this->applySearch($query);
        $cards = $query->paginate(25);

        return view('livewire.card-index', [
            'cards' => $cards,
        ]);
    }
}
