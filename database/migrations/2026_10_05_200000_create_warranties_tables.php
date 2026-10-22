<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Paket garansi berbayar buatan admin; dipasang ke produk lewat tabel penghubung.
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('description', 200)->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->unsignedSmallInteger('duration_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'price']);
        });

        Schema::create('product_warranty', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warranty_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'warranty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_warranty');
        Schema::dropIfExists('warranties');
    }
};
