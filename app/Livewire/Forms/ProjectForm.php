<?php

namespace App\Livewire\Forms;

use App\Enums\ProjectColor;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Create / edit form for a project, shared by the projects list and project page.
 */
class ProjectForm extends Form
{
    public string $name = '';

    public ?string $description = null;

    public string $status = 'active';

    public string $color = 'sky';

    public ?string $deadline = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'color' => ['required', Rule::enum(ProjectColor::class)],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function fillFrom(Project $project): void
    {
        $this->name = $project->name;
        $this->description = $project->description;
        $this->status = $project->status->value;
        $this->color = $project->color->value;
        $this->deadline = $project->deadline?->format('Y-m-d');
    }

    /**
     * Validate and return attributes ready to save on a Project.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $validated = $this->validate();

        return [
            'name' => trim($validated['name']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'status' => $validated['status'],
            'color' => $validated['color'],
            'deadline' => $validated['deadline'] ?: null,
        ];
    }
}
