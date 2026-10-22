<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ulasan pembeli. Hanya yang berstatus approved tampil di toko dan dihitung ke rating.
        // user_id dan order_id kosong untuk data contoh; order_id belum punya foreign key karena tabel Pesanan belum ada.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('reviewer_name', 80);
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('status', 10)->default('pending'); // pending | approved | hidden
            $table->timestamps();

            $table->index(['product_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
