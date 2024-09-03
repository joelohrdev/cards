<?php

use App\Models\Card;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
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

    public function create(): void
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

        Card::create($validated);

        $this->reset([
            'year',
            'number',
            'mfg',
            'set_type',
            'first_name',
            'last_name',
            'team',
            'numbered',
            'rookie',
            'parallel',
            'value',
            'notes',
            'imageFront',
            'imageBack',
        ]);
    }
}; ?>

<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="mx-auto w-full max-w-2xl py-6">
                <form wire:submit.prevent="create">
                    <!-- Year -->
                    <div>
                        <x-input-label for="year" :value="__('Year')" />
                        <x-text-input
                            wire:model="year"
                            placeholder="2020"
                            id="year"
                            class="mt-1 block w-full"
                            type="text"
                            name="year"
                            required
                            autofocus
                            autocomplete="year"
                        />
                        <x-input-error :messages="$errors->get('year')" class="mt-2" />
                    </div>

                    <!-- Year -->
                    <div class="mt-4">
                        <x-input-label for="number" :value="__('Card Number')" />
                        <x-text-input
                            wire:model="number"
                            placeholder="321"
                            id="number"
                            class="mt-1 block w-full"
                            type="text"
                            name="number"
                            required
                            autofocus
                            autocomplete="number"
                        />
                        <x-input-error :messages="$errors->get('number')" class="mt-2" />
                    </div>

                    <!-- Mfg -->
                    <div class="mt-4">
                        <x-input-label for="mfg" :value="__('Manufacturer')" />
                        <x-text-input
                            wire:model="mfg"
                            placeholder="Topps"
                            id="mfg"
                            class="mt-1 block w-full"
                            type="text"
                            name="mfg"
                            required
                            autocomplete="mfg"
                        />
                        <x-input-error :messages="$errors->get('mfg')" class="mt-2" />
                    </div>

                    <!-- Set -->
                    <div class="mt-4">
                        <x-input-label for="set_type" :value="__('Set')" />
                        <x-text-input
                            wire:model="set_type"
                            placeholder="Series Two"
                            id="set_type"
                            class="mt-1 block w-full"
                            type="text"
                            name="set_type"
                            required
                            autocomplete="set_type"
                        />
                        <x-input-error :messages="$errors->get('set_type')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- First Name -->
                        <div class="mt-4">
                            <x-input-label for="first_name" :value="__('First Name')" />
                            <x-text-input
                                wire:model="first_name"
                                placeholder="Frank"
                                id="first_name"
                                class="mt-1 block w-full"
                                type="text"
                                name="first_name"
                                required
                                autocomplete="first_name"
                            />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                        </div>

                        <!-- Last Name -->
                        <div class="mt-4">
                            <x-input-label for="last_name" :value="__('Last Name')" />
                            <x-text-input
                                wire:model="last_name"
                                placeholder="Thomas"
                                id="last_name"
                                class="mt-1 block w-full"
                                type="text"
                                name="last_name"
                                required
                                autocomplete="last_name"
                            />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                        </div>
                    </div>

                    <!-- Team -->
                    <div class="mt-4">
                        <x-input-label for="team" :value="__('Team')" />
                        <x-text-input
                            wire:model="team"
                            id="team"
                            placeholder="White Sox"
                            class="mt-1 block w-full"
                            type="text"
                            name="team"
                            required
                            autocomplete="team"
                        />
                        <x-input-error :messages="$errors->get('team')" class="mt-2" />
                    </div>

                    <!-- Numbered -->
                    <div class="mt-4">
                        <x-input-label for="numbered" :value="__('Numbered')" />
                        <x-text-input
                            wire:model="numbered"
                            placeholder="01/50"
                            id="numbered"
                            class="mt-1 block w-full"
                            type="text"
                            name="numbered"
                            autocomplete="numbered"
                        />
                        <x-input-error :messages="$errors->get('numbered')" class="mt-2" />
                    </div>

                    <!-- Parallel -->
                    <div class="mt-4">
                        <x-input-label for="parallel" :value="__('Parallel')" />
                        <x-text-input
                            wire:model="parallel"
                            placeholder="X-Fractor"
                            id="parallel"
                            class="mt-1 block w-full"
                            type="text"
                            name="parallel"
                            autocomplete="parallel"
                        />
                        <x-input-error :messages="$errors->get('parallel')" class="mt-2" />
                    </div>

                    <!-- Value -->
                    <div class="mt-4">
                        <x-input-label for="value" :value="__('Value')" />
                        <x-text-input
                            wire:model="value"
                            id="value"
                            class="mt-1 block w-full"
                            type="text"
                            name="value"
                            autocomplete="value"
                        />
                        <x-input-error :messages="$errors->get('value')" class="mt-2" />
                    </div>

                    <!-- Notes -->
                    <div class="mt-4">
                        <x-input-label for="notes" :value="__('Notes')" />
                        <x-text-input
                            wire:model="notes"
                            id="notes"
                            class="mt-1 block w-full"
                            type="text"
                            name="notes"
                            autocomplete="notes"
                        />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <!-- Rookie -->
                    <div class="mt-4 block">
                        <label for="rookie" class="inline-flex items-center">
                            <input
                                wire:model="rookie"
                                id="rookie"
                                type="checkbox"
                                class="rounded border-gray-300 text-slate-600 shadow-sm focus:ring-slate-500"
                                name="rookie"
                            />
                            <span class="ms-2 text-sm text-gray-600">{{ __('Rookie') }}</span>
                        </label>
                    </div>

                    <x-primary-button class="mt-4">
                        {{ __('Create') }}
                    </x-primary-button>
                </form>
            </div>
        </div>
    </div>
</div>
