<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-17 10:00:00');
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function trashedFile(Project $project, string $name = 'brief.pdf'): ProjectFile
    {
        $file = $project->files()->make(['original_name' => $name, 'size' => 5]);
        $file->path = "projects/{$project->id}/".$name;
        $file->save();
        Storage::disk('local')->put($file->path, 'hello');
        $file->delete();

        return $file;
    }

    public function test_the_trash_page_lists_my_deleted_items(): void
    {
        $project = Project::factory()->for($this->user)->create(['name' => 'Kept project']);
        Project::factory()->for($this->user)->create(['name' => 'Binned project'])->delete();
        Task::factory()->for($this->user)->create(['title' => 'Binned task'])->delete();
        $project->notes()->create(['body' => 'Binned note'])->delete();
        $project->links()->create(['url' => 'https://example.com', 'label' => 'Binned link'])->delete();
        $this->trashedFile($project, 'binned.pdf');
        Task::factory()->for($this->user)->create(['title' => 'Live task']);

        $this->get('/trash')
            ->assertOk()
            ->assertSee(['Binned project', 'Binned task', 'Binned note', 'Binned link', 'binned.pdf', 'Deleted Today'])
            ->assertDontSee('Live task');
    }

    public function test_an_empty_trash_says_so(): void
    {
        $this->get('/trash')->assertOk()->assertSee('The trash is empty');
    }

    public function test_items_can_be_restored(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $task = Task::factory()->for($this->user)->create();
        $note = $project->notes()->create(['body' => 'A note']);
        $file = $this->trashedFile($project);
        $task->delete();
        $note->delete();
        $project->delete();

        Volt::test('trash')
            ->call('restore', 'task', $task->id)
            ->call('restore', 'note', $note->id)
            ->call('restore', 'file', $file->id)
            ->call('restore', 'project', $project->id)
            ->assertSee('restored.');

        foreach ([$task, $note, $file, $project] as $model) {
            $this->assertNotSoftDeleted($model);
        }
    }

    public function test_items_can_be_deleted_forever(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $task = Task::factory()->for($this->user)->create();
        $task->delete();
        $file = $this->trashedFile($project);

        Volt::test('trash')
            ->call('deleteForever', 'task', $task->id)
            ->call('deleteForever', 'file', $file->id)
            ->assertSee('deleted forever.');

        $this->assertModelMissing($task);
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_deleting_a_project_forever_removes_its_contents_but_keeps_its_tasks(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $task = Task::factory()->for($this->user)->create(['project_id' => $project->id]);
        $note = $project->notes()->create(['body' => 'Inside']);
        $file = $this->trashedFile($project);
        $project->delete();

        Volt::test('trash')->call('deleteForever', 'project', $project->id);

        $this->assertModelMissing($project);
        $this->assertModelMissing($note);
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
        $this->assertNull($task->fresh()->project_id);
        $this->assertNotSoftDeleted($task);
    }

    public function test_empty_trash_deletes_everything_in_it_forever(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $live = Task::factory()->for($this->user)->create();
        Task::factory()->for($this->user)->count(2)->create()->each->delete();
        $file = $this->trashedFile($project);
        $project->delete();

        Volt::test('trash')->call('emptyTrash')->assertSee('Trash emptied.');

        $this->assertSame(1, Task::withTrashed()->count());
        $this->assertNotSoftDeleted($live);
        $this->assertSame(0, Project::withTrashed()->count());
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_trashed_tasks_do_not_ring_reminders(): void
    {
        $task = Task::factory()->for($this->user)->create(['remind_at' => '2026-11-17 09:00:00']);
        $task->delete();

        $this->artisan('tasks:send-reminders')->expectsOutput('Sent 0 reminder(s).');
    }

    public function test_a_task_cannot_be_put_in_a_trashed_project(): void
    {
        $project = Project::factory()->for($this->user)->create();
        $project->delete();

        Volt::test('dashboard')
            ->set('title', 'Something')
            ->set('projectId', (string) $project->id)
            ->call('addTask')
            ->assertHasErrors(['projectId']);
    }

    public function test_other_users_trash_is_off_limits(): void
    {
        $other = User::factory()->create();
        $theirTask = Task::factory()->for($other)->create(['title' => 'Their binned task']);
        $theirTask->delete();
        $theirProject = Project::factory()->for($other)->create();
        $theirNote = $theirProject->notes()->create(['body' => 'Their note']);
        $theirNote->delete();

        $this->get('/trash')->assertDontSee('Their binned task')->assertDontSee('Their note');

        Volt::test('trash')->call('restore', 'task', $theirTask->id)->assertStatus(404);
        Volt::test('trash')->call('deleteForever', 'note', $theirNote->id)->assertStatus(404);
        Volt::test('trash')->call('emptyTrash');

        $this->assertSoftDeleted($theirTask);
        $this->assertSoftDeleted($theirNote);
    }

    public function test_unknown_item_types_are_rejected(): void
    {
        Volt::test('trash')->call('restore', 'user', $this->user->id)->assertStatus(404);
    }
}
