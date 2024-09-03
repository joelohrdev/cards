<?php

use App\Models\Card;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $year = '';
    public string $mfg = '';
    public string $cardSet = '';
    public string $firstName = '';
    public string $lastName = '';
    public string $team = '';
    public string $numbered = '';
    public bool $rookie = false;
    public string $parallel = '';
    public ?int $value = null;
    public string $notes = '';
    public string $imageFront = '';
    public string $imageBack = '';

    public function create(): void
    {
        $validated = $this->validate([
            'year' => ['required', 'string', 'max:255'],
            'mfg' => ['required', 'string', 'max:255'],
            'cardSet' => ['required', 'string', 'max:255'],
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'team' => ['required', 'string', 'max:255'],
            'numbered' => ['required', 'string', 'max:255'],
            'parallel' => ['required', 'string', 'max:255'],
            'value' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:255'],
            'imageFront' => ['nullable', 'string', 'max:255'],
            'imageBack' => ['nullable', 'string', 'max:255'],
        ]);

        // Create the card
        Card::create($validated);
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
                        <x-input-label for="cardSet" :value="__('Set')" />
                        <x-text-input
                            wire:model="cardSet"
                            placeholder="Stadium Club"
                            id="cardSet"
                            class="mt-1 block w-full"
                            type="text"
                            name="cardSet"
                            required
                            autocomplete="cardSet"
                        />
                        <x-input-error :messages="$errors->get('cardSet')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- First Name -->
                        <div class="mt-4">
                            <x-input-label for="firstName" :value="__('First Name')" />
                            <x-text-input
                                wire:model="firstName"
                                placeholder="Frank"
                                id="firstName"
                                class="mt-1 block w-full"
                                type="text"
                                name="firstName"
                                required
                                autocomplete="firstName"
                            />
                            <x-input-error :messages="$errors->get('firstName')" class="mt-2" />
                        </div>

                        <!-- Last Name -->
                        <div class="mt-4">
                            <x-input-label for="lastName" :value="__('Last Name')" />
                            <x-text-input
                                wire:model="lastName"
                                placeholder="Thomas"
                                id="lastName"
                                class="mt-1 block w-full"
                                type="text"
                                name="lastName"
                                required
                                autocomplete="lastName"
                            />
                            <x-input-error :messages="$errors->get('lastName')" class="mt-2" />
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
                            required
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
                            required
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
                            required
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
                            required
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
