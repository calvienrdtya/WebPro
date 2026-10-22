<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DeployChecks;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Menu Panduan Deploy: cek kesiapan production server ini + dokumen panduan (docs/deploy.md). */
class DeployGuideController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Deploy/Index', [
            'checks' => DeployChecks::all(),
            // Dokumen dari repositori sendiri (bukan masukan pengguna); HTML mentah tetap di-escape.
            'guide' => (string) Str::markdown((string) file_get_contents(base_path('docs/deploy.md')), ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'checkedAt' => now()->toIso8601String(),
        ]);
    }
}
