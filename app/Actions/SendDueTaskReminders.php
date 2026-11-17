<?php

namespace App\Actions;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminder;
use Throwable;

/**
 * Sends every reminder whose time has come, exactly once.
 *
 * Runs every minute from the scheduler (all users) and also whenever an open
 * page checks the bell (just that user), so alarms still ring while the site
 * is open even if the scheduler isn't running.
 */
class SendDueTaskReminders
{
    public function handle(?User $user = null): int
    {
        $sent = 0;

        Task::query()
            ->with(['user', 'project'])
            ->whereNotNull('remind_at')
            ->whereNull('reminded_at')
            ->whereNull('completed_at')
            ->where('remind_at', '<=', now())
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('remind_at')
            ->limit(500)
            ->get()
            ->each(function (Task $task) use (&$sent) {
                // Claim the task first so two runs at the same moment can't both send it.
                $claimed = Task::whereKey($task->id)
                    ->whereNull('reminded_at')
                    ->update(['reminded_at' => now()]);

                if ($claimed === 0) {
                    return;
                }

                try {
                    $task->user->notify(new TaskReminder($task));
                    $sent++;
                } catch (Throwable $e) {
                    // The in-app alarm is stored before the email is sent, so a mail
                    // failure must not re-send it. Log it and move on.
                    report($e);
                }
            });

        return $sent;
    }
}
