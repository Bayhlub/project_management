<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class ProjectFile extends Model
{
    use SoftDeletes;

    /**
     * project_id and path are set explicitly by the upload code.
     *
     * @var list<string>
     */
    protected $fillable = ['original_name', 'mime_type', 'size'];

    protected static function booted(): void
    {
        static::forceDeleting(function (ProjectFile $file) {
            Storage::disk('local')->delete($file->path);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
