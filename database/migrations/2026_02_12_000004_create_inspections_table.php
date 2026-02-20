<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('date');
            $table->string('shift')->nullable(); // 1st, 2nd, 3rd
            $table->string('project')->nullable();
            $table->string('area_line')->nullable();
            $table->foreignId('scheduled_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('comment_general')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed
            $table->string('reference_code');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'reference_code']);
            $table->index('company_id');
            $table->index('status');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
