<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Halaman legal (Syarat & Ketentuan, Kebijakan Privasi). Isi Markdown dari menu admin Halaman Legal (settings
 * `legal.{halaman}.body`), bawaan dari config/legal.php. Penanda {nama_toko}, {email}, {situs}, {masa_log_aktivitas}, {masa_log_sistem} diisi saat tampil.
 */
class LegalPages
{
    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(config('legal.pages'));
    }

    public static function body(string $page): string
    {
        return Setting::get("legal.{$page}.body") ?? config("legal.pages.{$page}.body");
    }

    public static function isDefault(string $page): bool
    {
        return Setting::get("legal.{$page}.body") === null;
    }

    public static function updatedAt(string $page): ?Carbon
    {
        $date = Setting::get("legal.{$page}.updated");

        return $date ? Carbon::parse($date) : null;
    }

    /** Isi sama dengan draf bawaan disimpan kosong, supaya perbaikan draf ikut terpakai. Tanggal diperbarui tiap simpan. */
    public static function put(string $page, string $body): void
    {
        Setting::put("legal.{$page}.body", trim($body) === trim(config("legal.pages.{$page}.body")) ? null : $body);
        Setting::put("legal.{$page}.updated", now()->toDateString());
    }

    /** Nilai penanda; dipakai halaman toko dan pratinjau admin. Masa simpan log mengikuti Pengaturan. */
    public static function placeholders(): array
    {
        $email = StoreProfile::text('email');

        return [
            'nama_toko' => StoreProfile::name(),
            'email' => $email !== '' ? $email : 'email admin',
            'situs' => preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')),
            'masa_log_aktivitas' => Setting::activityLogDays().' hari',
            'masa_log_sistem' => Setting::systemLogDays().' hari',
        ];
    }

    public static function fill(string $text): string
    {
        return strtr($text, collect(static::placeholders())->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => $v])->all());
    }

    /** HTML aman: HTML mentah di Markdown di-escape dan tautan berbahaya (javascript:) tidak dirender. */
    public static function html(string $page, ?string $body = null): string
    {
        return (string) Str::markdown(static::fill($body ?? static::body($page)), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /** @return array{key: string, title: string, slug: string, description: string, url: string} */
    public static function meta(string $page): array
    {
        $c = config("legal.pages.{$page}");

        return ['key' => $page, 'title' => $c['title'], 'slug' => $c['slug'], 'description' => static::fill($c['description']), 'url' => route('legal.show', $c['slug'])];
    }
}
