<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** order_id di laporan dan ulasan dibuat sebelum tabel Pesanan ada; kini diberi foreign key. */
    public function up(): void
    {
        foreach (['customer_reports', 'reviews'] as $table) {
            // Nilai yang tidak menunjuk ke pesanan mana pun dikosongkan dulu supaya foreign key bisa dipasang.
            DB::table($table)->whereNotNull('order_id')->whereNotIn('order_id', DB::table('orders')->select('id'))->update(['order_id' => null]);

            Schema::table($table, function (Blueprint $t) {
                $t->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['customer_reports', 'reviews'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign(['order_id']));
        }
    }
};
