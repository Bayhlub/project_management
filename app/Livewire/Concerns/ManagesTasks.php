<?php

namespace App\Livewire\Concerns;

use App\Enums\Priority;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Tick off, edit and delete tasks. Used by every page that shows <x-task-row>
 * and <x-task-edit-modal>. All lookups are scoped to the logged-in user.
 */
trait ManagesTasks
{
    public ?int $editingId = null;

    public string $editTitle = '';

    public ?string $editNotes = null;

    public ?string $editDueDate = null;

    public ?string $editDueTime = null;

    public int $editPriority = 2;

    public ?string $editProjectId = null;

    /** "Y-m-d\TH:i", the format of <input type="datetime-local"> */
    public ?string $editRemindAt = null;

    /**
     * Re-render when the bell reports that reminders just rang,
     * so the task rows stop showing them as pending.
     */
    #[On('reminders-arrived')]
    public function refreshAfterReminders(): void {}

    public function toggle(int $id): void
    {
        $task = $this->findTask($id);
        $task->completed_at = $task->isCompleted() ? null : now();
        $task->save();
    }

    public function edit(int $id): void
    {
        $task = $this->findTask($id);

        $this->resetValidation();
        $this->editingId = $task->id;
        $this->editTitle = $task->title;
        $this->editNotes = $task->notes;
        $this->editDueDate = $task->due_date?->format('Y-m-d');
        $this->editDueTime = $task->dueTimeShort();
        $this->editPriority = $task->priority->value;
        $this->editProjectId = $task->project_id ? (string) $task->project_id : null;
        $this->editRemindAt = $task->remind_at?->format('Y-m-d\TH:i');

        Flux::modal('edit-task')->show();
    }

    /**
     * Fill the reminder field from a shortcut button in the edit dialog.
     */
    public function reminderPreset(string $preset): void
    {
        $this->resetValidation('editRemindAt');

        if ($preset === 'clear') {
            $this->editRemindAt = null;

            return;
        }

        if ($preset === 'in_1h') {
            $this->editRemindAt = now()->addHour()->format('Y-m-d\TH:i');

            return;
        }

        if ($preset === 'tomorrow') {
            $this->editRemindAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');

            return;
        }

        // The rest are relative to the due date (09:00 when the task has no due time).
        try {
            $due = $this->editDueDate
                ? Carbon::createFromFormat('Y-m-d H:i', $this->editDueDate.' '.($this->editDueTime ?: '09:00'))
                : null;
        } catch (\Throwable) {
            $due = null;
        }

        if (! $due) {
            $this->addError('editRemindAt', 'Set a due date first.');

            return;
        }

        $at = match ($preset) {
            'at_due' => $due,
            '1h_before' => $due->subHour(),
            '1d_before' => $due->subDay(),
            default => null,
        };

        if ($at) {
            $this->editRemindAt = $at->format('Y-m-d\TH:i');
        }
    }

    public function saveEdit(): void
    {
        $validated = $this->validate([
            'editTitle' => ['required', 'string', 'max:255'],
            'editNotes' => ['nullable', 'string', 'max:5000'],
            'editDueDate' => ['nullable', 'date_format:Y-m-d', 'required_with:editDueTime'],
            'editDueTime' => ['nullable', 'date_format:H:i'],
            'editPriority' => ['required', Rule::enum(Priority::class)],
            'editProjectId' => ['nullable', $this->ownProjectRule()],
            'editRemindAt' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ], [
            'editDueDate.required_with' => 'Pick a due date to go with the time.',
            'editRemindAt.date_format' => 'Pick a reminder date and time.',
        ], [
            'editTitle' => 'title',
            'editNotes' => 'notes',
            'editDueDate' => 'due date',
            'editDueTime' => 'due time',
            'editPriority' => 'priority',
            'editProjectId' => 'project',
        ]);

        $this->findTask($this->editingId)->update([
            'title' => trim($validated['editTitle']),
            'notes' => filled($validated['editNotes']) ? $validated['editNotes'] : null,
            'due_date' => $validated['editDueDate'] ?: null,
            'due_time' => $validated['editDueTime'] ?: null,
            'priority' => $validated['editPriority'],
            'project_id' => $validated['editProjectId'] ?: null,
            'remind_at' => $validated['editRemindAt']
                ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['editRemindAt'])->startOfMinute()
                : null,
        ]);

        Flux::modal('edit-task')->close();
        $this->reset('editingId');
    }

    public function delete(int $id): void
    {
        $this->findTask($id)->delete();
    }

    /**
     * Projects a task can be put in: the user's unarchived projects, plus the
     * one the task being edited is already in (even if that one is archived).
     */
    #[Computed]
    public function projectOptions(): Collection
    {
        return auth()->user()->projects()
            ->where(fn ($query) => $query
                ->whereNull('archived_at')
                ->when($this->editProjectId, fn ($query) => $query->orWhere('id', (int) $this->editProjectId)))
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'archived_at']);
    }

    protected function findTask(?int $id): Task
    {
        return auth()->user()->tasks()->findOrFail($id);
    }

    /**
     * Rule that only accepts the id of a project owned by the logged-in user.
     */
    protected function ownProjectRule(): Exists
    {
        return Rule::exists('projects', 'id')->where('user_id', auth()->id());
    }
}
