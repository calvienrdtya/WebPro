<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pasangan id sistem lama dan id sekarang, dipakai `legacy:import` agar impor bisa diulang dan tabel berikutnya bisa menemukan relasinya. */
    public function up(): void
    {
        Schema::create('legacy_id_maps', function (Blueprint $t) {
            $t->id();
            $t->string('source_table', 50);
            $t->unsignedBigInteger('legacy_id');
            // Kosong = baris lama sengaja tidak diimpor (alasannya di note).
            $t->unsignedBigInteger('new_id')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();

            $t->unique(['source_table', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_id_maps');
    }
};
