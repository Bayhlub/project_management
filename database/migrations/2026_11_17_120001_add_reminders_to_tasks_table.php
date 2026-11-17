<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // When to alarm the user, and when that alarm was actually sent.
            $table->dateTime('remind_at')->nullable()->after('due_time');
            $table->timestamp('reminded_at')->nullable()->after('remind_at');

            // The reminder sender looks for unsent reminders that are due.
            $table->index(['reminded_at', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['reminded_at', 'remind_at']);
            $table->dropColumn(['remind_at', 'reminded_at']);
        });
    }
};
