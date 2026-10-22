<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pasangan file tampil dan file asli satu gambar unggahan (lihat App\Support\ImageOptimizer). */
class UploadedImage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['optimized' => 'boolean'];
    }
}
