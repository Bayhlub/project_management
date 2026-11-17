<?php

namespace App\Support;

use App\Models\ProjectFile;
use App\Models\ProjectLink;
use App\Models\ProjectNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The logged-in user's trashed items, by type. Every lookup goes through
 * here so one user can never see, restore or delete another user's trash.
 */
class Trash
{
    public const TYPES = ['project', 'task', 'note', 'link', 'file'];

    /**
     * Query for the user's trashed items of one type, or null for an unknown type.
     */
    public static function query(string $type): Builder|HasMany|null
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $ownedByUser = fn ($query) => $query->withTrashed()->where('user_id', $user->id);
        $withProject = ['project' => fn ($query) => $query->withTrashed()];

        return match ($type) {
            'project' => $user->projects()->onlyTrashed(),
            'task' => $user->tasks()->onlyTrashed()->with($withProject),
            'note' => ProjectNote::onlyTrashed()->whereHas('project', $ownedByUser)->with($withProject),
            'link' => ProjectLink::onlyTrashed()->whereHas('project', $ownedByUser)->with($withProject),
            'file' => ProjectFile::onlyTrashed()->whereHas('project', $ownedByUser)->with($withProject),
            default => null,
        };
    }

    public static function count(): int
    {
        return collect(self::TYPES)->sum(fn (string $type) => self::query($type)?->count() ?? 0);
    }
}
