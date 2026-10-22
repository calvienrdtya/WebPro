<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Ai = 'ai';
    case Streaming = 'streaming';
    case Tools = 'tools';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'Akun AI',
            self::Streaming => 'Streaming',
            self::Tools => 'Tools',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
