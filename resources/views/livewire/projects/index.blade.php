<?php

use App\Livewire\Forms\ProjectForm;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Projects')] class extends Component {
    public ProjectForm $form;

    #[Url(as: 'show', except: 'all')]
    public string $show = 'all';

    public function create(): void
    {
        $this->form->reset();
        $this->resetValidation();

        Flux::modal('project-form')->show();
    }

    public function save(): void
    {
        $project = auth()->user()->projects()->create($this->form->data());

        Flux::modal('project-form')->close();
        $this->redirectRoute('projects.show', $project, navigate: true);
    }

    #[Computed]
    public function projects(): Collection
    {
        return auth()->user()->projects()
            ->withCount([
                'tasks as open_tasks_count' => fn ($query) => $query->whereNull('completed_at'),
                'notes',
                'links',
                'files',
            ])
            ->when(
                $this->show === 'archived',
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at'),
            )
            ->when(in_array($this->show, ['active', 'on_hold', 'done'], true), fn ($query) => $query->where('status', $this->show))
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->orderBy('name')
            ->get();
    }

    public function with(): array
    {
        return [
            'filters' => [
                'all' => 'All',
                'active' => 'Active',
                'on_hold' => 'On hold',
                'done' => 'Done',
                'archived' => 'Archived',
            ],
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Projects</flux:heading>
            <flux:text class="mt-1">Everything you're working on, with its notes, links and files.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">New project</flux:button>
    </div>

    <nav class="flex flex-wrap gap-2" aria-label="Filter projects">
        @foreach ($filters as $key => $label)
            <button
                type="button"
                wire:click="$set('show', '{{ $key }}')"
                @class([
                    'rounded-full px-3 py-1 text-sm font-medium transition',
                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $show === $key,
                    'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-600' => $show !== $key,
                ])
                aria-pressed="{{ $show === $key ? 'true' : 'false' }}"
            >{{ $label }}</button>
        @endforeach
    </nav>

    @if ($this->projects->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:icon name="folder-open" class="mx-auto size-10 text-zinc-400" />
            <flux:heading size="lg" class="mt-3">
                {{ $show === 'all' ? 'No projects yet' : 'No projects here' }}
            </flux:heading>
            <flux:text class="mt-1">
                {{ $show === 'all' ? 'Create your first project to keep its tasks, notes, links and files together.' : 'Try another filter.' }}
            </flux:text>
            @if ($show === 'all')
                <flux:button variant="primary" icon="plus" wire:click="create" class="mt-4">New project</flux:button>
            @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->projects as $project)
                <a
                    href="{{ route('projects.show', $project) }}"
                    wire:navigate
                    wire:key="project-{{ $project->id }}"
                    class="group flex flex-col overflow-hidden rounded-xl border border-zinc-200 transition hover:border-zinc-400 hover:shadow-sm dark:border-zinc-700 dark:hover:border-zinc-500"
                >
                    <div class="h-1.5 {{ $project->color->dotClass() }}"></div>
                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="font-semibold text-zinc-900 group-hover:underline dark:text-white">{{ $project->name }}</h2>
                            <flux:badge size="sm" :color="$project->status->color()">{{ $project->status->label() }}</flux:badge>
                        </div>

                        @if ($project->description)
                            <p class="line-clamp-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $project->description }}</p>
                        @endif

                        <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                            @if ($project->deadline)
                                <span @class(['inline-flex items-center gap-1', 'font-medium text-red-600 dark:text-red-400' => $project->isPastDeadline()])>
                                    <flux:icon name="flag" variant="micro" />
                                    {{ $project->isPastDeadline() ? 'Past deadline · ' : 'Due ' }}{{ $project->deadline->format('j M Y') }}
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1" title="Open tasks">
                                <flux:icon name="check-circle" variant="micro" /> {{ $project->open_tasks_count }} open
                            </span>
                            <span class="inline-flex items-center gap-1" title="Notes, links and files">
                                <flux:icon name="paper-clip" variant="micro" /> {{ $project->notes_count + $project->links_count + $project->files_count }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <x-project-form-modal heading="New project" />
</div>
