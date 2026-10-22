<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Tanggal akun mulai aktif (bisa lebih awal dari tanggal input) dan masa aktifnya per akun.
            // expires_at tetap disimpan, tetapi kini selalu dihitung: started_at + active_days.
            $table->date('started_at')->nullable()->after('status');
            $table->unsignedSmallInteger('active_days')->default(28)->after('started_at');
        });

        DB::table('accounts')->orderBy('id')->each(function ($account) {
            $started = Carbon::parse($account->created_at)->startOfDay();
            $days = $account->expires_at
                ? max(1, (int) $started->diffInDays(Carbon::parse($account->expires_at)->startOfDay(), false))
                : 28;

            DB::table('accounts')->where('id', $account->id)->update([
                'started_at' => $started->toDateString(),
                'active_days' => $days,
                'expires_at' => $started->copy()->addDays($days)->toDateString(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'active_days']);
        });
    }
};
