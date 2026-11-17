@props(['projects', 'added' => null])

{{-- Needs a Livewire component that uses App\Livewire\Concerns\ManagesTasks. --}}
<flux:modal name="edit-task" class="w-full md:w-[32rem]">
    <form wire:submit="saveEdit" class="space-y-5">
        <div>
            <flux:heading size="lg">Edit task</flux:heading>
            @if ($added)
                <flux:text class="mt-1">Added {{ $added }}</flux:text>
            @endif
        </div>

        <flux:input wire:model="editTitle" label="Title" />
        <flux:textarea wire:model="editNotes" label="Notes" rows="3" />

        <div class="grid grid-cols-2 gap-3">
            <flux:input type="date" wire:model="editDueDate" label="Due date" />
            <flux:input type="time" wire:model="editDueTime" label="Time (optional)" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <flux:select wire:model="editPriority" label="Priority">
                @foreach (array_reverse(\App\Enums\Priority::cases()) as $p)
                    <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="editProjectId" label="Project">
                <flux:select.option value="">No project</flux:select.option>
                @foreach ($projects as $project)
                    <flux:select.option value="{{ $project->id }}">
                        {{ $project->name }}{{ $project->archived_at ? ' (archived)' : '' }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div>
            <flux:input type="datetime-local" wire:model="editRemindAt" label="Remind me at" />
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach (['in_1h' => 'In 1 hour', 'tomorrow' => 'Tomorrow 9:00', 'at_due' => 'At due time', '1h_before' => '1 hour before', '1d_before' => '1 day before'] as $preset => $label)
                    <flux:button size="xs" variant="filled" wire:click="reminderPreset('{{ $preset }}')">{{ $label }}</flux:button>
                @endforeach
                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="reminderPreset('clear')">No reminder</flux:button>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">Save</flux:button>
        </div>
    </form>
</flux:modal>
