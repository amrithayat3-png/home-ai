@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'HOME AI')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <img src="{{ asset('images/centre-seal-64.png') }}" alt="Sindh Center For Excellence on Countering Violent Extremism" class="size-8 rounded-full">
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'HOME AI')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center">
            <img src="{{ asset('images/centre-seal-64.png') }}" alt="Sindh Center For Excellence on Countering Violent Extremism" class="size-8 rounded-full">
        </x-slot>
    </flux:brand>
@endif
