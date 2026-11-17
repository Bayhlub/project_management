<?php

use App\Enums\Priority;
use App\Livewire\Concerns\ManagesTasks;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('To-do')] class extends Component {
    use ManagesTasks;

    /** How many days after today get their own column in the week strip. */
    public const STRIP_DAYS = 5;

    // Quick add
    public string $title = '';
    public ?string $dueDate = null;
    public int $priority = 2;
    public ?string $projectId = null;

    // Filters
    #[Url(as: 'priority', except: '')]
    public string $priorityFilter = '';

    /** '' = all tasks, 'none' = tasks without a project, otherwise a project id */
    #[Url(as: 'project', except: '')]
    public string $projectFilter = '';

    /** '' = every day, 'overdue', 'today', 'later', or a date (Y-m-d) from the week strip */
    #[Url(as: 'day', except: '')]
    public string $day = '';

    public bool $showCompleted = false;

    public function addTask(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'dueDate' => ['nullable', 'date_format:Y-m-d'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'projectId' => ['nullable', $this->ownProjectRule()],
        ], [], ['projectId' => 'project']);

        auth()->user()->tasks()->create([
            'title' => trim($validated['title']),
            'due_date' => $validated['dueDate'] ?: null,
            'priority' => $validated['priority'],
            'project_id' => $validated['projectId'] ?: null,
        ]);

        // Keep the chosen project so several tasks can be added to it in a row.
        $this->reset('title', 'dueDate', 'priority');
    }

    public function pickDay(string $day): void
    {
        $this->day = $this->day === $day ? '' : $day;
    }

    /**
     * All open tasks matching the project and priority filters (not the day filter).
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function openTasks(): Collection
    {
        return $this->filteredTasks()
            ->open()
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderByRaw('due_time is null')
            ->orderBy('due_time')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * Open tasks split into list sections, after the day filter.
     *
     * @return array<string, Collection<int, Task>>
     */
    #[Computed]
    public function groups(): array
    {
        $now = now();

        $grouped = $this->openTasks
            ->filter(fn (Task $task) => $this->matchesDay($task, $now))
            ->groupBy(fn (Task $task) => $task->groupFor($now));

        return collect([Task::GROUP_OVERDUE, Task::GROUP_TODAY, Task::GROUP_UPCOMING, Task::GROUP_SOMEDAY])
            ->mapWithKeys(fn (string $group) => [$group => $grouped->get($group, collect())])
            ->all();
    }

    /**
     * Columns of the week strip: overdue, today, the next few days, later.
     *
     * @return list<array{key: string, label: string, sub: ?string, count: int, tone: string}>
     */
    #[Computed]
    public function strip(): array
    {
        $now = now();
        $today = $now->copy()->startOfDay();
        $tasks = $this->openTasks;

        $columns = [[
            'key' => 'overdue',
            'label' => 'Overdue',
            'sub' => null,
            'count' => $tasks->filter(fn (Task $t) => $t->groupFor($now) === Task::GROUP_OVERDUE)->count(),
            'tone' => 'overdue',
        ], [
            'key' => 'today',
            'label' => 'Today',
            'sub' => $today->format('j M'),
            'count' => $tasks->filter(fn (Task $t) => $t->groupFor($now) === Task::GROUP_TODAY)->count(),
            'tone' => 'today',
        ]];

        for ($i = 1; $i <= self::STRIP_DAYS; $i++) {
            $date = $today->copy()->addDays($i);
            $columns[] = [
                'key' => $date->format('Y-m-d'),
                'label' => $i === 1 ? 'Tomorrow' : $date->format('l'),
                'sub' => $date->format('j M'),
                'count' => $tasks->filter(fn (Task $t) => $t->due_date?->isSameDay($date))->count(),
                'tone' => $date->isWeekend() ? 'weekend' : 'day',
            ];
        }

        $columns[] = [
            'key' => 'later',
            'label' => 'Later',
            'sub' => 'After '.$today->copy()->addDays(self::STRIP_DAYS)->format('j M'),
            'count' => $tasks->filter(fn (Task $t) => $t->due_date?->gt($today->copy()->addDays(self::STRIP_DAYS)))->count(),
            'tone' => 'day',
        ];

        return $columns;
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function completed(): Collection
    {
        if (! $this->showCompleted) {
            return collect();
        }

        return $this->filteredTasks()->completed()->latest('completed_at')->limit(20)->get();
    }

    protected function matchesDay(Task $task, Carbon $now): bool
    {
        $today = $now->copy()->startOfDay();

        return match (true) {
            $this->day === '' => true,
            $this->day === 'overdue' => $task->groupFor($now) === Task::GROUP_OVERDUE,
            $this->day === 'today' => $task->groupFor($now) === Task::GROUP_TODAY,
            $this->day === 'later' => (bool) $task->due_date?->gt($today->copy()->addDays(self::STRIP_DAYS)),
            default => $task->due_date?->format('Y-m-d') === $this->day,
        };
    }

    protected function filteredTasks()
    {
        return auth()->user()->tasks()
            ->with('project')
            ->when($this->priorityFilter !== '', fn ($query) => $query->where('priority', (int) $this->priorityFilter))
            ->when($this->projectFilter === 'none', fn ($query) => $query->whereNull('project_id'))
            ->when(ctype_digit($this->projectFilter), fn ($query) => $query->where('project_id', (int) $this->projectFilter));
    }

    public function with(): array
    {
        $strip = collect($this->strip);
        $overdue = $strip->firstWhere('key', 'overdue')['count'];
        $today = $strip->firstWhere('key', 'today')['count'];
        $week = $strip->filter(fn ($c) => ! in_array($c['key'], ['overdue', 'today', 'later'], true))->sum('count');

        $summary = collect([
            $overdue ? "{$overdue} overdue" : null,
            $today ? "{$today} due today" : null,
            $week ? "{$week} more in the next ".self::STRIP_DAYS.' days' : null,
        ])->filter()->join(', ', ' and ');

        $dayLabel = match (true) {
            $this->day === '' => null,
            $this->day === 'overdue' => 'overdue tasks',
            $this->day === 'today' => 'today',
            $this->day === 'later' => 'tasks after '.today()->addDays(self::STRIP_DAYS)->format('l j F'),
            default => rescue(fn () => Carbon::createFromFormat('Y-m-d', $this->day)->format('l j F'), 'the chosen day', false),
        };

        return [
            'now' => now(),
            'priorities' => Priority::cases(),
            'summary' => $summary ? ucfirst($summary).'.' : 'Nothing due in the next few days.',
            'dayLabel' => $dayLabel,
            'isFiltered' => $this->priorityFilter !== '' || $this->projectFilter !== '' || $this->day !== '',
            'sections' => [
                Task::GROUP_OVERDUE => ['label' => 'Overdue', 'class' => 'text-overdue'],
                Task::GROUP_TODAY => ['label' => 'Today', 'class' => 'text-accent-content'],
                Task::GROUP_UPCOMING => ['label' => 'Upcoming', 'class' => 'text-zinc-700 dark:text-zinc-200'],
                Task::GROUP_SOMEDAY => ['label' => 'No date', 'class' => 'text-zinc-500 dark:text-zinc-400'],
            ],
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-8">
    {{-- Title --}}
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">To-do</h1>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $summary }}</p>
    </header>

    {{-- Week strip: the shape of the coming days at a glance; each column filters the list. --}}
    <nav aria-label="Filter by day" class="-mx-4 overflow-x-auto px-4 [scrollbar-width:thin] sm:mx-0 sm:px-0">
        <ol class="grid min-w-[44rem] grid-cols-8 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
            @foreach ($this->strip as $column)
                @php
                    $active = $day === $column['key'];
                    $empty = $column['count'] === 0;
                @endphp
                <li class="border-zinc-200 dark:border-zinc-800 [&:not(:first-child)]:border-l">
                    <button
                        type="button"
                        wire:click="pickDay('{{ $column['key'] }}')"
                        aria-pressed="{{ $active ? 'true' : 'false' }}"
                        @class([
                            'relative flex h-full w-full flex-col items-start gap-1 px-3 pb-3 pt-2.5 text-left transition-colors focus-visible:z-10 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent',
                            'bg-evergreen-50 dark:bg-evergreen-900/40' => $active,
                            'hover:bg-zinc-50 dark:hover:bg-zinc-900' => ! $active,
                            'bg-zinc-50/60 dark:bg-zinc-900/40' => ! $active && $column['tone'] === 'weekend',
                        ])
                    >
                        {{-- Today gets a bar along the top; overdue turns red only when there is something in it. --}}
                        @if ($column['tone'] === 'today')
                            <span class="absolute inset-x-0 top-0 h-0.5 bg-accent" aria-hidden="true"></span>
                        @endif

                        <span @class([
                            'text-xs font-medium',
                            'text-overdue' => $column['tone'] === 'overdue' && ! $empty,
                            'text-accent-content' => $column['tone'] === 'today',
                            'text-zinc-500 dark:text-zinc-400' => ! in_array($column['tone'], ['today'], true) && ! ($column['tone'] === 'overdue' && ! $empty),
                        ])>{{ $column['label'] }}</span>

                        <span @class([
                            'font-condensed text-3xl font-semibold leading-none tabular-nums',
                            'text-zinc-300 dark:text-zinc-700' => $empty,
                            'text-overdue' => ! $empty && $column['tone'] === 'overdue',
                            'text-zinc-900 dark:text-white' => ! $empty && $column['tone'] !== 'overdue',
                        ])>{{ $column['count'] }}</span>

                        <span class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $column['sub'] ?? ($empty ? 'All clear' : 'Past due') }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- Quick add: one line, details to the right. --}}
    <form wire:submit="addTask" class="-mt-2">
        <div class="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/20 lg:flex-row lg:items-center dark:border-zinc-800 dark:bg-zinc-950">
            <label class="flex min-w-0 flex-1 items-center gap-3 px-4">
                <flux:icon name="plus" class="size-5 shrink-0 text-zinc-400" />
                <span class="sr-only">Task title</span>
                <input
                    wire:model="title"
                    type="text"
                    placeholder="Add a task"
                    x-data
                    x-init="if (window.matchMedia('(min-width: 1024px)').matches) $el.focus()"
                    class="w-full border-0 bg-transparent py-3.5 text-[15px] text-zinc-900 placeholder:text-zinc-400 focus:outline-none dark:text-white"
                />
            </label>
            <div class="flex flex-wrap items-center gap-2 border-t border-zinc-200 bg-zinc-50/70 px-3 py-2 lg:border-t-0 lg:border-l lg:bg-transparent dark:border-zinc-800 dark:bg-zinc-900/50">
                <div class="w-36"><flux:input type="date" size="sm" wire:model="dueDate" aria-label="Due date" /></div>
                <div class="w-28">
                    <flux:select size="sm" wire:model="priority" aria-label="Priority">
                        @foreach (array_reverse($priorities) as $p)
                            <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                @if ($this->projectOptions->isNotEmpty())
                    <div class="w-36">
                        <flux:select size="sm" wire:model="projectId" aria-label="Project">
                            <flux:select.option value="">No project</flux:select.option>
                            @foreach ($this->projectOptions as $project)
                                <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
                <flux:button type="submit" size="sm" variant="primary">Add task</flux:button>
            </div>
        </div>
        <flux:error name="title" class="mt-2" />
        <flux:error name="dueDate" class="mt-2" />
        <flux:error name="projectId" class="mt-2" />
    </form>

    {{-- List toolbar --}}
    <div @class(['-mb-4 flex flex-wrap items-center gap-3', 'justify-between' => $dayLabel, 'justify-end' => ! $dayLabel])>
        @if ($dayLabel)
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Showing <span class="font-medium text-zinc-900 dark:text-white">{{ $dayLabel }}</span>.
                <button type="button" wire:click="$set('day', '')" class="ml-1 font-medium text-accent-content underline-offset-2 hover:underline">Show all days</button>
            </p>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <div class="w-40">
                <flux:select wire:model.live="projectFilter" size="sm" aria-label="Filter by project">
                    <flux:select.option value="">All projects</flux:select.option>
                    <flux:select.option value="none">No project</flux:select.option>
                    @foreach ($this->projectOptions as $project)
                        <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="w-36">
                <flux:select wire:model.live="priorityFilter" size="sm" aria-label="Filter by priority">
                    <flux:select.option value="">All priorities</flux:select.option>
                    @foreach (array_reverse($priorities) as $p)
                        <flux:select.option value="{{ $p->value }}">{{ $p->label() }} only</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="ml-1"><flux:switch wire:model.live="showCompleted" label="Completed" /></div>
        </div>
    </div>

    {{-- Sections --}}
    @foreach ($sections as $key => $section)
        @if ($this->groups[$key]->isNotEmpty())
            <section wire:key="section-{{ $key }}" aria-labelledby="h-{{ $key }}">
                <h2 id="h-{{ $key }}" class="mb-2 flex items-baseline gap-2 text-sm font-semibold {{ $section['class'] }}">
                    {{ $section['label'] }}
                    <span class="font-normal tabular-nums text-zinc-400">{{ $this->groups[$key]->count() }}</span>
                </h2>
                <ul class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-950">
                    @foreach ($this->groups[$key] as $task)
                        <x-task-row :task="$task" :now="$now" :overdue="$key === 'overdue'" wire:key="task-{{ $task->id }}" />
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    @if (collect($this->groups)->every(fn ($tasks) => $tasks->isEmpty()))
        <div class="rounded-xl border border-dashed border-zinc-300 bg-white/50 px-6 py-12 text-center dark:border-zinc-700 dark:bg-transparent">
            <x-app-logo-icon class="mx-auto size-10 opacity-90" />
            <p class="mt-4 font-medium text-zinc-900 dark:text-white">
                {{ $isFiltered ? 'No open tasks match these filters' : 'You’re all caught up' }}
            </p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $isFiltered ? 'Clear the day, project or priority filter to see more.' : 'Add a task above to plan your next step.' }}
            </p>
        </div>
    @endif

    @if ($showCompleted)
        <section wire:key="section-completed" aria-labelledby="h-completed">
            <h2 id="h-completed" class="mb-2 flex items-baseline gap-2 text-sm font-semibold text-zinc-500 dark:text-zinc-400">
                Completed
                <span class="font-normal tabular-nums text-zinc-400">{{ $this->completed->count() }}</span>
            </h2>
            @if ($this->completed->isEmpty())
                <p class="text-sm text-zinc-500">No completed tasks yet.</p>
            @else
                <ul class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-950">
                    @foreach ($this->completed as $task)
                        <x-task-row :task="$task" :now="$now" wire:key="done-{{ $task->id }}" />
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <x-task-edit-modal :projects="$this->projectOptions" />
</div>
