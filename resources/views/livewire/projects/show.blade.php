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
            'notes' => $this->project->notes()->latest()->latest('id')->get(),
            'links' => $this->project->links()->oldest()->get(),
            'files' => $this->project->files()->latest()->latest('id')->get(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
    {{-- Header --}}
    <div>
        <a href="{{ route('projects.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white">
            <flux:icon name="arrow-left" variant="micro" /> Projects
        </a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="size-3.5 shrink-0 rounded-full {{ $project->color->dotClass() }}" aria-hidden="true"></span>
                    <flux:heading size="xl" level="1" class="break-words">{{ $project->name }}</flux:heading>
                    <flux:badge size="sm" :color="$project->status->color()">{{ $project->status->label() }}</flux:badge>
                    @if ($project->isArchived())
                        <flux:badge size="sm" color="zinc" icon="archive-box">Archived</flux:badge>
                    @endif
                </div>

                @if ($project->deadline)
                    <p @class(['mt-2 inline-flex items-center gap-1 text-sm', 'font-medium text-red-600 dark:text-red-400' => $project->isPastDeadline(), 'text-zinc-500 dark:text-zinc-400' => ! $project->isPastDeadline()])>
                        <flux:icon name="flag" variant="micro" />
                        {{ $project->isPastDeadline() ? 'Past deadline' : 'Deadline' }}: {{ $project->deadline->format('l, j F Y') }}
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" icon="pencil-square" wire:click="editProject">Edit</flux:button>
                <flux:button size="sm" icon="archive-box" wire:click="toggleArchive">
                    {{ $project->isArchived() ? 'Unarchive' : 'Archive' }}
                </flux:button>
                <flux:button
                    size="sm"
                    variant="danger"
                    icon="trash"
                    wire:click="deleteProject"
                    wire:confirm="Delete this project with all its notes, links and files? Its tasks stay on your to-do list without a project. This cannot be undone."
                >Delete</flux:button>
            </div>
        </div>

        @if ($project->description)
            <p class="mt-4 max-w-3xl whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $project->description }}</p>
        @endif
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Left: tasks and notes --}}
        <div class="flex flex-col gap-6 lg:col-span-2">
            <section class="flex flex-col gap-3" aria-labelledby="tasks-heading">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="tasks-heading" class="font-semibold">Tasks <span class="font-normal text-zinc-500">{{ $this->openTasks->count() }} open</span></h2>
                    <flux:switch wire:model.live="showCompletedTasks" label="Show completed" />
                </div>

                <form wire:submit="addTask" class="flex flex-col gap-2">
                    <flux:input wire:model="taskTitle" placeholder="Add a task to this project, then press Enter" aria-label="Task title" />
                    <div class="flex flex-wrap gap-2">
                        <div class="min-w-36 flex-1">
                            <flux:input type="date" wire:model="taskDueDate" aria-label="Due date" />
                        </div>
                        <div class="w-28">
                            <flux:select wire:model="taskPriority" aria-label="Priority">
                                @foreach (array_reverse(\App\Enums\Priority::cases()) as $p)
                                    <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:button type="submit" icon="plus">Add</flux:button>
                    </div>
                </form>
                <flux:error name="taskTitle" />

                @if ($this->openTasks->isNotEmpty())
                    <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($this->openTasks as $task)
                            <x-task-row :task="$task" :now="$now" :overdue="$task->groupFor($now) === \App\Models\Task::GROUP_OVERDUE" :show-project="false" wire:key="task-{{ $task->id }}" />
                        @endforeach
                    </ul>
                @else
                    <flux:text>No open tasks.</flux:text>
                @endif

                @if ($showCompletedTasks && $this->completedTasks->isNotEmpty())
                    <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($this->completedTasks as $task)
                            <x-task-row :task="$task" :now="$now" :show-project="false" wire:key="done-{{ $task->id }}" />
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="flex flex-col gap-3" aria-labelledby="notes-heading">
                <h2 id="notes-heading" class="font-semibold">Notes <span class="font-normal text-zinc-500">{{ $notes->count() }}</span></h2>

                <form wire:submit="addNote" class="flex flex-col gap-2">
                    <flux:textarea wire:model="noteBody" rows="3" placeholder="Write down progress, decisions, ideas…" aria-label="New note" />
                    <div class="flex justify-end">
                        <flux:button type="submit" size="sm" icon="plus">Add note</flux:button>
                    </div>
                </form>
                <flux:error name="noteBody" />

                @foreach ($notes as $note)
                    <article wire:key="note-{{ $note->id }}" class="group rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <time datetime="{{ $note->created_at->toIso8601String() }}" class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $note->created_at->format('D j M Y, H:i') }}
                            </time>
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteNote({{ $note->id }})" wire:confirm="Delete this note?" aria-label="Delete note" />
                        </div>
                        <p class="whitespace-pre-line break-words text-sm text-zinc-800 dark:text-zinc-200">{{ $note->body }}</p>
                    </article>
                @endforeach
            </section>
        </div>

        {{-- Right: links and files --}}
        <div class="flex flex-col gap-6">
            <section class="flex flex-col gap-3" aria-labelledby="links-heading">
                <h2 id="links-heading" class="font-semibold">Links <span class="font-normal text-zinc-500">{{ $links->count() }}</span></h2>

                @if ($links->isNotEmpty())
                    <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($links as $link)
                            <li wire:key="link-{{ $link->id }}" class="flex items-center gap-2 px-3 py-2">
                                <flux:icon name="link" variant="micro" class="shrink-0 text-zinc-400" />
                                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="min-w-0 flex-1 hover:underline">
                                    <span class="block truncate text-sm font-medium">{{ $link->displayLabel() }}</span>
                                    @if ($link->label)
                                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $link->host() }}</span>
                                    @endif
                                </a>
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteLink({{ $link->id }})" wire:confirm="Remove this link?" aria-label="Remove link" />
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form wire:submit="addLink" class="flex flex-col gap-2">
                    <flux:input wire:model="linkUrl" type="url" placeholder="https://…" aria-label="Link address" />
                    <flux:input wire:model="linkLabel" placeholder="Label (optional)" aria-label="Link label" />
                    <flux:error name="linkUrl" />
                    <flux:error name="linkLabel" />
                    <div class="flex justify-end">
                        <flux:button type="submit" size="sm" icon="plus">Add link</flux:button>
                    </div>
                </form>
            </section>

            <section class="flex flex-col gap-3" aria-labelledby="files-heading">
                <h2 id="files-heading" class="font-semibold">Files <span class="font-normal text-zinc-500">{{ $files->count() }}</span></h2>

                @if ($files->isNotEmpty())
                    <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @foreach ($files as $file)
                            <li wire:key="file-{{ $file->id }}" class="flex items-center gap-2 px-3 py-2">
                                <flux:icon name="document" variant="micro" class="shrink-0 text-zinc-400" />
                                <a href="{{ route('projects.files.download', [$project, $file]) }}" class="min-w-0 flex-1 hover:underline">
                                    <span class="block truncate text-sm font-medium">{{ $file->original_name }}</span>
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $file->humanSize() }} · {{ $file->created_at->format('j M Y') }}</span>
                                </a>
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="deleteFile({{ $file->id }})" wire:confirm="Delete this file? This cannot be undone." aria-label="Delete file" />
                            </li>
                        @endforeach
                    </ul>
                @endif

                <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-center text-sm text-zinc-500 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-400 dark:hover:bg-zinc-700/40">
                    <flux:icon name="arrow-up-tray" class="size-5" />
                    <span wire:loading.remove wire:target="upload">Upload a file (up to 10 MB)</span>
                    <span wire:loading wire:target="upload">Uploading…</span>
                    <input type="file" wire:model="upload" class="sr-only" />
                </label>
                <flux:error name="upload" />
            </section>
        </div>
    </div>

    <x-project-form-modal heading="Edit project" />
    <x-task-edit-modal :projects="$this->projectOptions" />
</div>
