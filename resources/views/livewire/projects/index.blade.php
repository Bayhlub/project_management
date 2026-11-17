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
                'tasks as done_tasks_count' => fn ($query) => $query->whereNotNull('completed_at'),
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
            // Finished projects sink to the bottom; the rest by nearest deadline.
            ->orderByRaw("status = 'done'")
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
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">Projects</h1>
            <p class="mt-1 text-zinc-600 dark:text-zinc-400">Each project keeps its tasks, notes, links and files together.</p>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">New project</flux:button>
    </header>

    {{-- Segmented filter --}}
    <div role="group" aria-label="Filter projects" class="inline-flex w-fit flex-wrap rounded-lg border border-zinc-200 bg-white p-0.5 dark:border-zinc-800 dark:bg-zinc-950">
        @foreach ($filters as $key => $label)
            <button
                type="button"
                wire:click="$set('show', '{{ $key }}')"
                aria-pressed="{{ $show === $key ? 'true' : 'false' }}"
                @class([
                    'rounded-md px-3 py-1 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-accent',
                    'bg-zinc-900 font-medium text-white dark:bg-white dark:text-zinc-900' => $show === $key,
                    'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => $show !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    @if ($this->projects->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 bg-white/50 px-6 py-12 text-center dark:border-zinc-700 dark:bg-transparent">
            <flux:icon name="folder-open" class="mx-auto size-10 text-zinc-300 dark:text-zinc-600" />
            <p class="mt-4 font-medium text-zinc-900 dark:text-white">
                {{ $show === 'all' ? 'No projects yet' : 'No projects match this filter' }}
            </p>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $show === 'all' ? 'Create a project to keep its tasks, notes, links and files in one place.' : 'Choose another filter above.' }}
            </p>
            @if ($show === 'all')
                <flux:button variant="primary" icon="plus" wire:click="create" class="mt-5">New project</flux:button>
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                    <tr>
                        <th scope="col" class="py-2.5 pl-5 pr-3 font-medium">Project</th>
                        <th scope="col" class="hidden px-3 py-2.5 font-medium sm:table-cell">Status</th>
                        <th scope="col" class="hidden px-3 py-2.5 font-medium md:table-cell">Progress</th>
                        <th scope="col" class="px-3 py-2.5 font-medium">Deadline</th>
                        <th scope="col" class="hidden py-2.5 pl-3 pr-5 text-right font-medium lg:table-cell">Saved items</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->projects as $project)
                        @php
                            $total = $project->open_tasks_count + $project->done_tasks_count;
                            $percent = $total ? (int) round($project->done_tasks_count / $total * 100) : 0;
                            $items = $project->notes_count + $project->links_count + $project->files_count;
                        @endphp
                        <tr wire:key="project-{{ $project->id }}" class="group relative align-top transition-colors hover:bg-zinc-50/70 dark:hover:bg-zinc-900/60">
                            <td class="py-3.5 pl-5 pr-3">
                                <div class="flex gap-3">
                                    <span class="mt-1 h-8 w-1 shrink-0 rounded-full {{ $project->color->dotClass() }}" aria-hidden="true"></span>
                                    <div class="min-w-0">
                                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="font-medium text-zinc-900 after:absolute after:inset-0 group-hover:underline dark:text-white">
                                            {{ $project->name }}
                                        </a>
                                        @if ($project->description)
                                            <p class="mt-0.5 line-clamp-1 max-w-md text-zinc-500 dark:text-zinc-400">{{ $project->description }}</p>
                                        @endif
                                        <div class="mt-1 sm:hidden">
                                            <flux:badge size="sm" :color="$project->status->color()">{{ $project->status->label() }}</flux:badge>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-3 py-3.5 sm:table-cell">
                                <flux:badge size="sm" :color="$project->status->color()">{{ $project->status->label() }}</flux:badge>
                            </td>
                            <td class="hidden px-3 py-3.5 md:table-cell">
                                @if ($total)
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Tasks done">
                                            <div class="h-full rounded-full bg-accent" style="width: {{ $percent }}%"></div>
                                        </div>
                                        <span class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ $project->done_tasks_count }}/{{ $total }}</span>
                                    </div>
                                @else
                                    <span class="text-zinc-400">No tasks</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                @if ($project->deadline)
                                    <span @class(['tabular-nums', 'font-medium text-overdue' => $project->isPastDeadline(), 'text-zinc-700 dark:text-zinc-300' => ! $project->isPastDeadline()])>
                                        {{ $project->deadline->format('j M Y') }}
                                    </span>
                                    @if ($project->isPastDeadline())
                                        <span class="block text-xs text-overdue">Past deadline</span>
                                    @elseif ($project->status !== \App\Enums\ProjectStatus::Done)
                                        <span class="block text-xs text-zinc-400">@php($daysLeft = (int) today()->diffInDays($project->deadline))
                                        {{ $daysLeft === 0 ? 'Today' : 'in '.$daysLeft.' '.\Illuminate\Support\Str::plural('day', $daysLeft) }}</span>
                                    @endif
                                @else
                                    <span class="text-zinc-400">None</span>
                                @endif
                            </td>
                            <td class="hidden py-3.5 pl-3 pr-5 text-right tabular-nums text-zinc-500 lg:table-cell dark:text-zinc-400">
                                {{ $items ?: '–' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-project-form-modal heading="New project" />
</div>
