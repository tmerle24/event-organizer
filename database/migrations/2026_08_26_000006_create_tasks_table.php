<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->foreignId('assignee_participant_id')->nullable()
                ->constrained('participants')->nullOnDelete();
            // open | done
            $table->string('status', 10)->default('open');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
