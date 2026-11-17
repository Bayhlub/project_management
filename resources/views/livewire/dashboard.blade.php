<?php

use App\Enums\Priority;
use App\Livewire\Concerns\ManagesTasks;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('To-do')] class extends Component {
    use ManagesTasks;

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

    /**
     * Open tasks split into dashboard sections.
     *
     * @return array<string, Collection<int, Task>>
     */
    #[Computed]
    public function groups(): array
    {
        $now = now();

        $grouped = $this->filteredTasks()
            ->open()
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderByRaw('due_time is null')
            ->orderBy('due_time')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $task) => $task->groupFor($now));

        return collect([Task::GROUP_OVERDUE, Task::GROUP_TODAY, Task::GROUP_UPCOMING, Task::GROUP_SOMEDAY])
            ->mapWithKeys(fn (string $group) => [$group => $grouped->get($group, collect())])
            ->all();
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
        return [
            'now' => now(),
            'priorities' => Priority::cases(),
            'isFiltered' => $this->priorityFilter !== '' || $this->projectFilter !== '',
            'sections' => [
                Task::GROUP_OVERDUE => ['label' => 'Overdue', 'icon' => 'exclamation-triangle', 'class' => 'text-red-600 dark:text-red-400'],
                Task::GROUP_TODAY => ['label' => 'Today', 'icon' => 'sun', 'class' => 'text-amber-600 dark:text-amber-400'],
                Task::GROUP_UPCOMING => ['label' => 'Upcoming', 'icon' => 'calendar-days', 'class' => 'text-sky-600 dark:text-sky-400'],
                Task::GROUP_SOMEDAY => ['label' => 'No date', 'icon' => 'inbox', 'class' => 'text-zinc-500 dark:text-zinc-400'],
            ],
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">To-do</flux:heading>
            <flux:text class="mt-1">{{ $now->format('l, j F Y') }}</flux:text>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <flux:select wire:model.live="projectFilter" size="sm" class="w-44" aria-label="Filter by project">
                <flux:select.option value="">All projects</flux:select.option>
                <flux:select.option value="none">No project</flux:select.option>
                @foreach ($this->projectOptions as $project)
                    <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="priorityFilter" size="sm" class="w-36" aria-label="Filter by priority">
                <flux:select.option value="">All priorities</flux:select.option>
                @foreach (array_reverse($priorities) as $p)
                    <flux:select.option value="{{ $p->value }}">{{ $p->label() }} only</flux:select.option>
                @endforeach
            </flux:select>

            <flux:switch wire:model.live="showCompleted" label="Show completed" />
        </div>
    </div>

    {{-- Summary --}}
    @php($overdueCount = $this->groups['overdue']->count())
    <div class="grid grid-cols-3 gap-3">
        <div class="rounded-xl border p-4 {{ $overdueCount ? 'border-red-300 bg-red-50 dark:border-red-500/40 dark:bg-red-500/10' : 'border-zinc-200 dark:border-zinc-700' }}">
            <flux:text>Overdue</flux:text>
            <div class="mt-1 text-2xl font-semibold {{ $overdueCount ? 'text-red-600 dark:text-red-400' : '' }}">{{ $overdueCount }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Due today</flux:text>
            <div class="mt-1 text-2xl font-semibold">{{ $this->groups['today']->count() }}</div>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Open tasks</flux:text>
            <div class="mt-1 text-2xl font-semibold">{{ collect($this->groups)->sum(fn ($tasks) => $tasks->count()) }}</div>
        </div>
    </div>

    {{-- Quick add --}}
    <form wire:submit="addTask" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <div class="min-w-48 flex-1">
                <flux:input wire:model="title" placeholder="Add a task, then press Enter" aria-label="Task title" autofocus />
            </div>
            <flux:input type="date" wire:model="dueDate" aria-label="Due date" class="sm:w-40" />
            <flux:select wire:model="priority" aria-label="Priority" class="sm:w-28">
                @foreach (array_reverse($priorities) as $p)
                    <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($this->projectOptions->isNotEmpty())
                <flux:select wire:model="projectId" aria-label="Project" class="sm:w-40">
                    <flux:select.option value="">No project</flux:select.option>
                    @foreach ($this->projectOptions as $project)
                        <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:button type="submit" variant="primary" icon="plus">Add</flux:button>
        </div>
        <flux:error name="title" class="mt-2" />
        <flux:error name="dueDate" class="mt-2" />
        <flux:error name="projectId" class="mt-2" />
    </form>

    {{-- Sections --}}
    @foreach ($sections as $key => $section)
        @if ($this->groups[$key]->isNotEmpty())
            <section wire:key="section-{{ $key }}">
                <div class="mb-2 flex items-center gap-2 {{ $section['class'] }}">
                    <flux:icon :name="$section['icon']" variant="mini" />
                    <h2 class="text-sm font-semibold uppercase tracking-wide">{{ $section['label'] }}</h2>
                    <span class="text-sm opacity-70">{{ $this->groups[$key]->count() }}</span>
                </div>
                <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($this->groups[$key] as $task)
                        <x-task-row :task="$task" :now="$now" :overdue="$key === 'overdue'" wire:key="task-{{ $task->id }}" />
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach

    @if (collect($this->groups)->every(fn ($tasks) => $tasks->isEmpty()))
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:icon name="check-circle" class="mx-auto size-10 text-emerald-500" />
            <flux:heading size="lg" class="mt-3">
                {{ $isFiltered ? 'No open tasks match these filters' : 'Nothing to do' }}
            </flux:heading>
            <flux:text class="mt-1">
                {{ $isFiltered ? 'Choose "All projects" and "All priorities" to see everything.' : 'Add your first task above.' }}
            </flux:text>
        </div>
    @endif

    @if ($showCompleted)
        <section wire:key="section-completed">
            <div class="mb-2 flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                <flux:icon name="check-circle" variant="mini" />
                <h2 class="text-sm font-semibold uppercase tracking-wide">Completed</h2>
                <span class="text-sm opacity-70">{{ $this->completed->count() }}</span>
            </div>
            @if ($this->completed->isEmpty())
                <flux:text>No completed tasks yet.</flux:text>
            @else
                <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($this->completed as $task)
                        <x-task-row :task="$task" :now="$now" wire:key="done-{{ $task->id }}" />
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <x-task-edit-modal :projects="$this->projectOptions" />
</div>
