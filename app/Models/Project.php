<?php

namespace App\Models;

use App\Enums\ProjectColor;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * user_id and archived_at are set explicitly, never mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'status',
        'color',
        'deadline',
    ];

    protected $attributes = [
        'status' => 'active',
        'color' => 'sky',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'color' => ProjectColor::class,
            'deadline' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Moving to the trash keeps everything. Deleting forever cascades the database
        // rows, but uploaded files have to be removed from disk by hand.
        static::forceDeleting(function (Project $project) {
            Storage::disk('local')->deleteDirectory($project->filesDirectory());
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(ProjectLink::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function scopeNotArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isPastDeadline(): bool
    {
        return $this->deadline !== null
            && $this->status !== ProjectStatus::Done
            && $this->deadline->lt(today());
    }

    public function filesDirectory(): string
    {
        return 'projects/'.$this->id;
    }
}
