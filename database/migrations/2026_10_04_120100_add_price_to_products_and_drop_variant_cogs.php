<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Harga jual sebelum diskon (harga coret di toko) dan diskon dalam persen.
            $table->unsignedInteger('price')->default(0)->after('specs');
            $table->unsignedTinyInteger('discount_percent')->default(0)->after('price');
        });

        // Produk yang sudah ada: ambil dari varian pertama (harga coret bila ada, kalau tidak harga jual).
        DB::table('products')->orderBy('id')->each(function ($product) {
            $variant = DB::table('product_variants')->where('product_id', $product->id)->orderBy('sort_order')->orderBy('duration_days')->first();

            if (! $variant) {
                return;
            }

            $base = ($variant->compare_price && $variant->compare_price > $variant->price) ? $variant->compare_price : $variant->price;

            DB::table('products')->where('id', $product->id)->update([
                'price' => $base,
                'discount_percent' => $base > 0 ? (int) round(($base - $variant->price) / $base * 100) : 0,
            ]);
        });

        // HPP sekarang hanya ada di akun.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('cogs');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('cogs')->nullable()->after('compare_price');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price', 'discount_percent']);
        });
    }
};
