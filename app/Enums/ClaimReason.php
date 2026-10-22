<?php

namespace App\Enums;

/** Alasan akun diganti dan masuk Bengkel (dipilih admin saat Ganti Akun). */
enum ClaimReason: string
{
    case Backfree = 'backfree';
    case Limit = 'limit';
    case Household = 'household';
    case IncorrectPassword = 'incorrect_password';
    case UpdatePayment = 'update_payment';
    case ScreenLimit = 'screen_limit';
    case LoginLimit = 'login_limit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Backfree => 'Backfree',
            self::Limit => 'Limit',
            self::Household => 'Household',
            self::IncorrectPassword => 'Incorrect Password',
            self::UpdatePayment => 'Update Payment',
            self::ScreenLimit => 'Screen Limit',
            self::LoginLimit => 'Login Limit',
            self::Other => 'Other',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
