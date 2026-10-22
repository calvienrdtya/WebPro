<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedInteger('price');
            $table->unsignedInteger('compare_price')->nullable();
            $table->unsignedInteger('cogs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'duration_days']);
            $table->index(['product_id', 'is_active', 'sort_order']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_compare_price_check CHECK (compare_price IS NULL OR compare_price >= price)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
