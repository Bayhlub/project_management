<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TaskDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_a_task_can_be_added(): void
    {
        Volt::test('dashboard')
            ->set('title', '  Send invoice  ')
            ->set('dueDate', '2026-11-20')
            ->set('priority', Priority::High->value)
            ->call('addTask')
            ->assertHasNoErrors()
            ->assertSet('title', '')
            ->assertSee('Send invoice');

        $task = $this->user->tasks()->sole();
        $this->assertSame('Send invoice', $task->title);
        $this->assertSame('2026-11-20', $task->due_date->format('Y-m-d'));
        $this->assertSame(Priority::High, $task->priority);
        $this->assertNull($task->completed_at);
    }

    public function test_a_task_needs_a_title(): void
    {
        Volt::test('dashboard')
            ->set('title', '')
            ->call('addTask')
            ->assertHasErrors(['title' => 'required']);

        $this->assertSame(0, Task::count());
    }

    public function test_open_tasks_are_grouped_by_due_date(): void
    {
        $make = fn (string $title, ?string $date, ?string $time = null) => Task::factory()
            ->for($this->user)
            ->dueOn($date, $time)
            ->create(['title' => $title]);

        $make('Yesterday task', '2026-11-16');
        $make('Earlier this morning', '2026-11-17', '09:00');
        $make('Later today', '2026-11-17', '15:00');
        $make('Today any time', '2026-11-17');
        $make('Tomorrow task', '2026-11-18');
        $make('Someday task', null);

        $now = now();
        $groups = Task::all()->mapWithKeys(fn (Task $t) => [$t->title => $t->groupFor($now)])->all();

        $this->assertSame([
            'Yesterday task' => 'overdue',
            'Earlier this morning' => 'overdue',
            'Later today' => 'today',
            'Today any time' => 'today',
            'Tomorrow task' => 'upcoming',
            'Someday task' => 'someday',
        ], $groups);

        Volt::test('dashboard')->assertSeeInOrder([
            'Overdue', 'Yesterday task', 'Earlier this morning',
            'Today', 'Later today', 'Today any time',
            'Upcoming', 'Tomorrow task',
            'No date', 'Someday task',
        ]);
    }

    public function test_a_task_can_be_completed_and_reopened(): void
    {
        $task = Task::factory()->for($this->user)->create();

        $component = Volt::test('dashboard')->call('toggle', $task->id);
        $this->assertNotNull($task->fresh()->completed_at);

        $component->call('toggle', $task->id);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_completed_tasks_are_hidden_unless_requested(): void
    {
        Task::factory()->for($this->user)->completed()->create(['title' => 'Finished thing']);
        Task::factory()->for($this->user)->create(['title' => 'Open thing']);

        Volt::test('dashboard')
            ->assertSee('Open thing')
            ->assertDontSee('Finished thing')
            ->set('showCompleted', true)
            ->assertSee('Finished thing');
    }

    public function test_tasks_can_be_filtered_by_priority(): void
    {
        Task::factory()->for($this->user)->create(['title' => 'Urgent one', 'priority' => Priority::High]);
        Task::factory()->for($this->user)->create(['title' => 'Relaxed one', 'priority' => Priority::Low]);

        Volt::test('dashboard')
            ->set('priorityFilter', (string) Priority::High->value)
            ->assertSee('Urgent one')
            ->assertDontSee('Relaxed one');
    }

    public function test_a_task_can_be_edited(): void
    {
        $task = Task::factory()->for($this->user)->create(['title' => 'Old title']);

        Volt::test('dashboard')
            ->call('edit', $task->id)
            ->assertSet('editTitle', 'Old title')
            ->set('editTitle', 'New title')
            ->set('editNotes', 'Some notes')
            ->set('editDueDate', '2026-11-21')
            ->set('editDueTime', '14:30')
            ->set('editPriority', Priority::Low->value)
            ->call('saveEdit')
            ->assertHasNoErrors();

        $task->refresh();
        $this->assertSame('New title', $task->title);
        $this->assertSame('Some notes', $task->notes);
        $this->assertSame('2026-11-21', $task->due_date->format('Y-m-d'));
        $this->assertSame('14:30', $task->dueTimeShort());
        $this->assertSame(Priority::Low, $task->priority);
    }

    public function test_a_due_time_needs_a_due_date(): void
    {
        $task = Task::factory()->for($this->user)->create();

        Volt::test('dashboard')
            ->call('edit', $task->id)
            ->set('editDueDate', '')
            ->set('editDueTime', '14:30')
            ->call('saveEdit')
            ->assertHasErrors(['editDueDate']);
    }

    public function test_deleting_a_task_moves_it_to_the_trash_with_undo(): void
    {
        $task = Task::factory()->for($this->user)->create(['title' => 'Old idea']);

        $component = Volt::test('dashboard')
            ->call('delete', $task->id)
            ->assertSee('“Old idea” moved to trash.')
            ->assertDontSee('Old idea</p>', false);

        $this->assertSoftDeleted($task);

        $component->call('undoTrash')->assertSet('undoLabel', null);
        $this->assertNotSoftDeleted($task);
    }

    public function test_a_task_can_be_added_to_a_project(): void
    {
        $project = Project::factory()->for($this->user)->create(['name' => 'Website']);

        Volt::test('dashboard')
            ->set('title', 'Fix footer')
            ->set('projectId', (string) $project->id)
            ->call('addTask')
            ->assertHasNoErrors()
            ->assertSee('Website');

        $this->assertSame($project->id, $this->user->tasks()->sole()->project_id);
    }

    public function test_a_task_cannot_be_put_in_someone_elses_project(): void
    {
        $theirs = Project::factory()->create();
        $task = Task::factory()->for($this->user)->create();

        Volt::test('dashboard')
            ->set('title', 'Sneaky')
            ->set('projectId', (string) $theirs->id)
            ->call('addTask')
            ->assertHasErrors(['projectId']);

        Volt::test('dashboard')
            ->call('edit', $task->id)
            ->set('editProjectId', (string) $theirs->id)
            ->call('saveEdit')
            ->assertHasErrors(['editProjectId']);

        $this->assertSame(1, Task::count());
        $this->assertNull($task->fresh()->project_id);
    }

    public function test_tasks_can_be_filtered_by_project(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->for($this->user)->create(['title' => 'In the project', 'project_id' => $project->id]);
        Task::factory()->for($this->user)->create(['title' => 'Loose task']);

        Volt::test('dashboard')
            ->set('projectFilter', (string) $project->id)
            ->assertSee('In the project')
            ->assertDontSee('Loose task')
            ->set('projectFilter', 'none')
            ->assertSee('Loose task')
            ->assertDontSee('In the project');
    }

    public function test_tasks_show_when_they_were_added_and_when_they_are_due(): void
    {
        Carbon::setTestNow('2026-11-16 09:15:00');
        $older = Task::factory()->for($this->user)->dueOn('2026-11-18')->create(['title' => 'Older task']);
        Carbon::setTestNow('2026-11-17 10:00:00');

        Volt::test('dashboard')
            ->set('title', 'Fresh task')
            ->set('dueDate', '2026-11-19')
            ->call('addTask')
            ->assertSeeInOrder(['Older task', 'Due Tomorrow', 'Added Yesterday'])
            ->assertSeeInOrder(['Fresh task', 'Due Thu 19 Nov', 'Added Today']);

        Volt::test('dashboard')
            ->call('edit', $older->id)
            ->assertSet('editAddedAt', 'Monday 16 November 2026 at 09:15')
            ->assertSee('Added Monday 16 November 2026 at 09:15');
    }

    public function test_finished_tasks_show_when_they_were_done(): void
    {
        Task::factory()->for($this->user)->completed()->create(['title' => 'Finished thing']);

        Volt::test('dashboard')
            ->set('showCompleted', true)
            ->assertSeeInOrder(['Finished thing', 'Done Today']);
    }

    public function test_week_strip_counts_tasks_and_filters_by_day(): void
    {
        $make = fn (string $title, string $date) => Task::factory()->for($this->user)->dueOn($date)->create(['title' => $title]);
        $make('Late thing', '2026-11-16');
        $make('Today thing', '2026-11-17');
        $make('Tomorrow thing', '2026-11-18');
        $make('Friday thing', '2026-11-20');
        $make('Far thing', '2026-12-30');

        $component = Volt::test('dashboard');

        $counts = collect($component->instance()->strip)->pluck('count', 'key')->all();
        $this->assertSame(1, $counts['overdue']);
        $this->assertSame(1, $counts['today']);
        $this->assertSame(1, $counts['2026-11-18']);
        $this->assertSame(1, $counts['2026-11-20']);
        $this->assertSame(1, $counts['later']);

        $component->call('pickDay', '2026-11-18')
            ->assertSet('day', '2026-11-18')
            ->assertSee('Tomorrow thing')
            ->assertDontSee('Friday thing')
            ->assertDontSee('Late thing');

        $component->call('pickDay', 'later')->assertSee('Far thing')->assertDontSee('Tomorrow thing');

        // Clicking the active day again shows everything.
        $component->call('pickDay', 'later')->assertSet('day', '')->assertSee('Late thing')->assertSee('Far thing');
    }

    public function test_other_users_tasks_are_not_shown(): void
    {
        Task::factory()->create(['title' => 'Someone else task']);

        Volt::test('dashboard')->assertDontSee('Someone else task');
    }

    public function test_other_users_tasks_cannot_be_changed(): void
    {
        $other = Task::factory()->create(['title' => 'Not mine']);

        foreach (['toggle', 'edit', 'delete'] as $action) {
            Volt::test('dashboard')->call($action, $other->id)->assertStatus(404);
        }

        $other->refresh();
        $this->assertNull($other->completed_at);
        $this->assertSame('Not mine', $other->title);
    }
}
