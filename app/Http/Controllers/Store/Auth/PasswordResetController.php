<?php

namespace App\Http\Controllers\Store\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Services\PasswordResetCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    private const SEND_COOLDOWN = 30;

    private const SENDS_PER_HOUR = 5;

    private const MAX_ATTEMPTS = 5;

    private const VERIFIED_MINUTES = 10;

    public function __construct(private PasswordResetCodes $codes) {}

    public static function sendThrottleEnabled(): bool
    {
        return app()->isProduction() || config('proakses.password_reset_throttle');
    }

    public function create(): View
    {
        return view('store.auth.forgot-password');
    }

    public function send(Request $request): JsonResponse
    {
        $email = $this->email($request);
        $key = sha1($email);

        $throttle = self::sendThrottleEnabled();

        if ($throttle) {
            if (RateLimiter::tooManyAttempts("pw-hour:$key", self::SENDS_PER_HOUR)) {
                return $this->tooMany('Terlalu banyak permintaan kode. Coba lagi nanti.', RateLimiter::availableIn("pw-hour:$key"));
            }

            if (! RateLimiter::attempt("pw-send:$key", 1, fn () => true, self::SEND_COOLDOWN)) {
                return $this->tooMany('Tunggu sebentar sebelum meminta kode lagi.', RateLimiter::availableIn("pw-send:$key"));
            }

            RateLimiter::hit("pw-hour:$key", 3600);
        }

        RateLimiter::clear("pw-verify:$key");

        // Respons selalu sama, ada atau tidaknya akun, agar email terdaftar tidak bisa ditebak.
        $user = User::where('email', $email)->first();

        if ($user) {
            $code = $this->codes->issue($email);

            // Sengaja tidak lewat antrean (yang berjalan tiap menit): kode harus sampai dalam hitungan detik,
            // dan meminta ulang membuat kode lama tidak berlaku.
            defer(function () use ($user, $code) {
                try {
                    Mail::to($user)->send(new PasswordResetCodeMail($user->name, $code));
                } catch (Throwable $e) {
                    report($e);
                }
            });
        }

        return response()->json([
            'expires_in' => PasswordResetCodes::TTL_SECONDS,
            'resend_in' => $throttle ? self::SEND_COOLDOWN : 0,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $email = $this->email($request);
        $code = $request->validate(['code' => ['required', 'digits:6']])['code'];
        $key = "pw-verify:".sha1($email);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return $this->locked();
        }

        if ($this->codes->consume($email, $code)) {
            RateLimiter::clear($key);
            $request->session()->put('password_reset', [
                'email' => $email,
                'expires_at' => now()->addMinutes(self::VERIFIED_MINUTES)->timestamp,
            ]);

            return response()->json(['ok' => true]);
        }

        RateLimiter::hit($key, PasswordResetCodes::TTL_SECONDS);
        $left = max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($key));

        if ($left === 0) {
            $this->codes->forget($email);

            return $this->locked();
        }

        return response()->json([
            'message' => "Kode salah. Sisa percobaan: $left.",
            'attempts_left' => $left,
        ], 422);
    }

    public function update(Request $request): JsonResponse
    {
        $reset = $request->session()->get('password_reset');
        $user = $reset && $reset['expires_at'] >= now()->timestamp
            ? User::where('email', $reset['email'])->first()
            : null;

        if (! $user) {
            $request->session()->forget('password_reset');

            return response()->json([
                'code' => 'session_expired',
                'message' => 'Sesi verifikasi berakhir. Ulangi dari awal.',
            ], 422);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->forceFill([
            'password' => $data['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $request->session()->forget('password_reset');

        return response()->json(['ok' => true]);
    }

    private function email(Request $request): string
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return Str::lower($data['email']);
    }

    private function tooMany(string $message, int $retryAfter): JsonResponse
    {
        return response()->json(['message' => $message, 'retry_after' => $retryAfter], 429);
    }

    private function locked(): JsonResponse
    {
        return response()->json([
            'code' => 'locked',
            'message' => 'Terlalu banyak percobaan salah. Minta kode baru.',
        ], 429);
    }
}
