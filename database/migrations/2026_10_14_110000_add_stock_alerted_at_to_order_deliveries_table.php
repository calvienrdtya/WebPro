<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_deliveries', function (Blueprint $table) {
            // Kapan admin terakhir diberi tahu bahwa kiriman jatuh tempo ini tertunda karena stok kurang (agar tidak berulang tiap jam).
            $table->timestamp('stock_alerted_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_deliveries', function (Blueprint $table) {
            $table->dropColumn('stock_alerted_at');
        });
    }
};
