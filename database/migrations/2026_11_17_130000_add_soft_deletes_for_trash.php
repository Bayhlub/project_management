<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting moves things to the Trash (deleted_at is set) instead of removing
 * them. They are only removed for good with "Delete forever" or "Empty trash".
 */
return new class extends Migration
{
    private array $tables = ['tasks', 'projects', 'project_notes', 'project_links', 'project_files'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
