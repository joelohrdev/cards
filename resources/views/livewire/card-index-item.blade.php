<tr class="divide-x divide-gray-200">
    <td class="whitespace-nowrap p-2 text-xs text-gray-500">{{ $card->number }}</td>
    <td class="whitespace-nowrap py-2 pl-4 pr-4 text-sm font-medium text-gray-900">
        <div class="flex items-center justify-between">
            <div>
                {{ $card->first_name }} {{ $card->last_name }}
                <span class="block text-xs text-gray-400">{{ $card->team }}</span>
            </div>
            @if ($card->rookie)
                <x-icons.rookie class="size-8 opacity-75" />
            @endif
        </div>
    </td>
    <td class="whitespace-nowrap p-2 text-xs text-gray-500">{{ $card->team }}</td>
    <td class="whitespace-nowrap p-2 text-xs text-gray-500">
        {{ $card->year }} {{ $card->mfg }} {{ $card->set_type }}
    </td>
    <td class="whitespace-nowrap py-2 pl-4 pr-4 text-xs text-gray-500 sm:pr-0">
        {{ $card->numbered }}
    </td>
    <td class="whitespace-nowrap py-2 pl-4 pr-4 text-xs text-gray-500 sm:pr-0">
        {{ $card->parallel }}
    </td>
    <td class="whitespace-nowrap py-2 pl-4 pr-4 text-xs text-gray-500 sm:pr-0">
        @if ($card->value)
            ${{ $card->value }}
        @endif
    </td>
    <td class="whitespace-nowrap py-2 pl-4 pr-4 text-xs text-gray-500 sm:pr-0">
        <a wire:navigate href="{{ route('cards.show', $card) }}">View</a>
    </td>
</tr>
