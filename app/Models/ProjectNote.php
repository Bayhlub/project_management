<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectNote extends Model
{
    use SoftDeletes;

    protected $fillable = ['body'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
