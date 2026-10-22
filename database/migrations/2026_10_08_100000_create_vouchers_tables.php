<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Potongan hanya untuk harga produk. Syarat (min_purchase, dst.) berupa kolom nullable: kosong = tanpa syarat.
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('title', 80);
            $table->string('description', 200)->nullable();
            $table->string('discount_type', 10); // percent | fixed
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('max_discount')->nullable(); // plafon potongan, hanya untuk persen
            $table->unsignedInteger('min_purchase')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedSmallInteger('per_user_limit')->nullable()->default(1);
            $table->boolean('requires_claim')->default(true);
            $table->boolean('is_listed')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('applies_to_all')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });

        Schema::create('voucher_product', function (Blueprint $table) {
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->primary(['voucher_id', 'product_id']);
        });

        // Dompet voucher: voucher yang sudah diklaim user. Pemakaian dicatat bersama modul Pesanan.
        Schema::create('user_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at');

            $table->unique(['user_id', 'voucher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_vouchers');
        Schema::dropIfExists('voucher_product');
        Schema::dropIfExists('vouchers');
    }
};
