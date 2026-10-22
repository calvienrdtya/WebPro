<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LegalPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Menu Halaman Legal: isi Syarat & Ketentuan dan Kebijakan Privasi (Markdown) yang tampil di toko. */
class LegalPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Legal/Index', [
            'pages' => collect(LegalPages::keys())->map(fn ($k) => [
                ...LegalPages::meta($k),
                'body' => LegalPages::body($k),
                'default_body' => config("legal.pages.{$k}.body"),
                'is_default' => LegalPages::isDefault($k),
                'updated_at' => LegalPages::updatedAt($k)?->toDateString(),
            ])->values(),
            // Nilai penanda untuk pratinjau di admin (sama dengan yang dipakai toko).
            'placeholders' => LegalPages::placeholders(),
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        abort_unless(in_array($page, LegalPages::keys(), true), 404);
        $body = $request->validate(['body' => ['required', 'string', 'max:50000']], [], ['body' => 'Isi halaman'])['body'];

        LegalPages::put($page, $body);

        return back()->with('success', config("legal.pages.{$page}.title").' disimpan.');
    }
}
