<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Kategori produk pindah dari enum ke tabel: dibuat otomatis dari form produk, slug dikunci setelah dibuat.
     *
     * MySQL tidak membatalkan perubahan struktur bila migrasi gagal di tengah, dan melarang mengubah kolom yang sudah
     * punya foreign key menjadi NOT NULL. Karena itu urutannya: kolom nullable tanpa FK, isi data, jadikan NOT NULL,
     * baru pasang FK. Tiap langkah dicek dulu agar migrasi yang terhenti di tengah bisa dijalankan ulang.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_categories')) {
            $this->createCategories();
        }

        if (! Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', fn (Blueprint $table) => $table->unsignedBigInteger('category_id')->nullable()->after('slug'));
        }

        if (Schema::hasColumn('products', 'category')) {
            foreach (DB::table('product_categories')->get() as $category) {
                DB::table('products')->where('category', $category->slug)->update(['category_id' => $category->id]);
            }

            if (Schema::hasIndex('products', ['is_active', 'category', 'sort_order'])) {
                Schema::table('products', fn (Blueprint $table) => $table->dropIndex(['is_active', 'category', 'sort_order']));
            }

            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('category'));
        }

        if ($this->hasCategoryForeignKey()) {
            Schema::table('products', fn (Blueprint $table) => $table->dropForeign(['category_id']));
        }

        Schema::table('products', fn (Blueprint $table) => $table->unsignedBigInteger('category_id')->nullable(false)->change());

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('product_categories')->restrictOnDelete();
        });

        if (! Schema::hasIndex('products', ['is_active', 'category_id', 'sort_order'])) {
            Schema::table('products', fn (Blueprint $table) => $table->index(['is_active', 'category_id', 'sort_order']));
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['is_active', 'category_id', 'sort_order']);
            $table->string('category', 30)->default('tools')->after('slug');
        });

        foreach (DB::table('product_categories')->get() as $category) {
            DB::table('products')->where('category_id', $category->id)->update(['category' => $category->slug]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category_id');
            $table->index(['is_active', 'category', 'sort_order']);
        });

        Schema::dropIfExists('product_categories');
    }

    private function createCategories(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        $known = ['ai' => 'Akun AI', 'streaming' => 'Streaming', 'tools' => 'Tools'];

        if (Schema::hasColumn('products', 'category')) {
            foreach (DB::table('products')->distinct()->pluck('category') as $slug) {
                $known[$slug] ??= Str::headline($slug);
            }
        }

        $now = now();

        foreach ($known as $slug => $name) {
            DB::table('product_categories')->insert(['name' => $name, 'slug' => $slug, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function hasCategoryForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('products'))->contains(fn (array $fk) => $fk['columns'] === ['category_id']);
    }
};
