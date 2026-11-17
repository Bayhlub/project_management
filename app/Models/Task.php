<?php

namespace App\Models;

use App\Enums\Priority;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const GROUP_OVERDUE = 'overdue';

    public const GROUP_TODAY = 'today';

    public const GROUP_UPCOMING = 'upcoming';

    public const GROUP_SOMEDAY = 'someday';

    /**
     * user_id and completed_at are set explicitly, never mass assigned.
     * project_id must always be validated as one of the user's own projects.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'title',
        'notes',
        'priority',
        'due_date',
        'due_time',
        'remind_at',
    ];

    protected $attributes = [
        'priority' => 2,
    ];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'remind_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A new or changed reminder time should ring again.
        static::saving(function (Task $task) {
            if ($task->isDirty('remind_at')) {
                $task->reminded_at = null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * The due time as "H:i", or null when the task has no time.
     */
    public function dueTimeShort(): ?string
    {
        return $this->due_time ? substr($this->due_time, 0, 5) : null;
    }

    /**
     * Which dashboard section an open task belongs in.
     */
    public function groupFor(Carbon $now): string
    {
        if ($this->due_date === null) {
            return self::GROUP_SOMEDAY;
        }

        $today = $now->copy()->startOfDay();

        if ($this->due_date->lt($today)) {
            return self::GROUP_OVERDUE;
        }

        if ($this->due_date->isSameDay($today)) {
            if ($this->due_time !== null && $this->due_date->copy()->setTimeFromTimeString($this->due_time)->lt($now)) {
                return self::GROUP_OVERDUE;
            }

            return self::GROUP_TODAY;
        }

        return self::GROUP_UPCOMING;
    }

    /**
     * A short, human label for the due date, e.g. "Today 14:30" or "Fri 21 Nov".
     */
    public function dueLabel(Carbon $now): ?string
    {
        if ($this->due_date === null) {
            return null;
        }

        return trim(self::dayLabel($this->due_date, $now).' '.$this->dueTimeShort());
    }

    /**
     * The reminder time as a short label, e.g. "Today 14:15".
     */
    public function reminderLabel(Carbon $now): ?string
    {
        if ($this->remind_at === null) {
            return null;
        }

        return self::dayLabel($this->remind_at, $now).' '.$this->remind_at->format('H:i');
    }

    public function hasPendingReminder(): bool
    {
        return $this->remind_at !== null && $this->reminded_at === null && ! $this->isCompleted();
    }

    /**
     * "Today", "Tomorrow", "Yesterday", "Fri 21 Nov" or "21 Nov 2027".
     */
    public static function dayLabel(Carbon $date, Carbon $now): string
    {
        $today = $now->copy()->startOfDay();

        return match (true) {
            $date->isSameDay($today) => 'Today',
            $date->isSameDay($today->copy()->addDay()) => 'Tomorrow',
            $date->isSameDay($today->copy()->subDay()) => 'Yesterday',
            $date->isSameYear($today) => $date->format('D j M'),
            default => $date->format('j M Y'),
        };
    }
}
