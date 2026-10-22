<?php

namespace App\Enums;

enum FaqCategory: string
{
    case General = 'umum';
    case Payment = 'pembayaran';
    case Account = 'akun';
    case Warranty = 'garansi';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Umum',
            self::Payment => 'Pembayaran',
            self::Account => 'Akun',
            self::Warranty => 'Garansi',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
