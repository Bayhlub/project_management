<?php

namespace Tests\Feature;

use App\Enums\ProjectColor;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectLink;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function project(array $attributes = []): Project
    {
        return Project::factory()->for($this->user)->create($attributes);
    }

    // ---- Pages ------------------------------------------------------------

    public function test_guests_cannot_see_projects(): void
    {
        auth()->logout();

        $this->get('/projects')->assertRedirect('/login');
    }

    public function test_projects_pages_load(): void
    {
        $project = $this->project(['name' => 'Website redesign']);

        $this->get('/projects')->assertOk()->assertSee('Website redesign');
        $this->get("/projects/{$project->id}")->assertOk()->assertSee('Website redesign');
    }

    public function test_other_users_projects_are_hidden_and_forbidden(): void
    {
        $other = Project::factory()->create(['name' => 'Their secret project']);

        $this->get('/projects')->assertDontSee('Their secret project');
        $this->get("/projects/{$other->id}")->assertForbidden();
    }

    // ---- Create / edit / archive / delete ---------------------------------

    public function test_a_project_can_be_created(): void
    {
        Volt::test('projects.index')
            ->call('create')
            ->set('form.name', '  Mobile app  ')
            ->set('form.description', 'Flutter app for clients')
            ->set('form.status', 'on_hold')
            ->set('form.color', 'violet')
            ->set('form.deadline', '2026-12-31')
            ->call('save')
            ->assertHasNoErrors();

        $project = $this->user->projects()->sole();
        $this->assertSame('Mobile app', $project->name);
        $this->assertSame(ProjectStatus::OnHold, $project->status);
        $this->assertSame(ProjectColor::Violet, $project->color);
        $this->assertSame('2026-12-31', $project->deadline->format('Y-m-d'));
    }

    public function test_a_project_needs_a_valid_name_status_and_color(): void
    {
        Volt::test('projects.index')
            ->set('form.name', '')
            ->set('form.status', 'nonsense')
            ->set('form.color', 'plaid')
            ->call('save')
            ->assertHasErrors(['form.name', 'form.status', 'form.color']);

        $this->assertSame(0, Project::count());
    }

    public function test_projects_list_can_be_filtered(): void
    {
        $this->project(['name' => 'Running project']);
        $this->project(['name' => 'Paused project', 'status' => ProjectStatus::OnHold]);
        Project::factory()->for($this->user)->archived()->create(['name' => 'Old project']);

        Volt::test('projects.index')
            ->assertSee('Running project')
            ->assertSee('Paused project')
            ->assertDontSee('Old project')
            ->set('show', 'on_hold')
            ->assertSee('Paused project')
            ->assertDontSee('Running project')
            ->set('show', 'archived')
            ->assertSee('Old project')
            ->assertDontSee('Paused project');
    }

    public function test_a_project_can_be_edited(): void
    {
        $project = $this->project(['name' => 'Old name']);

        Volt::test('projects.show', ['project' => $project])
            ->call('editProject')
            ->assertSet('form.name', 'Old name')
            ->set('form.name', 'New name')
            ->set('form.status', 'done')
            ->call('save')
            ->assertHasNoErrors();

        $project->refresh();
        $this->assertSame('New name', $project->name);
        $this->assertSame(ProjectStatus::Done, $project->status);
    }

    public function test_a_project_can_be_archived_and_unarchived(): void
    {
        $project = $this->project();

        $component = Volt::test('projects.show', ['project' => $project])->call('toggleArchive');
        $this->assertNotNull($project->fresh()->archived_at);

        $component->call('toggleArchive');
        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_deleting_a_project_keeps_its_tasks_and_removes_its_files(): void
    {
        Storage::fake('local');
        $project = $this->project();
        $task = Task::factory()->for($this->user)->create(['project_id' => $project->id]);

        Volt::test('projects.show', ['project' => $project])
            ->set('upload', UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'));
        $path = $project->files()->sole()->path;
        Storage::disk('local')->assertExists($path);

        Volt::test('projects.show', ['project' => $project])
            ->call('deleteProject')
            ->assertRedirect(route('projects.index'));

        $this->assertModelMissing($project);
        $this->assertSame(0, ProjectFile::count());
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($task->fresh()->project_id);
    }

    // ---- Tasks on the project page ----------------------------------------

    public function test_a_task_can_be_added_from_the_project_page(): void
    {
        $project = $this->project();

        Volt::test('projects.show', ['project' => $project])
            ->set('taskTitle', 'Write the brief')
            ->call('addTask')
            ->assertHasNoErrors()
            ->assertSee('Write the brief');

        $task = $this->user->tasks()->sole();
        $this->assertSame($project->id, $task->project_id);
    }

    // ---- Notes ------------------------------------------------------------

    public function test_notes_can_be_added_and_deleted(): void
    {
        $project = $this->project();

        $component = Volt::test('projects.show', ['project' => $project])
            ->set('noteBody', 'Client approved the mockups.')
            ->call('addNote')
            ->assertHasNoErrors()
            ->assertSee('Client approved the mockups.');

        $note = $project->notes()->sole();

        $component->call('deleteNote', $note->id);
        $this->assertModelMissing($note);
    }

    public function test_an_empty_note_is_rejected(): void
    {
        Volt::test('projects.show', ['project' => $this->project()])
            ->set('noteBody', '')
            ->call('addNote')
            ->assertHasErrors(['noteBody' => 'required']);
    }

    // ---- Links ------------------------------------------------------------

    public function test_links_can_be_added_and_deleted(): void
    {
        $project = $this->project();

        $component = Volt::test('projects.show', ['project' => $project])
            ->set('linkUrl', 'https://github.com/example/repo')
            ->set('linkLabel', 'Repository')
            ->call('addLink')
            ->assertHasNoErrors()
            ->assertSee('Repository')
            ->assertSee('github.com');

        $link = $project->links()->sole();

        $component->call('deleteLink', $link->id);
        $this->assertModelMissing($link);
    }

    public function test_only_web_links_are_accepted(): void
    {
        Volt::test('projects.show', ['project' => $this->project()])
            ->set('linkUrl', 'javascript:alert(1)')
            ->call('addLink')
            ->assertHasErrors(['linkUrl']);

        $this->assertSame(0, ProjectLink::count());
    }

    // ---- Files ------------------------------------------------------------

    public function test_a_file_can_be_uploaded_downloaded_and_deleted(): void
    {
        Storage::fake('local');
        $project = $this->project();

        $component = Volt::test('projects.show', ['project' => $project])
            ->set('upload', UploadedFile::fake()->create('Quote v2.pdf', 200, 'application/pdf'))
            ->assertHasNoErrors()
            ->assertSee('Quote v2.pdf');

        $file = $project->files()->sole();
        $this->assertSame('Quote v2.pdf', $file->original_name);
        $this->assertStringStartsWith("projects/{$project->id}/", $file->path);
        Storage::disk('local')->assertExists($file->path);

        $this->get(route('projects.files.download', [$project, $file]))
            ->assertOk()
            ->assertDownload('Quote v2.pdf');

        $component->call('deleteFile', $file->id);
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_files_over_10_mb_are_rejected(): void
    {
        Storage::fake('local');

        Volt::test('projects.show', ['project' => $this->project()])
            ->set('upload', UploadedFile::fake()->create('huge.zip', 11 * 1024))
            ->assertHasErrors(['upload']);

        $this->assertSame(0, ProjectFile::count());
    }

    public function test_other_users_files_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $theirProject = Project::factory()->create();
        $theirFile = $theirProject->files()->make(['original_name' => 'secret.txt', 'size' => 5]);
        $theirFile->path = 'projects/'.$theirProject->id.'/secret.txt';
        $theirFile->save();
        Storage::disk('local')->put($theirFile->path, 'hello');

        $this->get(route('projects.files.download', [$theirProject, $theirFile]))->assertForbidden();

        // Pairing one of my projects with their file must not work either.
        $mine = $this->project();
        $this->get("/projects/{$mine->id}/files/{$theirFile->id}")->assertNotFound();
    }

    public function test_items_of_other_projects_cannot_be_deleted_through_my_project(): void
    {
        $mine = $this->project();
        $theirs = Project::factory()->create();
        $note = $theirs->notes()->create(['body' => 'Not yours']);
        $link = $theirs->links()->create(['url' => 'https://example.com']);

        Volt::test('projects.show', ['project' => $mine])->call('deleteNote', $note->id)->assertStatus(404);
        Volt::test('projects.show', ['project' => $mine])->call('deleteLink', $link->id)->assertStatus(404);

        $this->assertModelExists($note);
        $this->assertModelExists($link);
    }
}
