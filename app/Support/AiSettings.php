<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Pengaturan menu AI (Koneksi dan Perilaku), disimpan di tabel settings dengan awalan "ai.".
 * API key disimpan terenkripsi (APP_KEY) dan tidak pernah dikirim utuh ke browser.
 */
class AiSettings
{
    public const ENABLED = 'ai.enabled';

    public const BASE_URL = 'ai.base_url';

    public const API_KEY = 'ai.api_key';

    public const CHAT_MODEL = 'ai.chat_model';

    public const VISION_MODEL = 'ai.vision_model';

    public const REASONING = 'ai.reasoning_effort';

    /** Tingkat berpikir yang dikirim sebagai `reasoning_effort`; kosong (Bawaan) = tidak dikirim. */
    public const REASONING_LEVELS = ['none', 'minimal', 'low', 'medium'];

    public const NAME = 'ai.name';

    public const INSTRUCTIONS = 'ai.instructions';

    public const FALLBACK = 'ai.fallback';

    public const MAX_TOKENS = 'ai.max_tokens';

    public const GREETING = 'ai.greeting';

    public const LIMIT_MESSAGE = 'ai.limit_message';

    public const LIMIT_MESSAGE_GUEST = 'ai.limit_message_guest';

    public const LIMIT_IMAGE_MESSAGE = 'ai.limit_image_message';

    /** Kolom batas harian di form => kunci settings. Nilai bawaan ada di config/ai.php (behavior.limits). */
    public const LIMITS = [
        'guest_messages' => 'ai.limit.guest_messages',
        'guest_images' => 'ai.limit.guest_images',
        'user_messages' => 'ai.limit.user_messages',
        'user_images' => 'ai.limit.user_images',
    ];

    public const TEMPLATE_PREFIX = 'ai.tpl.';

    public const ADVANCED_PREFIX = 'ai.adv.';

    public const STRIP_PREFIXES = 'ai.strip_prefixes';

    public const WA_TRIGGERS = 'ai.whatsapp_triggers';

    public static function enabled(): bool
    {
        return Setting::bool(self::ENABLED);
    }

    public static function baseUrl(): string
    {
        return rtrim(Setting::get(self::BASE_URL) ?: config('ai.base_url'), '/');
    }

    /** API key asli, atau null bila belum diisi atau tidak bisa dibuka lagi (mis. APP_KEY berganti). */
    public static function apiKey(): ?string
    {
        $stored = Setting::get(self::API_KEY);

        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function putApiKey(?string $key): void
    {
        Setting::put(self::API_KEY, $key ? Crypt::encryptString($key) : null);
    }

    /** Versi tersamar untuk ditampilkan di admin, mis. "sk-a...9f3c". */
    public static function maskedApiKey(): ?string
    {
        $key = static::apiKey();

        if ($key === null) {
            return null;
        }

        return strlen($key) <= 8 ? str_repeat('*', strlen($key)) : substr($key, 0, 4).'...'.substr($key, -4);
    }

    public static function chatModel(): ?string
    {
        return Setting::get(self::CHAT_MODEL) ?: null;
    }

    /** Model untuk membaca gambar; kosong berarti sama dengan model chat. */
    public static function visionModel(): ?string
    {
        return Setting::get(self::VISION_MODEL) ?: static::chatModel();
    }

    public static function reasoningEffort(): ?string
    {
        $level = Setting::get(self::REASONING);

        return in_array($level, self::REASONING_LEVELS, true) ? $level : null;
    }

    /** AI siap dipakai bila koneksi lengkap (API key + model chat). */
    public static function configured(): bool
    {
        return static::apiKey() !== null && static::chatModel() !== null;
    }

    public static function behavior(): array
    {
        $defaults = config('ai.behavior');

        return [
            'name' => Setting::get(self::NAME) ?: $defaults['name'],
            'instructions' => Setting::get(self::INSTRUCTIONS) ?? $defaults['instructions'],
            'fallback' => Setting::get(self::FALLBACK) ?: $defaults['fallback'],
            'max_tokens' => (int) (Setting::get(self::MAX_TOKENS) ?: $defaults['max_tokens']),
            'greeting' => Setting::get(self::GREETING) ?: $defaults['greeting'],
            'limit_message' => Setting::get(self::LIMIT_MESSAGE) ?: $defaults['limit_message'],
            'limit_message_guest' => Setting::get(self::LIMIT_MESSAGE_GUEST) ?: $defaults['limit_message_guest'],
            'limit_image_message' => Setting::get(self::LIMIT_IMAGE_MESSAGE) ?: $defaults['limit_image_message'],
            ...collect(self::LIMITS)->map(fn ($key, $field) => (int) (Setting::get($key) ?? $defaults['limits'][$field]))->all(),
        ];
    }

    public static function putBehavior(array $data): void
    {
        Setting::put(self::NAME, $data['name']);
        Setting::put(self::INSTRUCTIONS, $data['instructions'] ?? '');
        Setting::put(self::FALLBACK, $data['fallback']);
        Setting::put(self::MAX_TOKENS, (string) $data['max_tokens']);
        // Teks yang sama dengan bawaan disimpan kosong supaya perbaikan bawaan ikut terpakai.
        foreach (['greeting' => self::GREETING, 'limit_message' => self::LIMIT_MESSAGE, 'limit_message_guest' => self::LIMIT_MESSAGE_GUEST, 'limit_image_message' => self::LIMIT_IMAGE_MESSAGE] as $field => $key) {
            if (array_key_exists($field, $data)) {
                Setting::put($key, $data[$field] === config("ai.behavior.{$field}") ? null : $data[$field]);
            }
        }

        foreach (self::LIMITS as $field => $key) {
            Setting::put($key, (string) $data[$field]);
        }
    }

    /** Template prompt: yang tersimpan, atau bawaan dari config/ai.php. */
    public static function template(string $name): string
    {
        return Setting::get(self::TEMPLATE_PREFIX.$name) ?: config("ai.templates.{$name}");
    }

    /** @return array<string, string> */
    public static function templates(): array
    {
        return collect(config('ai.templates'))->keys()->mapWithKeys(fn ($name) => [$name => static::template($name)])->all();
    }

    /** Template yang sama persis dengan bawaan disimpan kosong, supaya perbaikan bawaan di config ikut terpakai. */
    public static function putTemplates(array $templates): void
    {
        foreach (array_keys(config('ai.templates')) as $name) {
            $value = $templates[$name] ?? null;
            Setting::put(self::TEMPLATE_PREFIX.$name, $value === config("ai.templates.{$name}") ? null : $value);
        }
    }

    public static function advanced(string $key): int
    {
        [$default, $min, $max] = config("ai.advanced.{$key}");
        $value = Setting::get(self::ADVANCED_PREFIX.$key);

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    /** @return array<string, int> */
    public static function advancedAll(): array
    {
        return collect(config('ai.advanced'))->keys()->mapWithKeys(fn ($key) => [$key => static::advanced($key)])->all();
    }

    public static function putAdvanced(array $values): void
    {
        foreach (array_keys(config('ai.advanced')) as $key) {
            Setting::put(self::ADVANCED_PREFIX.$key, (string) $values[$key]);
        }
    }

    /** @return list<string> */
    public static function stripPrefixes(): array
    {
        $stored = Setting::get(self::STRIP_PREFIXES);

        return $stored === null ? config('ai.strip_prefixes') : array_values(array_filter(array_map('trim', explode("\n", $stored))));
    }

    /** @return list<string> */
    public static function whatsappTriggers(): array
    {
        $stored = Setting::get(self::WA_TRIGGERS);

        return $stored === null ? config('ai.whatsapp_triggers') : array_values(array_filter(array_map('trim', explode("\n", $stored))));
    }

    /** @param  list<string>  $triggers */
    public static function putWhatsappTriggers(array $triggers): void
    {
        Setting::put(self::WA_TRIGGERS, implode("\n", $triggers));
    }

    /** @param  list<string>  $prefixes */
    public static function putStripPrefixes(array $prefixes): void
    {
        Setting::put(self::STRIP_PREFIXES, implode("\n", $prefixes));
    }
}
