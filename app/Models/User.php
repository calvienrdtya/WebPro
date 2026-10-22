<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'google_id', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }

    public const BLOCKED_MESSAGE = 'Akun ini diblokir. Hubungi admin untuk bantuan.';

    /** Semua user yang bukan admin (mereka yang mendaftar sebagai pembeli). */
    public function scopeNonAdmin(Builder $query): Builder
    {
        return $query->where('is_admin', false);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CustomerReport::class);
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function homeUrl(): string
    {
        return $this->is_admin ? route('admin.dashboard') : route('profil');
    }
}
