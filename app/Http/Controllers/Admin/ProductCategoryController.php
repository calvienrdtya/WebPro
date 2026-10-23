<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    /** Ganti nama tampilan saja; slug tetap supaya URL toko yang sudah terindeks tidak mati. */
    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $request->merge(['name' => trim(preg_replace('/\s+/', ' ', (string) $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('product_categories', 'name')->ignore($category)],
        ], [], ['name' => 'Nama kategori']);

        $category->update($data);

        return back()->with('success', "Kategori diubah menjadi \"{$category->name}\".");
    }
}
