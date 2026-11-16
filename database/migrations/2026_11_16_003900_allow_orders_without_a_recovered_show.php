<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('show_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('orders')->whereNull('show_id')->exists()) {
            throw new RuntimeException('Assign a show to each historical order before rolling back.');
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('show_id')->nullable(false)->change();
        });
    }
};
