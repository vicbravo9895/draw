<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employees (User) are assigned to companies they work with; they do not "belong" to a company.
     * Companies consult only via the portal (CompanyViewer).
     */
    public function up(): void
    {
        Schema::create('user_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'company_id']);
        });

        // Backfill from users.company_id so existing data works
        $rows = DB::table('users')->whereNotNull('company_id')->get(['id', 'company_id']);
        $now = now();
        foreach ($rows as $row) {
            DB::table('user_companies')->insertOrIgnore([
                'user_id' => $row->id,
                'company_id' => $row->company_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_companies');
    }
};
