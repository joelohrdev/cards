<div class="max-w-2xl mx-auto">
    <form class="space-y-6">
        <flux:field>
            <flux:label>Player Name</flux:label>

            <flux:input size="sm" wire:model="form.name" type="text" />

            <flux:error name="name" />
        </flux:field>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Year</flux:label>

                <flux:input size="sm" wire:model="form.year" type="text" />

                <flux:error name="year" />
            </flux:field>

            <flux:field>
                <flux:label>Team Name</flux:label>

                <flux:input size="sm" wire:model="form.team" type="text" />

                <flux:error name="team" />
            </flux:field>

            <flux:field>
                <flux:label>Card Number</flux:label>

                <flux:input size="sm" wire:model="form.cardNumber" type="text" />

                <flux:error name="cardNumber" />
            </flux:field>

            <flux:field>
                <flux:label>Brand</flux:label>

                <flux:select size="sm" wire:model="form.brand">
                    <flux:select.option></flux:select.option>
                    @foreach(\App\Enums\Brand::cases() as $brand)
                        <flux:select.option>{{ $brand }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Set Name</flux:label>

            <flux:input size="sm" wire:model="form.setName" type="text" />

            <flux:error name="setName" />
        </flux:field>

        <flux:field>
            <flux:label>Image</flux:label>

            <flux:input  wire:model="form.image" type="file" />

            <flux:error name="image" />
        </flux:field>

        <flux:field>
            <flux:label>Quantity</flux:label>

            <flux:input size="sm" wire:model="form.quantity" type="number" />

            <flux:error name="quantity  " />
        </flux:field>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Estimated Value</flux:label>

                <flux:input.group>
                    <flux:input.group.prefix>$</flux:input.group.prefix>

                    <flux:input size="sm" wire:model="form.estimatedValue" placeholder="9.99" />
                </flux:input.group>

                <flux:error name="estimatedValue  " />
            </flux:field>

            <flux:field>
                <flux:label>Purchase Price</flux:label>

                <flux:input.group>
                    <flux:input.group.prefix>$</flux:input.group.prefix>

                    <flux:input size="sm" wire:model="form.purchasePrice" placeholder="9.99" />
                </flux:input.group>

                <flux:error name="purchasePrice" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Purchase Date</flux:label>

            <flux:date-picker size="sm" wire:model="form.purchaseDate" />

            <flux:error name="purchaseDate" />
        </flux:field>

        <flux:checkbox wire:model.live="form.isSold" label="Card has been sold" />

        @if($form->isSold)
            <flux:field>
                <flux:label>Sold Date</flux:label>

                <flux:date-picker  wire:model="form.soldDate" />

                <flux:error name="soldDate" />
            </flux:field>
        @endif

        <flux:checkbox wire:model="form.favorite" label="Favorite" />

        <flux:button variant="primary">Add</flux:button>
    </form>
</div>
