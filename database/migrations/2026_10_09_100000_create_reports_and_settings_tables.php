<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laporan satu arah dari pelanggan; admin menyelesaikannya (resolve). order_id belum punya foreign key karena tabel Pesanan belum ada.
        Schema::create('customer_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('subject', 150);
            $table->text('body');
            $table->string('status', 10)->default('open'); // open | resolved
            $table->string('resolution', 30)->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('customer_reports')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('customer_reports')->cascadeOnDelete();
            $table->string('path');
            $table->string('name', 150);
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        // Pengaturan toko berbentuk key-value (notifikasi sekarang; biaya admin dan lainnya menyusul).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('report_attachments');
        Schema::dropIfExists('customer_reports');
    }
};
