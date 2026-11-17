<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="!px-4 !pt-4 sm:!px-8 lg:!px-10">
        {{-- Top bar: today's date and the reminders bell. --}}
        <div class="mb-6 flex items-center justify-between gap-4 border-b border-zinc-200 pb-3 dark:border-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                <time datetime="{{ now()->toDateString() }}">{{ now()->format('l, j F Y') }}</time>
            </p>

            {{-- Kept alive across wire:navigate page changes so pop-ups aren't lost. --}}
            @persist('reminder-bell')
                <livewire:reminder-bell />
            @endpersist
        </div>

        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
