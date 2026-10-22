<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Sengaja longgar: semua kolom kredensial opsional dan berformat bebas
            // (email bisa berisi link/teks apa saja, password diisi sesuai kebutuhan).
            $table->text('email')->nullable();
            $table->text('password')->nullable(); // terenkripsi lewat cast
            $table->string('profile')->nullable();
            $table->string('pin')->nullable();
            $table->string('status', 20)->default('available');
            $table->date('expires_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
