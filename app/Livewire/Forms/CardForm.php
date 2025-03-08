<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class CardForm extends Form
{
    #[Validate(['required'])]
    public $uuid = '';

    #[Validate(['required'])]
    public $name = '';

    #[Validate(['required'])]
    public $team = '';

    #[Validate(['nullable'])]
    public $year = '';

    #[Validate(['nullable'])]
    public $brand = '';

    #[Validate(['nullable'])]
    public $number = '';

    #[Validate(['nullable'])]
    public $setName = '';

    #[Validate(['nullable'])]
    public $image = '';

    #[Validate(['nullable'])]
    public $quantity = 0;

    #[Validate(['nullable'])]
    public $estimatedValue = 0;

    #[Validate(['nullable'])]
    public $purchasePrice = 0;

    #[Validate(['nullable'])]
    public $purchaseDate = 0;

    #[Validate(['nullable'])]
    public $isSold = false;

    #[Validate(['nullable'])]
    public $soldDate = '';

    #[Validate(['nullable'])]
    public $favorite = false;
}
