<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('title');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_sections');
    }
};
