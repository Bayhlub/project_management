@props(['task', 'now', 'overdue' => false, 'showProject' => true])

@php
    $done = $task->isCompleted();

    // The checkbox ring carries the priority: strong for high, faint for low.
    $ring = match ($task->priority) {
        \App\Enums\Priority::High => 'border-overdue',
        \App\Enums\Priority::Low => 'border-zinc-300 dark:border-zinc-600',
        default => 'border-zinc-400 dark:border-zinc-500',
    };
@endphp

<li {{ $attributes->class('group relative flex items-start gap-3 px-4 py-3 transition-colors hover:bg-zinc-50/70 dark:hover:bg-zinc-900/60') }}>
    <span class="relative mt-0.5 flex size-5 shrink-0 items-center justify-center">
        <input
            type="checkbox"
            @checked($done)
            wire:click="toggle({{ $task->id }})"
            aria-label="{{ $done ? 'Mark as not done' : 'Mark as done' }}: {{ $task->title }}"
            class="peer size-5 cursor-pointer appearance-none rounded-full border-2 {{ $ring }} bg-white transition-colors checked:border-accent checked:bg-accent hover:border-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent dark:bg-zinc-950"
        />
        <svg viewBox="0 0 16 16" class="pointer-events-none absolute size-3 text-accent-foreground opacity-0 peer-checked:opacity-100" aria-hidden="true">
            <path d="M3.5 8.5l3 3 6-7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>

    <div class="min-w-0 flex-1">
        <p class="break-words text-[15px] leading-snug {{ $done ? 'text-zinc-400 line-through dark:text-zinc-500' : 'text-zinc-900 dark:text-zinc-50' }}">
            {{ $task->title }}
            @if ($task->priority === \App\Enums\Priority::High && ! $done)
                <span class="sr-only">(high priority)</span>
            @endif
        </p>

        @if ($task->notes)
            <p class="mt-0.5 line-clamp-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $task->notes }}</p>
        @endif

        @php($dueLabel = $task->dueLabel($now))
        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
            @if ($dueLabel)
                <span @class([
                    'inline-flex items-center gap-1',
                    'font-medium text-overdue' => $overdue && ! $done,
                    'text-zinc-600 dark:text-zinc-300' => ! ($overdue && ! $done),
                ])>
                    <flux:icon name="calendar" variant="micro" class="opacity-70" />
                    Due {{ $dueLabel }}
                </span>
            @endif

            @if ($task->hasPendingReminder())
                <span class="inline-flex items-center gap-1 text-reminder" title="Reminder">
                    <flux:icon name="bell" variant="micro" />
                    {{ $task->reminderLabel($now) }}
                </span>
            @endif

            @if ($showProject && $task->project)
                <a href="{{ route('projects.show', $task->project) }}" wire:navigate class="relative z-10 inline-flex items-center gap-1.5 text-zinc-600 hover:text-zinc-900 hover:underline dark:text-zinc-300 dark:hover:text-white">
                    <span class="size-2 rounded-full {{ $task->project->color->dotClass() }}" aria-hidden="true"></span>
                    {{ $task->project->name }}
                </a>
            @endif

            @if (! $done && $task->priority === \App\Enums\Priority::High)
                <span class="inline-flex items-center gap-1 font-medium text-overdue">
                    <flux:icon name="flag" variant="micro" /> High
                </span>
            @elseif (! $done && $task->priority === \App\Enums\Priority::Low)
                <span class="text-zinc-400">Low priority</span>
            @endif

            {{-- When it was added (or finished), always shown last and quiet. --}}
            <span class="inline-flex items-center gap-1 text-zinc-400 dark:text-zinc-500" title="Added {{ $task->created_at?->format('l j F Y, H:i') }}">
                @if ($done)
                    <flux:icon name="check" variant="micro" />
                    Done {{ \App\Models\Task::dayLabel($task->completed_at, $now) }}
                @else
                    <flux:icon name="plus-circle" variant="micro" />
                    Added {{ $task->addedLabel($now) }}
                @endif
            </span>
        </div>
    </div>

    {{-- Actions: always visible on touch screens, on hover or focus elsewhere. --}}
    <div class="flex shrink-0 items-center gap-0.5 sm:opacity-0 sm:transition-opacity sm:group-hover:opacity-100 sm:focus-within:opacity-100">
        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $task->id }})" aria-label="Edit task" />
        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $task->id }})" aria-label="Move task to trash" />
    </div>
</li>
