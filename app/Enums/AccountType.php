<?php

namespace App\Enums;

enum AccountType: string
{
    case Netflix = 'netflix';
    case Disney = 'disney';
    case Hbo = 'hbo';
    case Youtube = 'youtube';
    case Spotify = 'spotify';
    case Canva = 'canva';
    case Capcut = 'capcut';
    case Claude = 'claude';
    case Chatgpt = 'chatgpt';

    public function label(): string
    {
        return match ($this) {
            self::Netflix => 'Netflix',
            self::Disney => 'Disney+ Hotstar',
            self::Hbo => 'HBO Max',
            self::Youtube => 'YouTube Premium',
            self::Spotify => 'Spotify',
            self::Canva => 'Canva',
            self::Capcut => 'CapCut',
            self::Claude => 'Claude',
            self::Chatgpt => 'ChatGPT',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
