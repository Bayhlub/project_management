@props(['task', 'now', 'overdue' => false, 'showProject' => true])

@php($done = $task->isCompleted())

<li {{ $attributes->class('group flex items-start gap-3 px-4 py-3') }}>
    <input
        type="checkbox"
        @checked($done)
        wire:click="toggle({{ $task->id }})"
        aria-label="{{ $done ? 'Mark as not done' : 'Mark as done' }}: {{ $task->title }}"
        class="mt-1 size-4 shrink-0 cursor-pointer accent-emerald-600"
    />

    <div class="min-w-0 flex-1">
        <p class="break-words font-medium {{ $done ? 'text-zinc-400 line-through dark:text-zinc-500' : 'text-zinc-900 dark:text-white' }}">
            {{ $task->title }}
        </p>

        @if ($task->notes)
            <p class="mt-0.5 line-clamp-2 whitespace-pre-line text-sm text-zinc-500 dark:text-zinc-400">{{ $task->notes }}</p>
        @endif

        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
            @if ($label = $task->dueLabel($now))
                <span class="inline-flex items-center gap-1 {{ $overdue && ! $done ? 'font-medium text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                    <flux:icon name="calendar" variant="micro" />
                    {{ $label }}
                </span>
            @endif

            @if ($task->hasPendingReminder())
                <span class="inline-flex items-center gap-1 text-violet-600 dark:text-violet-400" title="Reminder">
                    <flux:icon name="bell-alert" variant="micro" />
                    {{ $task->reminderLabel($now) }}
                </span>
            @endif

            @if ($showProject && $task->project)
                <a href="{{ route('projects.show', $task->project) }}" wire:navigate class="inline-flex items-center gap-1.5 text-zinc-600 hover:underline dark:text-zinc-300">
                    <span class="size-2 rounded-full {{ $task->project->color->dotClass() }}" aria-hidden="true"></span>
                    {{ $task->project->name }}
                </a>
            @endif

            @if ($task->priority !== \App\Enums\Priority::Normal)
                <flux:badge size="sm" :color="$task->priority->color()">{{ $task->priority->label() }}</flux:badge>
            @endif
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-1">
        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $task->id }})" aria-label="Edit task" />
        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $task->id }})" wire:confirm="Delete this task?" aria-label="Delete task" />
    </div>
</li>
