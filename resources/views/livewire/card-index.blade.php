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
                <th scope="col" class="py-3.5 pl-4 pr-4 text-left text-sm font-semibold text-gray-900 sm:pr-0"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            @foreach ($cards as $card)
                <livewire:card-index-item :$card :key="$card->id" />
            @endforeach
        </tbody>
    </table>
    <div class="mt-10">
        {{ $cards->links() }}
    </div>
</div>
