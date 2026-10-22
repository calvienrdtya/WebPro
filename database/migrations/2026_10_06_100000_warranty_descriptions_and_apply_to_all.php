<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranties', function (Blueprint $table) {
            // Deskripsi jadi daftar poin; applies_to_all = berlaku untuk semua produk, termasuk yang ditambahkan nanti.
            $table->json('descriptions')->nullable()->after('name');
            $table->boolean('applies_to_all')->default(false)->after('is_active');
        });

        $productCount = DB::table('products')->whereNull('deleted_at')->count();

        DB::table('warranties')->orderBy('id')->each(function ($w) use ($productCount) {
            $linked = DB::table('product_warranty')->where('warranty_id', $w->id)->count();
            $all = $productCount > 0 && $linked >= $productCount;

            DB::table('warranties')->where('id', $w->id)->update([
                'descriptions' => $w->description ? json_encode([$w->description], JSON_UNESCAPED_UNICODE) : null,
                'applies_to_all' => $all,
            ]);

            // Yang tadinya terpasang ke semua produk kini cukup ditandai "semua produk".
            if ($all) {
                DB::table('product_warranty')->where('warranty_id', $w->id)->delete();
            }
        });

        Schema::table('warranties', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        Schema::table('warranties', function (Blueprint $table) {
            $table->string('description', 200)->nullable()->after('name');
        });

        DB::table('warranties')->orderBy('id')->each(function ($w) {
            $first = $w->descriptions ? (json_decode($w->descriptions, true)[0] ?? null) : null;
            DB::table('warranties')->where('id', $w->id)->update(['description' => $first]);
        });

        Schema::table('warranties', function (Blueprint $table) {
            $table->dropColumn(['descriptions', 'applies_to_all']);
        });
    }
};
