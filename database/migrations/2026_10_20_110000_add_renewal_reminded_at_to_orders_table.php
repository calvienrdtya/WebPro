<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Waktu email pengingat perpanjangan terkirim; satu pengingat per pesanan. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('renewal_reminded_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('renewal_reminded_at');
        });
    }
};
