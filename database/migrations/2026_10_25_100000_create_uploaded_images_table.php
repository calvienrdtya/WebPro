<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gambar unggahan yang dioptimalkan (App\Support\ImageOptimizer): file tampil di disk uploads (biasanya WebP
     * yang sudah dikecilkan) dan file ASLI di penyimpanan privat, agar admin bisa kembali ke file asli per gambar.
     */
    public function up(): void
    {
        Schema::create('uploaded_images', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique(); // file yang sedang dipakai toko (disk uploads)
            $table->string('profile', 20); // product, hero, logo, campus, share
            $table->string('original_path'); // disk local (storage/app/private)
            $table->string('original_name')->nullable();
            $table->unsignedInteger('original_size');
            $table->unsignedSmallInteger('original_width');
            $table->unsignedSmallInteger('original_height');
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->boolean('optimized'); // false = memakai file asli
            $table->unsignedTinyInteger('quality')->nullable();
            $table->string('share_path')->nullable(); // JPG untuk pratinjau tautan (WhatsApp, Facebook)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_images');
    }
};
