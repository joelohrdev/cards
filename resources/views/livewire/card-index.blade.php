<div>
    <div class="relative col-span-3 mb-10 text-sm text-gray-800">
        <div class="pointer-events-none absolute bottom-0 left-0 top-0 flex items-center pl-2 text-gray-500">
            <svg
                class="size-5"
                xmlns="http://www.w3.org/2000/svg"
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
            </svg>
        </div>

        <input
            wire:model.live="search"
            type="text"
            placeholder="Search name or team or card #"
            class="block w-full rounded-lg border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-slate-600 sm:text-sm sm:leading-6"
        />
    </div>
    <table class="min-w-full divide-y divide-gray-300">
        <thead>
            <tr class="divide-x divide-gray-200">
                <th scope="col" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">#</th>
                <th scope="col" class="py-3.5 pl-4 pr-4 text-left text-sm font-semibold text-gray-900">Name</th>
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
