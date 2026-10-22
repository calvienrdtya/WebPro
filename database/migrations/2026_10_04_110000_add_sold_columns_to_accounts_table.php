<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Pembeli sementara teks bebas (nama/kontak); nanti diisi otomatis dari pesanan.
            $table->string('sold_to')->nullable()->after('status');
            $table->timestamp('sold_at')->nullable()->after('sold_to');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['sold_to', 'sold_at']);
        });
    }
};
