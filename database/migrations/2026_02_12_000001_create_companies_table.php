<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('public_code')->unique();
            $table->string('status')->default('active'); // active, inactive
            $table->string('timezone')->default('America/Mexico_City');
            $table->string('contact_email')->nullable();
            $table->jsonb('allowed_domains')->nullable();
            $table->jsonb('allowed_emails')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('allow_exports')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
