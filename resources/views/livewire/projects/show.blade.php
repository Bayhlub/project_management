<?php

use App\Enums\Priority;
use App\Livewire\Concerns\ManagesTasks;
use App\Livewire\Forms\ProjectForm;
use App\Models\Project;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use ManagesTasks, WithFileUploads;

    public Project $project;

    public ProjectForm $form;

    // Quick add task
    public string $taskTitle = '';
    public ?string $taskDueDate = null;
    public int $taskPriority = 2;
    public bool $showCompletedTasks = false;

    // Notes, links, files
    public string $noteBody = '';
    public ?string $linkLabel = null;
    public string $linkUrl = '';
    public $upload;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    public function rendering(View $view): void
    {
        $view->title($this->project->name);
    }

    // ---- Project ----------------------------------------------------------

    public function editProject(): void
    {
        $this->form->fillFrom($this->project);
        $this->resetValidation();

        Flux::modal('project-form')->show();
    }

    public function save(): void
    {
        $this->authorize('update', $this->project);

        $this->project->update($this->form->data());

        Flux::modal('project-form')->close();
    }

    public function toggleArchive(): void
    {
        $this->authorize('update', $this->project);

        $this->project->archived_at = $this->project->isArchived() ? null : now();
        $this->project->save();
    }

    public function deleteProject(): void
    {
        $this->authorize('delete', $this->project);

        $this->project->delete();

        $this->redirectRoute('projects.index', navigate: true);
    }

    // ---- Tasks ------------------------------------------------------------

    public function addTask(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'taskTitle' => ['required', 'string', 'max:255'],
            'taskDueDate' => ['nullable', 'date_format:Y-m-d'],
            'taskPriority' => ['required', Rule::enum(Priority::class)],
        ], [], ['taskTitle' => 'title', 'taskDueDate' => 'due date', 'taskPriority' => 'priority']);

        auth()->user()->tasks()->create([
            'project_id' => $this->project->id,
            'title' => trim($validated['taskTitle']),
            'due_date' => $validated['taskDueDate'] ?: null,
            'priority' => $validated['taskPriority'],
        ]);

        $this->reset('taskTitle', 'taskDueDate', 'taskPriority');
    }

    #[Computed]
    public function openTasks(): Collection
    {
        return $this->project->tasks()
            ->open()
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderByRaw('due_time is null')
            ->orderBy('due_time')
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function completedTasks(): Collection
    {
        return $this->project->tasks()->completed()->latest('completed_at')->limit(20)->get();
    }

    // ---- Notes ------------------------------------------------------------

    public function addNote(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate(['noteBody' => ['required', 'string', 'max:10000']], [], ['noteBody' => 'note']);

        $this->project->notes()->create(['body' => trim($validated['noteBody'])]);
        $this->reset('noteBody');
    }

    public function deleteNote(int $id): void
    {
        $this->authorize('update', $this->project);

        $this->project->notes()->findOrFail($id)->delete();
    }

    // ---- Links ------------------------------------------------------------

    public function addLink(): void
    {
        $this->authorize('update', $this->project);

        $validated = $this->validate([
            'linkUrl' => ['required', 'string', 'max:2048', 'url:http,https'],
            'linkLabel' => ['nullable', 'string', 'max:120'],
        ], [
            'linkUrl.url' => 'Enter a full web address starting with http:// or https://',
        ], ['linkUrl' => 'link', 'linkLabel' => 'label']);

        $this->project->links()->create([
            'url' => trim($validated['linkUrl']),
            'label' => filled($validated['linkLabel']) ? trim($validated['linkLabel']) : null,
        ]);
        $this->reset('linkUrl', 'linkLabel');
    }

    public function deleteLink(int $id): void
    {
        $this->authorize('update', $this->project);

        $this->project->links()->findOrFail($id)->delete();
    }

    // ---- Files ------------------------------------------------------------

    /**
     * Files are saved as soon as they are picked.
     */
    public function updatedUpload(): void
    {
        $this->authorize('update', $this->project);

        $this->validate(
            ['upload' => ['required', 'file', 'max:10240']],
            ['upload.max' => 'Files can be up to 10 MB.'],
            ['upload' => 'file'],
        );

        $path = $this->upload->store($this->project->filesDirectory(), 'local');

        $file = $this->project->files()->make([
            'original_name' => mb_substr($this->upload->getClientOriginalName(), 0, 255),
            'mime_type' => $this->upload->getMimeType(),
            'size' => $this->upload->getSize(),
        ]);
        $file->path = $path;
        $file->save();

        $this->reset('upload');
    }

    public function deleteFile(int $id): void
    {
        $this->authorize('update', $this->project);

        $this->project->files()->findOrFail($id)->delete();
    }

    public function with(): array
    {
        return [
            'now' => now(),
            'doneCount' => $this->project->tasks()->completed()->count(),
            'notes' => $this->project->notes()->latest()->latest('id')->get(),
            'links' => $this->project->links()->oldest()->get(),
            'files' => $this->project->files()->latest()->latest('id')->get(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
    @php
        $openCount = $this->openTasks->count();
        $totalCount = $openCount + $doneCount;
        $percent = $totalCount ? (int) round($doneCount / $totalCount * 100) : 0;
    @endphp

    {{-- Header --}}
    <header>
        <a href="{{ route('projects.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
            <flux:icon name="chevron-left" variant="micro" /> All projects
        </a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
            <div class="flex min-w-0 gap-4">
                <span class="mt-1 h-10 w-1.5 shrink-0 rounded-full {{ $project->color->dotClass() }}" aria-hidden="true"></span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 class="break-words text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $project->name }}</h1>
                        <flux:badge size="sm" :color="$project->status->color()">{{ $project->status->label() }}</flux:badge>
                        @if ($project->isArchived())
                            <flux:badge size="sm" color="zinc" icon="archive-box">Archived</flux:badge>
                        @endif
                    </div>
                    @if ($project->description)
                        <p class="mt-2 max-w-2xl whitespace-pre-line text-zinc-600 dark:text-zinc-400">{{ $project->description }}</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" icon="pencil-square" wire:click="editProject">Edit</flux:button>
                <flux:button size="sm" icon="archive-box" wire:click="toggleArchive">
                    {{ $project->isArchived() ? 'Unarchive' : 'Archive' }}
                </flux:button>
                <flux:dropdown position="bottom" align="end">
                    <flux:button size="sm" icon="ellipsis-horizontal" aria-label="More actions" />
                    <flux:menu>
                        <flux:menu.item
                            icon="trash"
                            variant="danger"
                            wire:click="deleteProject"
                            wire:confirm="Delete this project with all its notes, links and files? Its tasks stay on your to-do list without a project. This cannot be undone."
                        >Delete project</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        {{-- Key facts --}}
        <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 sm:grid-cols-3 dark:border-zinc-800 dark:bg-zinc-800">
            <div class="bg-white px-4 py-3 dark:bg-zinc-950">
                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Deadline</dt>
                <dd @class(['mt-1 text-sm font-medium', 'text-overdue' => $project->isPastDeadline(), 'text-zinc-900 dark:text-white' => ! $project->isPastDeadline()])>
                    @if ($project->deadline)
                        {{ $project->deadline->format('D j M Y') }}
                        @if ($project->isPastDeadline())
                            <span class="font-normal">(past)</span>
                        @endif
                    @else
                        <span class="font-normal text-zinc-400">Not set</span>
                    @endif
                </dd>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-zinc-950">
                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Tasks done</dt>
                <dd class="mt-1 flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white">
                    <span class="tabular-nums">{{ $doneCount }} of {{ $totalCount }}</span>
                    <span class="h-1.5 w-20 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Tasks done">
                        <span class="block h-full rounded-full bg-accent" style="width: {{ $percent }}%"></span>
                    </span>
                </dd>
            </div>
            <div class="col-span-2 bg-white px-4 py-3 sm:col-span-1 dark:bg-zinc-950">
                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Started</dt>
                <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $project->created_at->format('j M Y') }}</dd>
            </div>
        </dl>
    </header>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- Main column: tasks and the notes log --}}
        <div class="flex min-w-0 flex-col gap-10">
            <section aria-labelledby="tasks-heading">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 id="tasks-heading" class="flex items-baseline gap-2 font-semibold text-zinc-900 dark:text-white">
                        Tasks <span class="text-sm font-normal tabular-nums text-zinc-400">{{ $openCount }} open</span>
                    </h2>
                    <flux:switch wire:model.live="showCompletedTasks" label="Completed" />
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                    <form wire:submit="addTask" class="flex flex-col border-b border-zinc-200 sm:flex-row sm:items-center dark:border-zinc-800">
                        <label class="flex min-w-0 flex-1 items-center gap-3 px-4">
                            <flux:icon name="plus" class="size-5 shrink-0 text-zinc-400" />
                            <span class="sr-only">Task title</span>
                            <input wire:model="taskTitle" type="text" placeholder="Add a task to this project" class="w-full border-0 bg-transparent py-3 text-[15px] placeholder:text-zinc-400 focus:outline-none" />
                        </label>
                        <div class="flex items-center gap-2 border-t border-zinc-200 px-3 py-2 sm:border-t-0 sm:border-l dark:border-zinc-800">
                            <div class="w-36"><flux:input type="date" size="sm" wire:model="taskDueDate" aria-label="Due date" /></div>
                            <div class="w-28">
                                <flux:select size="sm" wire:model="taskPriority" aria-label="Priority">
                                    @foreach (array_reverse(\App\Enums\Priority::cases()) as $p)
                                        <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <flux:button type="submit" size="sm" variant="primary">Add</flux:button>
                        </div>
                    </form>

                    @if ($this->openTasks->isNotEmpty())
                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->openTasks as $task)
                                <x-task-row :task="$task" :now="$now" :overdue="$task->groupFor($now) === \App\Models\Task::GROUP_OVERDUE" :show-project="false" wire:key="task-{{ $task->id }}" />
                            @endforeach
                        </ul>
                    @else
                        <p class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">No open tasks. Add the next step above.</p>
                    @endif

                    @if ($showCompletedTasks && $this->completedTasks->isNotEmpty())
                        <ul class="divide-y divide-zinc-100 border-t border-zinc-200 bg-zinc-50/50 dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900/40">
                            @foreach ($this->completedTasks as $task)
                                <x-task-row :task="$task" :now="$now" :show-project="false" wire:key="done-{{ $task->id }}" />
                            @endforeach
                        </ul>
                    @endif
                </div>
                <flux:error name="taskTitle" class="mt-2" />
            </section>

            <section aria-labelledby="notes-heading">
                <h2 id="notes-heading" class="mb-3 flex items-baseline gap-2 font-semibold text-zinc-900 dark:text-white">
                    Notes <span class="text-sm font-normal tabular-nums text-zinc-400">{{ $notes->count() }}</span>
                </h2>

                <form wire:submit="addNote" class="overflow-hidden rounded-xl border border-zinc-200 bg-white focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/20 dark:border-zinc-800 dark:bg-zinc-950">
                    <label for="note-body" class="sr-only">New note</label>
                    <textarea id="note-body" wire:model="noteBody" rows="3" placeholder="Write down progress, decisions or ideas" class="block w-full resize-y border-0 bg-transparent px-4 py-3 text-[15px] placeholder:text-zinc-400 focus:outline-none"></textarea>
                    <div class="flex justify-end border-t border-zinc-100 px-3 py-2 dark:border-zinc-800">
                        <flux:button type="submit" size="sm">Add note</flux:button>
                    </div>
                </form>
                <flux:error name="noteBody" class="mt-2" />

                {{-- The log: newest first, hung on a thin timeline. --}}
                @if ($notes->isNotEmpty())
                    <ol class="mt-6 border-l border-zinc-200 dark:border-zinc-800">
                        @foreach ($notes as $note)
                            <li wire:key="note-{{ $note->id }}" class="group relative pb-6 pl-6 last:pb-0">
                                <span class="absolute -left-[5px] top-1.5 size-2.5 rounded-full border-2 border-zinc-50 bg-zinc-400 dark:border-zinc-900 dark:bg-zinc-500" aria-hidden="true"></span>
                                <div class="flex items-center justify-between gap-2">
                                    <time datetime="{{ $note->created_at->toIso8601String() }}" class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                        {{ $note->created_at->format('D j M Y') }} at {{ $note->created_at->format('H:i') }}
                                    </time>
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteNote({{ $note->id }})" wire:confirm="Delete this note?" aria-label="Delete note" class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100" />
                                </div>
                                <p class="mt-1 max-w-prose whitespace-pre-line break-words text-[15px] leading-relaxed text-zinc-800 dark:text-zinc-200">{{ $note->body }}</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>

        {{-- Side rail: links and files --}}
        <aside class="flex flex-col gap-6">
            <section aria-labelledby="links-heading" class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                <h2 id="links-heading" class="flex items-baseline gap-2 border-b border-zinc-100 px-4 py-3 text-sm font-semibold text-zinc-900 dark:border-zinc-800 dark:text-white">
                    Links <span class="font-normal tabular-nums text-zinc-400">{{ $links->count() }}</span>
                </h2>

                @if ($links->isNotEmpty())
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($links as $link)
                            <li wire:key="link-{{ $link->id }}" class="group flex items-center gap-2 px-4 py-2.5">
                                <flux:icon name="link" variant="micro" class="shrink-0 text-zinc-400" />
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-zinc-900 hover:underline dark:text-white">{{ $link->displayLabel() }}</span>
                                    @if ($link->label)
                                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $link->host() }}</span>
                                    @endif
                                </a>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="deleteLink({{ $link->id }})" wire:confirm="Remove this link?" aria-label="Remove link" class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100" />
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form wire:submit="addLink" class="flex flex-col gap-2 border-t border-zinc-100 p-3 dark:border-zinc-800">
                    <flux:input wire:model="linkUrl" type="url" size="sm" placeholder="https://" aria-label="Link address" />
                    <div class="flex gap-2">
                        <div class="min-w-0 flex-1"><flux:input wire:model="linkLabel" size="sm" placeholder="Label (optional)" aria-label="Link label" /></div>
                        <flux:button type="submit" size="sm">Add link</flux:button>
                    </div>
                    <flux:error name="linkUrl" />
                    <flux:error name="linkLabel" />
                </form>
            </section>

            <section aria-labelledby="files-heading" class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                <h2 id="files-heading" class="flex items-baseline gap-2 border-b border-zinc-100 px-4 py-3 text-sm font-semibold text-zinc-900 dark:border-zinc-800 dark:text-white">
                    Files <span class="font-normal tabular-nums text-zinc-400">{{ $files->count() }}</span>
                </h2>

                @if ($files->isNotEmpty())
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($files as $file)
                            @php($ext = strtoupper(pathinfo($file->original_name, PATHINFO_EXTENSION)) ?: 'FILE')
                            <li wire:key="file-{{ $file->id }}" class="group flex items-center gap-3 px-4 py-2.5">
                                <span class="flex h-8 w-7 shrink-0 items-end justify-center rounded-sm border border-zinc-200 bg-zinc-50 pb-1 font-condensed text-[9px] font-semibold text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400" aria-hidden="true">{{ \Illuminate\Support\Str::limit($ext, 4, '') }}</span>
                                <a href="{{ route('projects.files.download', [$project, $file]) }}" class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-zinc-900 hover:underline dark:text-white">{{ $file->original_name }}</span>
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $file->humanSize() }}, added {{ $file->created_at->format('j M Y') }}</span>
                                </a>
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteFile({{ $file->id }})" wire:confirm="Delete this file? This cannot be undone." aria-label="Delete file" class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100" />
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="p-3 {{ $files->isNotEmpty() ? 'border-t border-zinc-100 dark:border-zinc-800' : '' }}">
                    <label class="flex cursor-pointer flex-col items-center gap-1 rounded-lg border border-dashed border-zinc-300 px-4 py-4 text-center text-sm text-zinc-500 transition-colors hover:border-accent hover:text-accent-content focus-within:border-accent dark:border-zinc-700 dark:text-zinc-400">
                        <flux:icon name="arrow-up-tray" class="size-5" />
                        <span wire:loading.remove wire:target="upload">Upload a file <span class="text-zinc-400">(up to 10 MB)</span></span>
                        <span wire:loading wire:target="upload">Uploading…</span>
                        <input type="file" wire:model="upload" class="sr-only" />
                    </label>
                    <flux:error name="upload" class="mt-2" />
                </div>
            </section>
        </aside>
    </div>

    <x-project-form-modal heading="Edit project" />
    <x-task-edit-modal :projects="$this->projectOptions" />
</div>
