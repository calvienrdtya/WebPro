<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // Salinan (snapshot) agar riwayat tidak berubah bila produk, garansi, atau voucher diedit/dihapus.
            $table->string('product_name');
            $table->string('variant_title', 60)->nullable();
            $table->unsignedTinyInteger('months')->default(1);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 30);

            $table->unsignedInteger('list_price');      // harga coret (sebelum diskon produk)
            $table->unsignedInteger('product_price');   // harga produk setelah diskon produk
            $table->foreignId('warranty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('warranty_name')->nullable();
            $table->unsignedInteger('warranty_days')->nullable();
            $table->unsignedInteger('warranty_price')->default(0);
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voucher_code', 30)->nullable();
            $table->unsignedInteger('voucher_discount')->default(0);
            $table->unsignedInteger('service_fee')->default(0);
            $table->unsignedInteger('total');

            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('claimed_paid_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Satu baris per bulan (satu akun per bulan). Bulan ke-2 dst terisi akunnya saat jatuh tempo.
        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->date('due_on')->nullable();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('cogs')->nullable();     // salinan HPP akun saat terkirim
            $table->timestamp('delivered_at')->nullable();
            $table->date('warranty_ends_on')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_deliveries');
        Schema::dropIfExists('orders');
    }
};
