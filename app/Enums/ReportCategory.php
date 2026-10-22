<?php

namespace App\Enums;

enum ReportCategory: string
{
    case Account = 'account';
    case Payment = 'payment';
    case OrderNotReceived = 'order_not_received';
    case Question = 'question';
    case Suggestion = 'suggestion';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Akun bermasalah',
            self::Payment => 'Pembayaran',
            self::OrderNotReceived => 'Pesanan belum diterima',
            self::Question => 'Pertanyaan',
            self::Suggestion => 'Saran',
            self::Other => 'Lainnya',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
