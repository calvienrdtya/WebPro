<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Support\SystemLog;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'admin';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => ['name' => config('app.name')],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            // Submenu Stok Akun di sidebar: satu entri per produk.
            'stockMenu' => fn () => $request->user()?->is_admin
                ? Product::query()
                    ->orderBy('sort_order')->orderBy('name')
                    ->get(['id', 'name', 'slug'])
                    ->map(fn ($p) => ['name' => $p->name, 'slug' => $p->slug])
                    ->all()
                : [],
            // Angka kecil di menu sidebar, mis. jumlah error baru di Log Sistem sejak terakhir dibuka.
            'sidebarBadges' => fn () => $request->user()?->is_admin
                ? ['system_log' => rescue(fn () => app(SystemLog::class)->newErrorCount(), 0, false)]
                : [],
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar,
                ] : null,
            ],
        ];
    }
}
