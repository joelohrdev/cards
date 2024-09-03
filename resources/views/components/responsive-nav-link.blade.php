@props([
    'active',
])

@php
    $classes =
        $active ?? false
            ? 'block w-full border-l-4 border-slate-400 bg-slate-50 py-2 pe-4 ps-3 text-start text-base font-medium text-slate-700 transition duration-150 ease-in-out focus:border-slate-700 focus:bg-slate-100 focus:text-slate-800 focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800 focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
