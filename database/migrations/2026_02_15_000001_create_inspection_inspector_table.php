<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_inspector', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['inspection_id', 'user_id']);
        });

        // Backfill: add existing assigned_inspector_id to pivot
        $inspections = DB::table('inspections')->whereNotNull('assigned_inspector_id')->get(['id', 'assigned_inspector_id']);
        $now = now();
        foreach ($inspections as $row) {
            DB::table('inspection_inspector')->insertOrIgnore([
                'inspection_id' => $row->id,
                'user_id' => $row->assigned_inspector_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_inspector');
    }
};
