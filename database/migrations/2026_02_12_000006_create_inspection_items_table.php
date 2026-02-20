<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('inspection_part_id')->constrained('inspection_parts')->cascadeOnDelete();
            $table->string('serial_number')->nullable();
            $table->string('lot_date')->nullable();
            $table->integer('good_qty')->default(0);
            $table->integer('defects_qty')->default(0);
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
