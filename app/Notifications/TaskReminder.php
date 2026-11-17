<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The alarm for a task: stored for the bell in the app, and sent by email.
 */
class TaskReminder extends Notification
{
    public function __construct(public Task $task) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Reminder: '.$this->task->title)
            ->greeting('Reminder')
            ->line($this->task->title);

        if ($details = $this->details()) {
            $mail->line($details);
        }

        if ($this->task->notes) {
            $mail->line($this->task->notes);
        }

        return $mail->action('Open my to-do list', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'body' => $this->details() ?: 'Reminder',
            'url' => $this->url(),
        ];
    }

    /**
     * e.g. "Due Today 15:00 · Client website"
     */
    protected function details(): string
    {
        $parts = [];

        if ($due = $this->task->dueLabel(now())) {
            $parts[] = 'Due '.$due;
        }

        if ($this->task->project) {
            $parts[] = $this->task->project->name;
        }

        return implode(' · ', $parts);
    }

    protected function url(): string
    {
        return $this->task->project
            ? route('projects.show', $this->task->project)
            : route('dashboard');
    }
}
