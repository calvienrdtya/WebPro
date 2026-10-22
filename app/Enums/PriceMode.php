<?php

namespace App\Enums;

enum PriceMode: string
{
    case FromProduct = 'from_product';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::FromProduct => 'Ambil dari produk',
            self::Custom => 'Buat sendiri',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
