<?php

namespace App\Livewire;

use App\Models\Card;
use Livewire\Attributes\Layout;
use Livewire\Component;

class CardShow extends Component
{
    public Card $card;
    public $year;
    public $number;
    public $mfg;
    public $set_type;
    public $first_name;
    public $last_name;
    public $team;
    public $numbered;
    public $rookie;
    public $parallel;
    public $value;
    public $notes;
    public $imageFront;
    public $imageBack;

    public function mount(): void
    {
        $this->year = $this->card->year;
        $this->number = $this->card->number;
        $this->mfg = $this->card->mfg;
        $this->set_type = $this->card->set_type;
        $this->first_name = $this->card->first_name;
        $this->last_name = $this->card->last_name;
        $this->team = $this->card->team;
        $this->numbered = $this->card->numbered;
        $this->rookie = $this->card->rookie;
        $this->parallel = $this->card->parallel;
        $this->value = $this->card->value;
        $this->notes = $this->card->notes;
        $this->imageFront = $this->card->imageFront;
        $this->imageBack = $this->card->imageBack;
    }

    public function updateCard()
    {
        $validated = $this->validate([
            'year' => 'required|numeric',
            'number' => 'nullable|string',
            'mfg' => 'required|string',
            'set_type' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'team' => 'required|string',
            'numbered' => 'nullable|string',
            'rookie' => 'nullable|boolean',
            'parallel' => 'nullable|string',
            'value' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'imageFront' => 'nullable|image',
            'imageBack' => 'nullable|image',
        ]);

        $this->card->update($validated);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.card-show');
    }
}
