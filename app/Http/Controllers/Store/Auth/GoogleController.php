<?php

namespace App\Http\Controllers\Store\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return $this->failed('Login Google belum dikonfigurasi.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);

            return $this->failed('Login Google gagal atau dibatalkan. Silakan coba lagi.');
        }

        $email = Str::lower((string) $google->getEmail());

        if ($email === '' || ! ($google->user['email_verified'] ?? false)) {
            return $this->failed('Email Google kamu belum terverifikasi.');
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? $this->linkOrCreate($google, $email);

        if ($user->isBlocked() && ! $user->is_admin) {
            return $this->failed(User::BLOCKED_MESSAGE);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended($user->homeUrl());
    }

    private function linkOrCreate($google, string $email): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return User::forceCreate([
                'name' => $google->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $google->getId(),
                'avatar' => $google->getAvatar(),
                'email_verified_at' => now(),
            ]);
        }

        // Email lokal belum pernah diverifikasi: cabut password agar pendaftar palsu dengan email ini kehilangan akses.
        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar' => $user->avatar ?? $google->getAvatar(),
            'password' => $user->email_verified_at === null ? null : $user->password,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
