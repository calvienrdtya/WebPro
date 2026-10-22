<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pertanyaan umum yang dikelola admin; tampil di beranda, halaman produk, dan halaman /faq.
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->string('question', 200);
            $table->text('answer');
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_home')->default(false);
            $table->boolean('show_on_product')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
