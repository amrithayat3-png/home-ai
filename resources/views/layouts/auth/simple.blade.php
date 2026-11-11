<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 text-center font-medium" wire:navigate>
                    <img src="{{ asset('images/centre-seal.png') }}" width="128" height="128" class="rounded-full" style="width:128px;height:128px;box-shadow:0 0 0 2px rgba(255,255,255,.2),0 0 26px rgba(92,169,255,.6),0 0 70px rgba(92,169,255,.3);" alt="Seal of the Sindh Center For Excellence on Countering Violent Extremism">
                    <span class="text-base font-semibold leading-snug text-neutral-900 dark:text-white">Sindh Center For Excellence on Countering Violent Extremism</span>
                    <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ config('app.name', 'HOME AI') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
