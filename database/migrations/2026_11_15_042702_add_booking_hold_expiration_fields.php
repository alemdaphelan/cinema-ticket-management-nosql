<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('expired_booking_holds')->default(0);
            $table->timestamp('booking_blocked_until')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancel_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cancel_reason');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['expired_booking_holds', 'booking_blocked_until']);
        });
    }
};
