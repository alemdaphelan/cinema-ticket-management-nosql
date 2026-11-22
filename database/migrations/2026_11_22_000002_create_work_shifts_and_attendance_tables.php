<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->date('shift_date');
            $table->unsignedTinyInteger('shift_number');
            $table->string('name', 50);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('scheduled');
            $table->decimal('counter_revenue', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['shift_date', 'shift_number']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('work_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_shift_id')->constrained('work_shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('position', 30);
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->string('check_in_method', 20)->nullable();
            $table->string('check_out_method', 20)->nullable();
            $table->decimal('check_in_confidence', 5, 2)->nullable();
            $table->string('attendance_status', 30)->default('pending');
            $table->boolean('is_violation')->default(false);
            $table->string('violation_reason', 255)->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->timestamp('admin_alert_acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['work_shift_id', 'user_id']);
            $table->index(['user_id', 'attendance_status']);
            $table->index(['is_violation', 'admin_alert_acknowledged_at'], 'wsa_violation_alert_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shift_assignments');
        Schema::dropIfExists('work_shifts');
    }
};
