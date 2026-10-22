<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log chat AI di toko. Percakapan tertaut ke akun penanya (tamu: kunci sesi + IP), disimpan permanen (hapus = soft delete)
     * dan dianonimkan saat user menghapus akun. Gambar pelanggan disimpan privat dan dihapus otomatis setelah sekian hari.
     */
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('guest_key', 64)->nullable()->index();
            $t->string('ip', 45)->nullable()->index();
            $t->unsignedInteger('messages_count')->default(0);
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamp('anonymized_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('ai_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $t->string('role', 10); // user | assistant
            $t->text('content')->nullable();
            // Pesan pelanggan: gambar terlampir (privat). image_path dikosongkan saat gambar dihapus otomatis.
            $t->boolean('has_image')->default(false);
            $t->string('image_path')->nullable();
            // Jawaban: ok | fallback (AI gagal, dijawab kata kunci) | limit (batas harian habis)
            $t->string('status', 10)->nullable();
            $t->string('error')->nullable();
            $t->string('model')->nullable();
            $t->unsignedInteger('prompt_tokens')->nullable();
            $t->unsignedInteger('completion_tokens')->nullable();
            $t->unsignedInteger('ms')->nullable();
            $t->boolean('show_wa')->default(false);
            $t->timestamps();

            $t->index(['conversation_id', 'id']);
            $t->index(['role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
