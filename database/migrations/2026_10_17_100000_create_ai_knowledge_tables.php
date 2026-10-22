<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Entri pengetahuan AI: teks mentah + lampiran (gambar/PDF) dari admin, dirapikan AI menjadi Markdown (body)
        // yang ditinjau admin sebelum dipakai. status: processing, review, ready, failed (diubah lewat forceFill).
        Schema::create('ai_knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('raw_text')->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('review')->index();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_builtin')->default(false);
            $table->boolean('use_ai')->default(true);
            $table->boolean('with_qa')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_knowledge_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('ai_knowledge_entries')->cascadeOnDelete();
            $table->string('kind', 10); // image, pdf
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('pages')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_knowledge_entry_product', function (Blueprint $table) {
            $table->foreignId('entry_id')->constrained('ai_knowledge_entries')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['entry_id', 'product_id']);
        });

        // Entri bawaan: kebijakan toko (tidak tersimpan di tabel lain). Bisa diubah atau dinonaktifkan, tidak bisa dihapus.
        DB::table('ai_knowledge_entries')->insert([
            'title' => 'Kebijakan toko',
            'body' => <<<'MD'
## Kebijakan toko

### Pembayaran
- Pembayaran hanya lewat QRIS. Nominal yang dibayar sama persis dengan total pesanan.
- Pembayaran diverifikasi manual oleh admin, tidak otomatis.
- Setelah membayar, tekan tombol "Saya Sudah Bayar" di halaman pesanan. Bukti transfer boleh diunggah.
- Pesanan yang tidak dibayar sampai batas waktu di halaman pesanan otomatis kedaluwarsa.

### Pengiriman akun
- Akun dikirim setelah admin memverifikasi pembayaran.
- Akun dikirim ke email yang diisi di form order, dan juga tampil di halaman Pesanan Saya.
- Paket lebih dari 1 bulan berisi 1 akun per bulan. Akun bulan berikutnya dikirim otomatis 28 hari setelah akun sebelumnya.

### Garansi
- Garansi wajib dipilih saat membeli.
- Masa garansi dihitung per akun, sejak akun itu dikirim.
- Bila akun bermasalah dalam masa garansi, laporkan lewat Profil > Laporan dengan memilih pesanan terkait. Admin akan memeriksa dan mengganti akun.

### Bantuan
- Untuk masalah pesanan tertentu, hubungi admin lewat WhatsApp.
MD,
            'status' => 'ready',
            'is_active' => true,
            'is_builtin' => true,
            'use_ai' => false,
            'with_qa' => false,
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_entry_product');
        Schema::dropIfExists('ai_knowledge_attachments');
        Schema::dropIfExists('ai_knowledge_entries');
    }
};
