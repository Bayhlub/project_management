<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-17 10:00:00');
        $this->user = User::factory()->create();
    }

    protected function task(array $attributes = []): Task
    {
        return Task::factory()->for($this->user)->create($attributes);
    }

    // ---- Sending ----------------------------------------------------------

    public function test_due_reminders_are_sent_once(): void
    {
        Notification::fake();
        $task = $this->task(['title' => 'Call the bank', 'remind_at' => '2026-11-17 09:59:00']);

        $this->artisan('tasks:send-reminders')->expectsOutput('Sent 1 reminder(s).')->assertSuccessful();
        $this->artisan('tasks:send-reminders')->expectsOutput('Sent 0 reminder(s).')->assertSuccessful();

        Notification::assertSentToTimes($this->user, TaskReminder::class, 1);
        $this->assertNotNull($task->fresh()->reminded_at);
    }

    public function test_future_completed_and_unset_reminders_are_not_sent(): void
    {
        Notification::fake();
        $this->task(['remind_at' => '2026-11-17 10:05:00']);
        $this->task(['remind_at' => '2026-11-17 09:00:00', 'completed_at' => now()]);
        $this->task(['remind_at' => null]);

        $this->artisan('tasks:send-reminders')->expectsOutput('Sent 0 reminder(s).');

        Notification::assertNothingSent();
    }

    public function test_reminder_is_stored_for_the_bell_and_emailed(): void
    {
        $project = Project::factory()->for($this->user)->create(['name' => 'Client website']);
        $this->task([
            'title' => 'Send invoice',
            'due_date' => '2026-11-17',
            'due_time' => '15:00',
            'project_id' => $project->id,
            'remind_at' => '2026-11-17 10:00:00',
        ]);

        $this->artisan('tasks:send-reminders');

        $notification = $this->user->notifications()->sole();
        $this->assertSame('Send invoice', $notification->data['title']);
        $this->assertSame('Due Today 15:00 · Client website', $notification->data['body']);
        $this->assertSame(route('projects.show', $project), $notification->data['url']);

        $mail = (new TaskReminder($this->user->tasks()->sole()))->toMail($this->user);
        $this->assertSame('Reminder: Send invoice', $mail->subject);
    }

    public function test_changing_the_reminder_time_arms_it_again(): void
    {
        $task = $this->task(['remind_at' => '2026-11-17 09:00:00']);
        $task->forceFill(['reminded_at' => now()])->save();

        $task->update(['title' => 'Renamed']);
        $this->assertNotNull($task->fresh()->reminded_at, 'Unrelated edits must not re-arm');

        $task->update(['remind_at' => '2026-11-18 09:00:00']);
        $this->assertNull($task->fresh()->reminded_at);
    }

    // ---- Setting a reminder -----------------------------------------------

    public function test_a_reminder_can_be_set_and_cleared_in_the_edit_dialog(): void
    {
        $this->actingAs($this->user);
        $task = $this->task();

        Volt::test('dashboard')
            ->call('edit', $task->id)
            ->set('editRemindAt', '2026-11-18T08:30')
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSee('Tomorrow 08:30');

        $this->assertSame('2026-11-18 08:30:00', $task->fresh()->remind_at->format('Y-m-d H:i:s'));

        Volt::test('dashboard')
            ->call('edit', $task->id)
            ->assertSet('editRemindAt', '2026-11-18T08:30')
            ->call('reminderPreset', 'clear')
            ->call('saveEdit');

        $this->assertNull($task->fresh()->remind_at);
    }

    public function test_reminder_shortcuts(): void
    {
        $this->actingAs($this->user);
        $task = $this->task(['due_date' => '2026-11-20', 'due_time' => '14:00']);

        $component = Volt::test('dashboard')->call('edit', $task->id);

        $component->call('reminderPreset', 'at_due')->assertSet('editRemindAt', '2026-11-20T14:00');
        $component->call('reminderPreset', '1h_before')->assertSet('editRemindAt', '2026-11-20T13:00');
        $component->call('reminderPreset', '1d_before')->assertSet('editRemindAt', '2026-11-19T14:00');
        $component->call('reminderPreset', 'in_1h')->assertSet('editRemindAt', '2026-11-17T11:00');
        $component->call('reminderPreset', 'tomorrow')->assertSet('editRemindAt', '2026-11-18T09:00');

        // Without a due date, the due-relative shortcuts explain what's missing.
        $component->set('editDueDate', '')->call('reminderPreset', 'at_due')->assertHasErrors(['editRemindAt']);
    }

    // ---- The bell ---------------------------------------------------------

    public function test_the_bell_sends_due_reminders_and_pops_them_up(): void
    {
        $this->actingAs($this->user);
        $this->task(['title' => 'Stand-up meeting', 'remind_at' => '2026-11-17 10:00:00']);

        Volt::test('reminder-bell')
            ->call('check')
            ->assertDispatched('reminders-arrived', fn ($name, $params) => $params['reminders'][0]['title'] === 'Stand-up meeting')
            ->assertSee('Stand-up meeting');

        // Already popped up: the next check stays quiet.
        Carbon::setTestNow('2026-11-17 10:01:00');
        Volt::test('reminder-bell')->call('check')->assertNotDispatched('reminders-arrived');
    }

    public function test_opening_a_reminder_marks_it_read(): void
    {
        $this->actingAs($this->user);
        $this->task(['remind_at' => '2026-11-17 10:00:00']);
        $this->artisan('tasks:send-reminders');
        $notification = $this->user->notifications()->sole();

        Volt::test('reminder-bell')
            ->call('open', $notification->id)
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $this->actingAs($this->user);
        $this->task(['remind_at' => '2026-11-17 09:00:00']);
        $this->task(['remind_at' => '2026-11-17 09:30:00']);
        $this->artisan('tasks:send-reminders');

        Volt::test('reminder-bell')->call('markAllRead');

        $this->assertSame(0, $this->user->unreadNotifications()->count());
    }

    public function test_the_bell_only_handles_my_reminders(): void
    {
        $this->actingAs($this->user);
        $other = User::factory()->create();
        $theirTask = Task::factory()->for($other)->create(['remind_at' => '2026-11-17 09:00:00']);

        Volt::test('reminder-bell')->call('check')->assertNotDispatched('reminders-arrived');
        $this->assertNull($theirTask->fresh()->reminded_at);

        $this->artisan('tasks:send-reminders');
        $theirNotification = $other->notifications()->sole();

        Volt::test('reminder-bell')->call('open', $theirNotification->id)->assertStatus(404);
        $this->assertNull($theirNotification->fresh()->read_at);
    }
}
