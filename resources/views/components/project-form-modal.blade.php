@props(['heading' => 'Project'])

{{-- Needs a Livewire component with a public ProjectForm $form and a save() action. --}}
<flux:modal name="project-form" class="w-full md:w-[34rem]">
    <form wire:submit="save" class="space-y-5">
        <flux:heading size="lg">{{ $heading }}</flux:heading>

        <flux:input wire:model="form.name" label="Name" />
        <flux:textarea wire:model="form.description" label="Description" rows="3" placeholder="What is this project about?" />

        <div class="grid grid-cols-2 gap-3">
            <flux:select wire:model="form.status" label="Status">
                @foreach (\App\Enums\ProjectStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="date" wire:model="form.deadline" label="Deadline (optional)" />
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-white">Color</legend>
            <div class="flex flex-wrap gap-2.5">
                @foreach (\App\Enums\ProjectColor::cases() as $color)
                    <label class="cursor-pointer" title="{{ $color->label() }}">
                        <input type="radio" wire:model="form.color" value="{{ $color->value }}" class="peer sr-only" aria-label="{{ $color->label() }}" />
                        <span class="block size-7 rounded-full {{ $color->dotClass() }} ring-zinc-900 ring-offset-2 peer-checked:ring-2 peer-focus-visible:ring-2 dark:ring-white dark:ring-offset-zinc-800"></span>
                    </label>
                @endforeach
            </div>
            <flux:error name="form.color" />
        </fieldset>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">Save</flux:button>
        </div>
    </form>
</flux:modal>
