<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PasswordResetCodes
{
    public const TTL_SECONDS = 300;

    public function issue(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => $this->hash($email, $code), 'created_at' => now()],
        );

        return $code;
    }

    public function consume(string $email, string $code): bool
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row) {
            return false;
        }

        if (Carbon::parse($row->created_at)->addSeconds(self::TTL_SECONDS)->isPast()) {
            $this->forget($email);

            return false;
        }

        if (! hash_equals($row->token, $this->hash($email, $code))) {
            return false;
        }

        $this->forget($email);

        return true;
    }

    public function forget(string $email): void
    {
        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }

    private function hash(string $email, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$code, (string) config('app.key'));
    }
}
