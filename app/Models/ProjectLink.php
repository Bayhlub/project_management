<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectLink extends Model
{
    use SoftDeletes;

    protected $fillable = ['label', 'url'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function host(): string
    {
        return preg_replace('/^www\./', '', (string) parse_url($this->url, PHP_URL_HOST));
    }

    public function displayLabel(): string
    {
        return $this->label ?: ($this->host() ?: $this->url);
    }
}
