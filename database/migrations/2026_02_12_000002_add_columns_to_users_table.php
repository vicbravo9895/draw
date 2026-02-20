<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            $table->string('employee_number')->nullable()->after('email');
            $table->string('username')->nullable()->unique()->after('employee_number');
            $table->string('phone')->nullable()->after('username');
            $table->string('status')->default('active')->after('phone'); // active, inactive
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'employee_number', 'username', 'phone', 'status', 'last_login_at']);
        });
    }
};
