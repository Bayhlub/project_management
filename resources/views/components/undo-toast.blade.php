@props(['label' => null, 'key' => null])

{{-- Needs a Livewire component that uses App\Livewire\Concerns\OffersUndo. --}}
@if ($label)
    <div
        wire:key="undo-{{ $key }}"
        x-data
        x-init="setTimeout(() => $wire.dismissUndo(), 12000)"
        role="status"
        class="fixed bottom-4 left-1/2 z-40 flex w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 items-center gap-3 rounded-xl bg-zinc-900 px-4 py-3 text-sm text-white shadow-lg dark:bg-white dark:text-zinc-900"
    >
        <flux:icon name="trash" variant="micro" class="shrink-0 opacity-70" />
        <p class="line-clamp-2 min-w-0 flex-1">{{ $label }} moved to trash.</p>
        <button type="button" wire:click="undoTrash" class="shrink-0 font-semibold text-evergreen-300 hover:underline dark:text-evergreen-700">Undo</button>
        <a href="{{ route('trash') }}" wire:navigate class="hidden shrink-0 opacity-80 hover:underline sm:inline">View trash</a>
        <button type="button" wire:click="dismissUndo" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Dismiss">&times;</button>
    </div>
@endif
