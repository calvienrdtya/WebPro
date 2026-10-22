<?php

namespace App\Http\Middleware;

use App\Support\Activity;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat aksi admin dan pelanggan yang terdaftar di config activity.routes ke Log Aktivitas.
 * Dicatat hanya bila aksi berhasil: bukan galat, tidak ada galat validasi, dan tidak ada pesan "error".
 */
class RecordActivity
{
    private const GUEST_ROUTES = ['password.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName();
        $entry = $name ? (config('activity.routes')[$name] ?? null) : null;

        if ($entry === null || ($request->isMethodSafe() && ! in_array($name, config('activity.get_routes'), true))) {
            return $next($request);
        }

        // Pelaku diambil sebelum aksi: setelah "hapus akun sendiri" user sudah keluar dan nama/emailnya dianonimkan.
        $actor = $request->user();
        $actorName = $actor?->name;
        $actorEmail = $actor?->email;
        Activity::start();
        try {
            $response = $next($request);
        } finally {
            $scope = Activity::stop();
        }

        // Tanpa user login hanya lupa sandi yang sah; selain itu tamu cuma diarahkan ke halaman login.
        if (($actor !== null || in_array($name, self::GUEST_ROUTES, true)) && $this->succeeded($request, $response)) {
            [$module, $label] = $entry;
            $subject = $this->subject($request, $scope['changes']);
            $ids = $request->input('ids');
            $description = $label
                .(isset($subject['label']) ? ' '.$subject['label'] : '')
                .(is_array($ids) ? ' ('.count($ids).' item)' : '')
                .($scope['notes'] !== [] ? ': '.implode(', ', $scope['notes']) : '');

            Activity::record($name, $module, $description, [
                'actor' => $actor,
                'subject' => $subject,
                'changes' => $scope['changes'],
                'more' => $scope['more'],
                'actor_name' => $actorName,
                // Lupa sandi: pelakunya tamu, emailnya dari user yang sandinya diatur ulang.
                'actor_email' => $actor ? $actorEmail : (($subject['type'] ?? null) === 'user' ? $subject['label'] : null),
            ]);
        }

        return $response;
    }

    private function succeeded(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return false;
        }
        if (! $request->hasSession()) {
            return true;
        }
        // Hanya flash yang dibuat di permintaan ini (flash permintaan sebelumnya juga masih terbaca).
        $new = (array) $request->session()->get('_flash.new', []);

        return ! in_array('errors', $new, true) && ! in_array('error', $new, true);
    }

    /** Subjek utama: model di parameter rute, atau model pertama yang dibuat/diubah (mis. pesanan baru saat checkout). */
    private function subject(Request $request, array $changes): ?array
    {
        foreach ($request->route()?->parameters() ?? [] as $param) {
            if ($param instanceof Model && isset(config('activity.models')[$param::class])) {
                return ['type' => Activity::alias($param), 'id' => $param->getKey(), 'label' => Activity::label($param)];
            }
        }
        // Pengaturan (kunci teks) tidak dijadikan subjek: kalimatnya sudah cukup, mis. "Mengubah pengaturan backup".
        $changes = array_values(array_filter($changes, fn ($c) => ($c['type'] ?? null) !== 'setting'));
        $first = collect($changes)->firstWhere('event', 'created') ?? $changes[0] ?? null;

        return $first ? ['type' => $first['type'], 'id' => $first['id'], 'label' => $first['label']] : null;
    }
}
