<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use App\Services\Ai\ChatService;
use App\Services\Ai\PromptBuilder;

/** Satu-satunya sumber nomor WhatsApp admin: `ADMIN_WHATSAPP_NUMBER` di .env (lewat config('proakses.whatsapp')). */
class Whatsapp
{
    /** Nomor format internasional tanpa "+" (08xx menjadi 628xx), atau null bila belum diisi. */
    public static function number(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) config('proakses.whatsapp'));

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    /** Tautan wa.me dengan pesan awal opsional; "#" bila nomor belum diatur supaya halaman tidak error. */
    public static function url(?string $message = null): string
    {
        $number = static::number();

        if ($number === null) {
            return '#';
        }

        return 'https://wa.me/'.$number.($message ? '?text='.rawurlencode($message) : '');
    }

    /** Template teks pembuka chat jenis `product` atau `general`: pilihan admin, atau bawaan bila kosong. Dibaca sekali per permintaan. */
    public static function template(string $type): string
    {
        return once(fn () => trim((string) Setting::get(Setting::WA_MESSAGE_PREFIX.$type)) ?: config("proakses.whatsapp_messages.{$type}"));
    }

    /** Teks pembuka chat tentang satu produk, dengan {toko}, {produk}, dan {link} terisi. */
    public static function productMessage(Product $product): string
    {
        return PromptBuilder::fill(static::template('product'), [
            'toko' => StoreProfile::name(),
            'produk' => $product->name,
            'link' => route('products.show', $product->slug),
        ]);
    }

    public static function generalMessage(): string
    {
        return PromptBuilder::fill(static::template('general'), ['toko' => StoreProfile::name()]);
    }

    /** Pop up Tanya AI tampil bila dinyalakan di Pengaturan (bawaan: nyala). */
    public static function aiPopupEnabled(): bool
    {
        return Setting::bool(Setting::CHAT_AI_ENABLED, true);
    }

    /** Pop up WhatsApp tampil bila dinyalakan di Pengaturan (bawaan: nyala) dan nomor WhatsApp sudah diisi. */
    public static function waPopupEnabled(): bool
    {
        return static::number() !== null && Setting::bool(Setting::CHAT_WA_ENABLED, true);
    }

    /** Konfigurasi untuk window.PROAKSES_CHAT, atau null bila kedua pop up mati (widget tidak dimuat sama sekali). */
    public static function chatConfig(): ?array
    {
        $ai = static::aiPopupEnabled();
        $wa = static::waPopupEnabled();

        if (! $ai && ! $wa) {
            return null;
        }

        $config = [
            'aiEnabled' => $ai,
            'waEnabled' => $wa,
            'waNumber' => $wa ? static::number() : '',
            'waMessage' => static::generalMessage(),
            'aiEndpoint' => '',
            'assistantName' => AiSettings::behavior()['name'],
        ];

        // AI sungguhan hanya bila menyala dan lengkap; selain itu widget menjawab dengan kata kunci.
        if ($ai && ChatService::available()) {
            $behavior = AiSettings::behavior();
            [, $images] = ChatService::limits(auth()->user());
            $config = [...$config,
                'aiEndpoint' => route('chat.send'),
                'historyEndpoint' => route('chat.history'),
                'greeting' => PromptBuilder::fill($behavior['greeting'], ['nama' => $behavior['name']]),
                'imagesEnabled' => $images > 0,
                'imageMaxMb' => AiSettings::advanced('chat_image_mb'),
            ];
        }

        return $config;
    }
}
