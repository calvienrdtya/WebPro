<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
    case Problem = 'problem';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Sold => 'Terjual',
            self::Problem => 'Bermasalah',
            self::Expired => 'Kedaluwarsa',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
