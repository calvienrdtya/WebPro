<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Activity;
use App\Support\ImageOptimizer;
use App\Support\StoreProfile;
use App\Support\Whatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Menu Tampilan Toko: identitas (nama, logo, favicon, footer), beranda (hero, logo kampus), dan sosial media. */
class StoreAppearanceController extends Controller
{
    /** Jenis optimasi gambar toko (config proakses.images.profiles). Favicon tidak dioptimalkan. */
    private const PROFILES = ['logo' => 'logo', 'hero_image' => 'hero', 'og_image' => 'share'];

    public function __construct(private ImageOptimizer $images) {}

    public function index(): Response
    {
        $image = fn (string $key) => ['url' => StoreProfile::imageUrl($key), 'custom' => StoreProfile::uploaded($key) !== null, 'info' => $this->imageInfo($key)];

        return Inertia::render('Store/Appearance', [
            'texts' => collect(StoreProfile::TEXTS)->keys()->mapWithKeys(fn ($k) => [$k => StoreProfile::text($k)]),
            'flags' => collect(StoreProfile::FLAGS)->keys()->mapWithKeys(fn ($k) => [$k => StoreProfile::flag($k)]),
            'images' => collect(StoreProfile::IMAGES)->keys()->mapWithKeys(fn ($k) => [$k => $image($k)]),
            'stats' => StoreProfile::stats(),
            'campuses' => array_map(fn ($c) => ['name' => $c['name'], 'url' => StoreProfile::campusUrl($c), 'info' => $this->campusInfo($c)], StoreProfile::campuses()),
            'socials' => StoreProfile::socials(),
            'reasons' => StoreProfile::reasons(),
            'reasonIcons' => collect(config('store.reason_icons'))->map(fn ($i, $k) => ['key' => $k, 'label' => $i[0], 'svg' => $i[1]])->values(),
            'limits' => ['logo_kb' => config('store.max_logo_kb'), 'image_kb' => config('store.max_image_kb')],
        ]);
    }

    /**
     * Per gambar toko (logo, hero, gambar bagikan): pakai file asli, atau optimalkan. Gambar bawaan (public/assets)
     * yang dioptimalkan disalin menjadi unggahan; file bawaannya tetap ada.
     */
    public function image(Request $request, string $key): RedirectResponse
    {
        abort_unless(isset(self::PROFILES[$key]), 404);
        $action = $request->validate(['action' => ['required', 'in:original,optimize']])['action'];
        $path = StoreProfile::uploaded($key);

        if ($action === 'original') {
            abort_unless($path, 404);
            StoreProfile::putImage($key, $this->images->useOriginal($path), deleteOld: false);
        } else {
            $new = $path
                ? $this->images->optimize($path, self::PROFILES[$key])
                : $this->images->adopt(config('store.'.StoreProfile::IMAGES[$key]), 'store', self::PROFILES[$key], fromUploads: false);
            abort_unless($new, 404);
            StoreProfile::putImage($key, $new, deleteOld: false);
        }

        Activity::note(($key === 'logo' ? 'logo' : ($key === 'hero_image' ? 'hero' : 'gambar bagikan')).', '.($action === 'original' ? 'pakai file asli' : 'dioptimalkan'));

        return back()->with('success', $action === 'original' ? 'Gambar kembali memakai file asli.' : 'Gambar dioptimalkan.');
    }

    public function campusImage(Request $request, int $index): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'in:original,optimize']])['action'];
        $campuses = StoreProfile::campuses();
        abort_unless(isset($campuses[$index]), 404);
        $campus = $campuses[$index];

        if ($action === 'original') {
            abort_unless($campus['uploaded'], 404);
            $campus['path'] = $this->images->useOriginal($campus['path']);
        } else {
            $new = $campus['uploaded']
                ? $this->images->optimize($campus['path'], 'campus')
                : $this->images->adopt($campus['path'], 'store/campus', 'campus', fromUploads: false);
            abort_unless($new, 404);
            $campus = ['path' => $new, 'name' => $campus['name'], 'uploaded' => true];
        }
        $campuses[$index] = $campus;
        StoreProfile::putCampuses($campuses);

        Activity::note($campus['name'].', '.($action === 'original' ? 'pakai file asli' : 'dioptimalkan'));

        return back()->with('success', $action === 'original' ? "Logo {$campus['name']} kembali memakai file asli." : "Logo {$campus['name']} dioptimalkan.");
    }

    /** Info ukuran untuk admin; gambar bawaan dianggap belum dioptimalkan. */
    private function imageInfo(string $key): ?array
    {
        if (! isset(self::PROFILES[$key])) {
            return null;
        }
        if ($path = StoreProfile::uploaded($key)) {
            return $this->images->info($path);
        }
        $file = public_path(config('store.'.StoreProfile::IMAGES[$key]));

        return is_file($file) ? ['tracked' => false, 'optimized' => false, 'size' => filesize($file), 'can_original' => false, 'can_optimize' => true] : null;
    }

    private function campusInfo(array $campus): ?array
    {
        if ($campus['uploaded']) {
            return $this->images->info($campus['path']);
        }
        $file = public_path($campus['path']);

        return is_file($file) ? ['tracked' => false, 'optimized' => false, 'size' => filesize($file), 'can_original' => false, 'can_optimize' => true] : null;
    }

    public function updateIdentity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:120'],
            'footer_note' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'file', 'extensions:png,webp,jpg,jpeg', 'mimetypes:image/png,image/webp,image/jpeg', 'max:'.config('store.max_logo_kb')],
            'favicon' => ['nullable', 'file', 'extensions:png,ico', 'max:'.config('store.max_logo_kb')],
            'remove_logo' => ['boolean'],
            'remove_favicon' => ['boolean'],
        ], [
            'logo.extensions' => 'Logo harus PNG, WebP, atau JPG. SVG tidak diterima karena bisa membawa skrip.',
            'logo.mimetypes' => 'Logo harus PNG, WebP, atau JPG. SVG tidak diterima karena bisa membawa skrip.',
            'favicon.extensions' => 'Favicon harus PNG atau ICO.',
        ], ['name' => 'Nama toko', 'tagline' => 'Deskripsi singkat', 'footer_note' => 'Catatan footer']);

        $this->putTexts($data, ['name', 'tagline', 'email', 'footer_note']);

        // Logo dikecilkan + WebP (transparansi dijaga); favicon disimpan apa adanya.
        foreach (['logo', 'favicon'] as $key) {
            if ($request->hasFile($key)) {
                StoreProfile::putImage($key, $key === 'favicon'
                    ? Storage::disk('uploads')->putFile('store', $request->file($key))
                    : $this->images->store($request->file($key), 'store', self::PROFILES[$key]));
            } elseif ($request->boolean("remove_{$key}")) {
                StoreProfile::putImage($key, null);
            }
        }

        return back()->with('success', 'Identitas toko disimpan.');
    }

    public function updateHome(Request $request): RedirectResponse
    {
        $image = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('store.max_image_kb')];
        $data = $request->validate([
            'hero_title' => ['required', 'string', 'max:150'],
            'hero_subtitle' => ['nullable', 'string', 'max:300'],
            'hero_badge' => ['nullable', 'string', 'max:80'],
            'hero_alt' => ['nullable', 'string', 'max:150'],
            'stat_orders' => ['boolean'],
            'stat_rating' => ['boolean'],
            'campus_enabled' => ['boolean'],
            'campus_title' => ['nullable', 'string', 'max:150'],
            'hero_image' => $image,
            'og_image' => $image,
            'remove_hero_image' => ['boolean'],
            'remove_og_image' => ['boolean'],
        ], [], ['hero_title' => 'Judul hero', 'hero_subtitle' => 'Subjudul', 'hero_badge' => 'Label atas', 'hero_image' => 'Gambar hero', 'og_image' => 'Gambar bagikan', 'campus_title' => 'Judul bagian kampus']);

        $this->putTexts($data, ['hero_title', 'hero_subtitle', 'hero_badge', 'hero_alt', 'campus_title']);
        foreach (['stat_orders', 'stat_rating', 'campus_enabled'] as $flag) {
            Setting::put(StoreProfile::PREFIX.$flag, $request->boolean($flag));
        }

        foreach (['hero_image', 'og_image'] as $key) {
            if ($request->hasFile($key)) {
                StoreProfile::putImage($key, $this->images->store($request->file($key), 'store', self::PROFILES[$key]));
            } elseif ($request->boolean("remove_{$key}")) {
                StoreProfile::putImage($key, null);
            }
        }

        return back()->with('success', 'Tampilan beranda disimpan.');
    }

    public function storeCampus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('store.max_logo_kb')],
        ], [], ['name' => 'Nama kampus', 'logo' => 'Logo kampus']);

        $campuses = StoreProfile::campuses();
        $campuses[] = ['path' => $this->images->store($data['logo'], 'store/campus', 'campus'), 'name' => $data['name'], 'uploaded' => true];
        StoreProfile::putCampuses($campuses);

        return back()->with('success', "Logo {$data['name']} ditambahkan.");
    }

    public function moveCampus(Request $request, int $index): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $campuses = StoreProfile::campuses();
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        abort_unless(isset($campuses[$index], $campuses[$target]), 404);
        [$campuses[$index], $campuses[$target]] = [$campuses[$target], $campuses[$index]];
        StoreProfile::putCampuses($campuses);

        return back();
    }

    public function destroyCampus(int $index): RedirectResponse
    {
        $campuses = StoreProfile::campuses();
        abort_unless(isset($campuses[$index]), 404);

        $removed = $campuses[$index];
        if ($removed['uploaded']) {
            $this->images->delete($removed['path']);
        }
        unset($campuses[$index]);
        StoreProfile::putCampuses($campuses);

        return back()->with('success', "Logo {$removed['name']} dihapus.");
    }

    public function updateSocials(Request $request): RedirectResponse
    {
        $platforms = array_keys(config('store.social_platforms'));

        $data = $request->validate([
            'socials' => ['required', 'array', 'size:'.count($platforms)],
            'socials.*.platform' => ['required', 'distinct', Rule::in($platforms)],
            'socials.*.url' => ['nullable', 'url:http,https', 'max:255'],
            'socials.*.active' => ['boolean'],
        ], ['socials.*.url.url' => 'URL harus diawali http:// atau https://.'], ['socials.*.url' => 'URL']);

        $validator = validator([]);
        foreach ($data['socials'] as $i => $s) {
            if (! ($s['active'] ?? false)) {
                continue;
            }
            if (config("store.social_platforms.{$s['platform']}.managed")) {
                if (Whatsapp::number() === null) {
                    $validator->errors()->add("socials.{$i}.url", 'Nomor WhatsApp belum diisi. Isi ADMIN_WHATSAPP_NUMBER di file .env, atau matikan saklarnya.');
                }
            } elseif (blank($s['url'] ?? null)) {
                $validator->errors()->add("socials.{$i}.url", 'Isi URL dulu, atau matikan saklarnya.');
            }
        }
        if ($validator->errors()->isNotEmpty()) {
            return back()->withErrors($validator);
        }

        StoreProfile::putSocials(array_map(fn ($s) => [...$s, 'active' => (bool) ($s['active'] ?? false)], $data['socials']));

        return back()->with('success', 'Sosial media disimpan.');
    }

    /** Bagian "Kenapa memilih kami": teks bagian dan kartu (maks 12, ikon dari daftar bawaan). */
    public function updateReasons(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'badge' => ['nullable', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'items' => ['array', 'max:12'],
            'items.*.icon' => ['required', Rule::in(array_keys(config('store.reason_icons')))],
            'items.*.title' => ['required', 'string', 'max:60'],
            'items.*.text' => ['required', 'string', 'max:200'],
        ], [], ['title' => 'Judul', 'items.*.title' => 'Judul kartu', 'items.*.text' => 'Keterangan kartu', 'items.*.icon' => 'Ikon']);

        StoreProfile::putReasons([
            'enabled' => $request->boolean('enabled'),
            'badge' => (string) ($data['badge'] ?? ''),
            'title' => $data['title'],
            'subtitle' => (string) ($data['subtitle'] ?? ''),
            'items' => array_values(array_map(fn ($i) => ['icon' => $i['icon'], 'title' => $i['title'], 'text' => $i['text']], $data['items'] ?? [])),
        ]);

        return back()->with('success', 'Bagian "Kenapa memilih kami" disimpan.');
    }

    /** Teks yang sama dengan bawaan disimpan kosong (null), supaya perbaikan bawaan ikut terpakai. */
    private function putTexts(array $data, array $keys): void
    {
        foreach ($keys as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            Setting::put(StoreProfile::PREFIX.$key, $value === (string) config('store.'.StoreProfile::TEXTS[$key]) ? null : $value);
        }
    }
}
