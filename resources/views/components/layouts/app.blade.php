<x-layouts.app.sidebar>
    <flux:main>
        <div class="-mt-2 mb-2 flex justify-end">
            {{-- Kept alive across wire:navigate page changes so pop-ups aren't lost. --}}
            @persist('reminder-bell')
                <livewire:reminder-bell />
            @endpersist
        </div>

        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
