<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Satu-satunya sumber identitas dan tampilan toko (menu Tampilan Toko): nama, logo, favicon, teks footer, hero
 * beranda, logo kampus, dan sosial media. Nilai di tabel settings (awalan "store.") menang atas config/store.php.
 * Gambar unggahan ada di disk `uploads`; tanpa unggahan dipakai gambar bawaan di public/.
 */
class StoreProfile
{
    public const PREFIX = 'store.';

    /** Kolom teks yang bisa diubah admin => kunci config bawaan. */
    public const TEXTS = [
        'name' => 'name',
        'tagline' => 'tagline',
        'email' => 'email',
        'footer_note' => 'footer_note',
        'hero_title' => 'hero.title',
        'hero_subtitle' => 'hero.subtitle',
        'hero_badge' => 'hero.badge',
        'hero_alt' => 'hero.alt',
        'campus_title' => 'campus.title',
    ];

    public const FLAGS = [
        'stat_orders' => 'hero.stat_orders',
        'stat_rating' => 'hero.stat_rating',
        'campus_enabled' => 'campus.enabled',
    ];

    /** Gambar yang bisa diunggah => kunci config gambar bawaan (null = tidak ada bawaan). */
    public const IMAGES = ['logo' => 'logo', 'favicon' => 'favicon', 'hero_image' => 'hero_image', 'og_image' => 'og_image'];

    public static function text(string $key): string
    {
        $stored = Setting::get(self::PREFIX.$key);

        return $stored ?? (string) config('store.'.self::TEXTS[$key]);
    }

    public static function flag(string $key): bool
    {
        return Setting::bool(self::PREFIX.$key, (bool) config('store.'.self::FLAGS[$key]));
    }

    public static function name(): string
    {
        return static::text('name') ?: 'ProAkses';
    }

    /** Path unggahan (disk uploads) atau null. */
    public static function uploaded(string $image): ?string
    {
        $path = Setting::get(self::PREFIX.$image);

        return $path && Storage::disk('uploads')->exists($path) ? $path : null;
    }

    /** URL gambar: unggahan, lalu bawaan. Gambar bagikan (og) tanpa unggahan memakai hero unggahan, lalu PNG bawaan. */
    public static function imageUrl(string $image): string
    {
        if ($path = static::uploaded($image)) {
            return VersionedUrl::upload($path);
        }

        // Gambar bagikan tanpa unggahan: varian JPG hero (pratinjau tautan tidak semua mendukung WebP).
        if ($image === 'og_image' && ($hero = static::uploaded('hero_image'))) {
            return app(ImageOptimizer::class)->shareUrl($hero);
        }

        return VersionedUrl::asset(config('store.'.self::IMAGES[$image]));
    }

    /** Simpan gambar toko; file lama (dan file aslinya) dihapus kecuali $deleteOld false (pakai asli/optimalkan lagi). */
    public static function putImage(string $image, ?string $path, bool $deleteOld = true): void
    {
        if ($deleteOld && ($old = Setting::get(self::PREFIX.$image))) {
            app(ImageOptimizer::class)->delete($old);
        }

        Setting::put(self::PREFIX.$image, $path);
    }

    /**
     * Judul hero dipecah per kata untuk animasi; bagian di antara * disorot emas. Tanda baca yang menempel setelah
     * bagian emas (mis. "*Premium*,") ditandai `glue` supaya ditulis tanpa spasi.
     *
     * @return list<array{text: string, gold: bool, glue: bool}>
     */
    public static function heroWords(): array
    {
        $words = [];

        foreach (preg_split('/(\*[^*]+\*)/', static::text('hero_title'), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            $gold = str_starts_with($part, '*') && str_ends_with($part, '*') && strlen($part) > 2;

            if (! $gold && $words && preg_match('/^([^\s\w*]+)(.*)$/su', $part, $m)) {
                $words[] = ['text' => $m[1], 'gold' => false, 'glue' => true];
                $part = $m[2];
            }

            foreach (preg_split('/\s+/', trim($gold ? substr($part, 1, -1) : $part), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $words[] = ['text' => $word, 'gold' => $gold, 'glue' => false];
            }
        }

        return $words;
    }

    /**
     * Angka nyata untuk hero: pesanan lunas, pelanggan yang pernah membeli, dan rata-rata ulasan yang tampil.
     * Disimpan di cache 10 menit supaya beranda tetap ringan.
     *
     * @return array{orders: int, customers: int, rating: float, reviews: int}
     */
    public static function stats(): array
    {
        return Cache::remember('store.stats', 600, function () {
            $paid = Order::whereIn('status', [OrderStatus::Paid, OrderStatus::Completed]);
            $reviews = Review::approved();

            return [
                'orders' => (clone $paid)->count(),
                'customers' => (clone $paid)->distinct('user_id')->count('user_id'),
                'rating' => round((float) (clone $reviews)->avg('rating'), 1),
                'reviews' => (clone $reviews)->count(),
            ];
        });
    }

    /** Label hero dengan {pelanggan} dan {pesanan} diganti angka nyata; kosong = tidak tampil. */
    public static function heroBadge(): string
    {
        $badge = trim(static::text('hero_badge'));
        if ($badge === '') {
            return '';
        }

        $stats = static::stats();

        return strtr($badge, ['{pelanggan}' => number_format($stats['customers'], 0, ',', '.'), '{pesanan}' => number_format($stats['orders'], 0, ',', '.')]);
    }

    /**
     * Bagian "Kenapa memilih kami": teks bagian dan daftar kartu (ikon dari config store.reason_icons).
     *
     * @return array{enabled: bool, badge: string, title: string, subtitle: string, items: list<array{icon: string, title: string, text: string}>}
     */
    public static function reasons(): array
    {
        $stored = json_decode((string) Setting::get(self::PREFIX.'reasons'), true);
        $data = is_array($stored) ? $stored + config('store.reasons') : config('store.reasons');
        $icons = config('store.reason_icons');
        $data['items'] = array_values(array_filter($data['items'], fn ($i) => isset($icons[$i['icon'] ?? ''])));

        return $data;
    }

    public static function putReasons(array $data): void
    {
        Setting::put(self::PREFIX.'reasons', json_encode($data));
    }

    public static function reasonIcon(string $key): string
    {
        return config("store.reason_icons.{$key}.1", '');
    }

    /**
     * Logo kampus: tersimpan (JSON) atau bawaan config.
     *
     * @return list<array{path: string, name: string, uploaded: bool}>
     */
    public static function campuses(): array
    {
        $stored = Setting::get(self::PREFIX.'campuses');

        if ($stored === null) {
            return array_map(fn ($c) => ['path' => $c[0], 'name' => $c[1], 'uploaded' => false], config('store.campus.logos'));
        }

        return json_decode($stored, true) ?: [];
    }

    /** @param  list<array{path: string, name: string, uploaded: bool}>  $campuses */
    public static function putCampuses(array $campuses): void
    {
        Setting::put(self::PREFIX.'campuses', json_encode(array_values($campuses)));
    }

    /** Atribut width/height logo kampus dari file aslinya. */
    public static function campusSize(array $campus): string
    {
        return VersionedUrl::sizeAttributes($campus['path'], (bool) $campus['uploaded']);
    }

    /** Atribut width/height gambar toko (logo, hero, ...) dari file yang sedang dipakai. */
    public static function imageSize(string $image): string
    {
        $path = static::uploaded($image);

        return VersionedUrl::sizeAttributes($path ?? config('store.'.self::IMAGES[$image]), $path !== null);
    }

    public static function campusUrl(array $campus): string
    {
        return $campus['uploaded'] ? VersionedUrl::upload($campus['path']) : VersionedUrl::asset($campus['path']);
    }

    /**
     * Semua platform dalam urutan tersimpan (platform baru di config ikut di akhir). WhatsApp (`managed`) memakai
     * tautan dari ADMIN_WHATSAPP_NUMBER, bukan URL ketikan admin.
     *
     * @return list<array{platform: string, url: string, active: bool, label: string, icon: string, color: string, managed: bool}>
     */
    public static function socials(): array
    {
        $platforms = config('store.social_platforms');
        $stored = json_decode((string) Setting::get(self::PREFIX.'socials'), true) ?: config('store.socials');
        $rows = collect($stored)->filter(fn ($s) => isset($platforms[$s['platform'] ?? '']))->keyBy('platform');
        // Platform yang belum ada di daftar tersimpan (mis. baru ditambahkan) memakai status bawaan config.
        $defaults = collect(config('store.socials'))->keyBy('platform');

        return collect($rows->keys())->concat(array_keys($platforms))->unique()
            ->map(function ($p) use ($platforms, $rows, $defaults) {
                $managed = (bool) ($platforms[$p]['managed'] ?? false);
                $url = $managed ? (Whatsapp::number() ? Whatsapp::url() : '') : (string) ($rows[$p]['url'] ?? '');
                $active = (bool) ($rows[$p]['active'] ?? $defaults[$p]['active'] ?? false);

                return ['platform' => $p, 'url' => $url, 'active' => $active, 'managed' => $managed] + $platforms[$p];
            })
            ->values()->all();
    }

    /** Sosial media yang tampil di toko: aktif dan URL-nya terisi. */
    public static function activeSocials(): array
    {
        return array_values(array_filter(static::socials(), fn ($s) => $s['active'] && $s['url'] !== ''));
    }

    /** @param  list<array{platform: string, url: string|null, active: bool}>  $socials */
    public static function putSocials(array $socials): void
    {
        $managed = fn ($p) => (bool) config("store.social_platforms.{$p}.managed", false);

        Setting::put(self::PREFIX.'socials', json_encode(array_map(fn ($s) => [
            'platform' => $s['platform'], 'url' => $managed($s['platform']) ? '' : (string) ($s['url'] ?? ''), 'active' => (bool) $s['active'],
        ], array_values($socials))));
    }
}
