<div>
    <table class="min-w-full divide-y divide-gray-300">
        <thead>
            <tr class="divide-x divide-gray-200">
                <th scope="col" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">#</th>
                <th scope="col" class="py-3.5 pl-4 pr-4 text-left text-sm font-semibold text-gray-900 sm:pl-0">Name</th>
                <th scope="col" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">Team</th>
                <th scope="col" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">Manufacturer</th>
                <th scope="col" class="py-3.5 pl-4 pr-4 text-left text-sm font-semibold text-gray-900 sm:pr-0">
                    Numbered
                </th>
                <th scope="col" class="py-3.5 pl-4 pr-4 text-left text-sm font-semibold text-gray-900 sm:pr-0">
                    Parallel
                </th>
                <th scope="col" class="py-3.5 pl-4 pr-4 text-left text-sm font-semibold text-gray-900 sm:pr-0">
                    Value
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @foreach ($cards as $card)
                <tr class="divide-x divide-gray-200">
                    <td class="whitespace-nowrap p-2 text-xs text-gray-500">{{ $card->number }}</td>
                    <td class="whitespace-nowrap py-2 pl-4 pr-4 text-sm font-medium text-gray-900">
                        <div class="flex items-center justify-between">
                            <div>
                                {{ $card->first_name }} {{ $card->last_name }}
                                <span class="block text-xs text-gray-400">{{ $card->team }}</span>
                            </div>
                            @if ($card->rookie)
                                <x-icons.rookie class="size-8" />
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
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-10">
        {{ $cards->links() }}
    </div>
</div>
