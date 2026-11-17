<?php

use App\Models\Task;
use App\Support\Trash;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Trash')] class extends Component {
    public ?string $message = null;

    public function restore(string $type, int $id): void
    {
        $item = $this->find($type, $id);
        $item->restore();

        $this->message = $this->name($type, $item).' restored.';
    }

    public function deleteForever(string $type, int $id): void
    {
        $item = $this->find($type, $id);
        $item->forceDelete();

        $this->message = $this->name($type, $item).' deleted forever.';
    }

    public function emptyTrash(): void
    {
        // Files first so their stored files are removed; projects last, since
        // deleting a project forever also removes everything still inside it.
        foreach (['file', 'link', 'note', 'task', 'project'] as $type) {
            Trash::query($type)->get()->each->forceDelete();
        }

        $this->message = 'Trash emptied.';
    }

    /**
     * @return array<string, Collection>
     */
    #[Computed]
    public function items(): array
    {
        return collect(Trash::TYPES)
            ->mapWithKeys(fn (string $type) => [$type => Trash::query($type)->latest('deleted_at')->get()])
            ->all();
    }

    protected function find(string $type, int $id)
    {
        abort_unless(in_array($type, Trash::TYPES, true), 404);

        return Trash::query($type)->findOrFail($id);
    }

    protected function name(string $type, $item): string
    {
        return match ($type) {
            'project' => '“'.$item->name.'”',
            'task' => '“'.str($item->title)->limit(40).'”',
            'note' => 'Note',
            'link' => '“'.$item->displayLabel().'”',
            'file' => '“'.$item->original_name.'”',
        };
    }

    public function with(): array
    {
        return [
            'now' => now(),
            'total' => collect($this->items)->sum(fn ($items) => $items->count()),
            'sections' => [
                'project' => 'Projects',
                'task' => 'Tasks',
                'note' => 'Notes',
                'link' => 'Links',
                'file' => 'Files',
            ],
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-8">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Trash</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Deleted items wait here until you restore them or delete them forever.</p>
        </div>
        @if ($total)
            <flux:button
                variant="danger"
                icon="trash"
                wire:click="emptyTrash"
                wire:confirm="Delete all {{ $total }} {{ Str::plural('item', $total) }} in the trash forever? This cannot be undone."
            >Empty trash</flux:button>
        @endif
    </header>

    @if ($message)
        <div wire:key="msg-{{ md5($message.microtime()) }}" x-data x-init="setTimeout(() => $el.remove(), 5000)" role="status" class="-mt-4 rounded-lg border border-evergreen-100 bg-evergreen-50 px-4 py-2.5 text-sm text-evergreen-700 dark:border-evergreen-900 dark:bg-evergreen-900/40 dark:text-evergreen-300">
            {{ $message }}
        </div>
    @endif

    @if ($total === 0)
        <div class="rounded-xl border border-dashed border-zinc-300 bg-white/50 px-6 py-12 text-center dark:border-zinc-700 dark:bg-transparent">
            <flux:icon name="trash" class="mx-auto size-10 text-zinc-300 dark:text-zinc-600" />
            <p class="mt-4 font-medium text-zinc-900 dark:text-white">The trash is empty</p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Tasks, projects, notes, links and files you delete will appear here.</p>
        </div>
    @endif

    @foreach ($sections as $type => $heading)
        @if ($this->items[$type]->isNotEmpty())
            <section aria-labelledby="trash-{{ $type }}" wire:key="trash-{{ $type }}">
                <h2 id="trash-{{ $type }}" class="mb-2 flex items-baseline gap-2 text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                    {{ $heading }} <span class="font-normal tabular-nums text-zinc-400">{{ $this->items[$type]->count() }}</span>
                </h2>

                <ul class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-950">
                    @foreach ($this->items[$type] as $item)
                        @php
                            $title = match ($type) {
                                'project' => $item->name,
                                'task' => $item->title,
                                'note' => str($item->body)->squish()->limit(120),
                                'link' => $item->displayLabel(),
                                'file' => $item->original_name,
                            };
                            $parent = $type === 'project' ? null : $item->project;
                        @endphp
                        <li wire:key="{{ $type }}-{{ $item->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                            <div class="flex min-w-56 flex-1 items-start gap-3">
                                @if ($type === 'project')
                                    <span class="mt-1.5 size-2.5 shrink-0 rounded-full {{ $item->color->dotClass() }}" aria-hidden="true"></span>
                                @endif
                                <div class="min-w-0">
                                    <p class="break-words text-[15px] text-zinc-900 dark:text-zinc-50">{{ $title }}</p>
                                    <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>Deleted {{ Task::dayLabel($item->deleted_at, $now) }} at {{ $item->deleted_at->format('H:i') }}</span>
                                        @if ($parent)
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="size-2 rounded-full {{ $parent->color->dotClass() }}" aria-hidden="true"></span>
                                                {{ $parent->name }}{{ $parent->trashed() ? ' (also in trash)' : '' }}
                                            </span>
                                        @endif
                                        @if ($type === 'file')
                                            <span>{{ $item->humanSize() }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-1">
                                <flux:button size="sm" icon="arrow-uturn-left" wire:click="restore('{{ $type }}', {{ $item->id }})">Restore</flux:button>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    class="!text-overdue"
                                    wire:click="deleteForever('{{ $type }}', {{ $item->id }})"
                                    wire:confirm="{{ $type === 'project' ? 'Delete this project forever, with all its notes, links and files? Its tasks stay on your to-do list. This cannot be undone.' : 'Delete this forever? This cannot be undone.' }}"
                                >Delete forever</flux:button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach
</div>
