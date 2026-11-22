<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_code', 20)->nullable()->unique()->after('role');
            $table->string('phone', 20)->nullable()->after('employee_code');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('gender', 20)->nullable()->after('date_of_birth');
            $table->string('citizen_id', 30)->nullable()->after('gender');
            $table->string('address', 255)->nullable()->after('citizen_id');
            $table->string('staff_position', 30)->nullable()->after('address');
            $table->unsignedInteger('hourly_rate')->default(35000)->after('staff_position');
            $table->string('account_status', 20)->default('active')->after('hourly_rate');
            $table->date('hired_at')->nullable()->after('account_status');
            $table->text('display_password')->nullable()->after('hired_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_code']);
            $table->dropColumn([
                'employee_code',
                'phone',
                'date_of_birth',
                'gender',
                'citizen_id',
                'address',
                'staff_position',
                'hourly_rate',
                'account_status',
                'hired_at',
                'display_password',
            ]);
        });
    }
};
