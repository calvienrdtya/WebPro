<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Varian kini templat global (title + jumlah bulan); harga per produk disimpan di product_variants.
        // Nama kunci asing ikut pindah saat tabel di-rename (MySQL) dan akan bentrok dengan tabel baru.
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropForeign(['product_id']));
        Schema::rename('product_variants', 'product_variants_old');

        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->string('title', 30);
            $table->unsignedTinyInteger('months');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'months']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            // from_product: harga jual produk x jumlah bulan, diskon produk. custom: price + discount_percent di baris ini.
            $table->string('price_mode', 20)->default('custom');
            $table->unsignedInteger('price')->nullable();
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'variant_id']);
        });

        $this->convertOldRows();

        Schema::drop('product_variants_old');
    }

    public function down(): void
    {
        Schema::drop('product_variants');
        Schema::drop('variants');

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedInteger('price');
            $table->unsignedInteger('compare_price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'duration_days']);
        });

        Schema::drop('product_variants_old');
    }

    private function convertOldRows(): void
    {
        $variantIds = [];

        DB::table('product_variants_old')->orderBy('product_id')->orderBy('sort_order')->orderBy('id')->each(function ($old) use (&$variantIds) {
            if (! $old->is_active) {
                return;
            }

            // Durasi >= 10000 adalah sisa pergeseran sementara di kode lama; kembalikan ke nilai aslinya.
            $days = $old->duration_days >= 10000 ? $old->duration_days - 10000 : $old->duration_days;
            $months = max(1, min(12, (int) round($days / 30)));

            $variantIds[$months] ??= DB::table('variants')->insertGetId([
                'title' => mb_substr($old->label, 0, 30),
                'months' => $months,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $base = ($old->compare_price && $old->compare_price > $old->price) ? $old->compare_price : $old->price;

            DB::table('product_variants')->insertOrIgnore([
                'product_id' => $old->product_id,
                'variant_id' => $variantIds[$months],
                'price_mode' => 'custom',
                'price' => $base,
                'discount_percent' => $base > 0 ? (int) round(($base - $old->price) / $base * 100) : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
};
