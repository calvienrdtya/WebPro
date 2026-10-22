<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NotificationSettingsRequest;
use App\Models\Setting;
use App\Services\AdminNotifier;
use App\Support\ImageOptimizer;
use App\Support\Whatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'notifications' => [
                'email_enabled' => Setting::bool(Setting::NOTIFY_EMAIL_ENABLED),
                'email_to' => Setting::get(Setting::NOTIFY_EMAIL_TO, ''),
                'types' => collect(Setting::NOTIFY_TYPES)->map(fn ($label, $type) => ['type' => $type, 'label' => $label, 'enabled' => AdminNotifier::typeEnabled($type)])->values(),
            ],
            'chat' => [
                'ai' => Whatsapp::aiPopupEnabled(),
                'wa' => Setting::bool(Setting::CHAT_WA_ENABLED, true),
                'number' => Whatsapp::number(),
            ],
            'renewal' => [
                'enabled' => Setting::renewalEnabled(),
                'days' => Setting::renewalDays(),
                'min_days' => (int) config('proakses.renewal_reminder.min_days'),
                'max_days' => (int) config('proakses.renewal_reminder.max_days'),
            ],
            'logs' => [
                'system_days' => Setting::systemLogDays(),
                'activity_days' => Setting::activityLogDays(),
                'min_days' => (int) config('proakses.logs.min_days'),
                'max_system_days' => (int) config('proakses.logs.max_system_days'),
                'max_activity_days' => (int) config('proakses.logs.max_activity_days'),
            ],
            'images' => [
                'quality' => Setting::imageQuality(),
                'min' => (int) config('proakses.images.min_quality'),
                'max' => (int) config('proakses.images.max_quality'),
                'webp' => ImageOptimizer::webpSupported(),
            ],
            'qris' => [
                'url' => Setting::qrisUrl(),
                'custom' => (bool) Setting::get(Setting::QRIS_IMAGE),
            ],
        ]);
    }

    public function updateNotifications(NotificationSettingsRequest $request): RedirectResponse
    {
        Setting::put(Setting::NOTIFY_EMAIL_ENABLED, $request->boolean('email_enabled'));
        Setting::put(Setting::NOTIFY_EMAIL_TO, $request->validated('email_to'));
        foreach ($request->validated('types') as $type => $enabled) {
            Setting::put('notify.email.type.'.$type, (bool) $enabled);
        }

        return back()->with('success', 'Pengaturan notifikasi disimpan.');
    }

    /** Pengingat perpanjangan ke pelanggan: saklar dan berapa hari sebelum akun terakhir habis. */
    public function updateRenewal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'days' => ['required', 'integer', 'min:'.config('proakses.renewal_reminder.min_days'), 'max:'.config('proakses.renewal_reminder.max_days')],
        ], [], ['days' => 'Jumlah hari']);

        Setting::put(Setting::RENEWAL_ENABLED, $request->boolean('enabled'));
        Setting::put(Setting::RENEWAL_DAYS, (string) $data['days']);

        return back()->with('success', 'Pengaturan pengingat perpanjangan disimpan.');
    }

    /** Kualitas WebP untuk gambar yang diunggah sesudah ini (gambar lama tetap; optimalkan lagi per gambar). */
    public function updateImages(Request $request): RedirectResponse
    {
        $c = config('proakses.images');
        $data = $request->validate([
            'quality' => ['required', 'integer', 'min:'.$c['min_quality'], 'max:'.$c['max_quality']],
        ], [], ['quality' => 'Kualitas gambar']);

        Setting::put(Setting::IMAGE_QUALITY, (string) $data['quality']);

        return back()->with('success', 'Kualitas gambar disimpan.');
    }

    /** Masa simpan Log Sistem dan Log Aktivitas (hari). Yang lebih lama dihapus cron logs:prune setiap pukul 03.15. */
    public function updateLogs(Request $request): RedirectResponse
    {
        $c = config('proakses.logs');
        $data = $request->validate([
            'system_days' => ['required', 'integer', 'min:'.$c['min_days'], 'max:'.$c['max_system_days']],
            'activity_days' => ['required', 'integer', 'min:'.$c['min_days'], 'max:'.$c['max_activity_days']],
        ], [], ['system_days' => 'Masa simpan log sistem', 'activity_days' => 'Masa simpan log aktivitas']);

        Setting::put(Setting::LOG_SYSTEM_DAYS, (string) $data['system_days']);
        Setting::put(Setting::LOG_ACTIVITY_DAYS, (string) $data['activity_days']);

        return back()->with('success', 'Masa simpan log disimpan.');
    }

    /**
     * Menyalakan atau mematikan pop up Tanya AI dan pop up WhatsApp (pojok kanan bawah toko), masing-masing sendiri.
     * Hanya saklar yang dikirim yang diubah. Berlaku di halaman toko berikutnya yang dibuka pengunjung.
     */
    public function updateChat(Request $request): RedirectResponse
    {
        $messages = [];

        foreach (['chat_ai_enabled' => [Setting::CHAT_AI_ENABLED, 'Tanya AI'], 'chat_wa_enabled' => [Setting::CHAT_WA_ENABLED, 'WhatsApp']] as $field => [$key, $name]) {
            if ($request->has($field)) {
                Setting::put($key, $request->boolean($field));
                $messages[] = "Pop up {$name} ".($request->boolean($field) ? 'dinyalakan' : 'dimatikan').'.';
            }
        }

        return back()->with('success', implode(' ', $messages) ?: 'Tidak ada perubahan.');
    }

    /** Gambar QRIS yang ditampilkan di halaman bayar. Tanpa unggahan, dipakai gambar bawaan. */
    public function updateQris(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'qris' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'qris.required' => 'Pilih gambar QRIS dulu.',
            'qris.image' => 'File harus berupa gambar.',
            'qris.mimes' => 'Gambar QRIS harus berformat JPG, PNG, atau WebP.',
            'qris.max' => 'Ukuran gambar QRIS maksimal 2 MB.',
        ], ['qris' => 'Gambar QRIS']);

        $this->forgetQris();
        Setting::put(Setting::QRIS_IMAGE, Storage::disk('uploads')->putFile('qris', $data['qris']));

        return back()->with('success', 'Gambar QRIS diperbarui.');
    }

    public function resetQris(): RedirectResponse
    {
        $this->forgetQris();
        Setting::put(Setting::QRIS_IMAGE, null);

        return back()->with('success', 'Gambar QRIS dikembalikan ke bawaan.');
    }

    private function forgetQris(): void
    {
        if ($old = Setting::get(Setting::QRIS_IMAGE)) {
            Storage::disk('uploads')->delete($old);
        }
    }
}
