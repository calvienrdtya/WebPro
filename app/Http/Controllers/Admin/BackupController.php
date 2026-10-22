<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Activity;
use App\Support\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Menu Backup: buat backup database sekarang, atur backup otomatis, unduh dan hapus file backup. */
class BackupController extends Controller
{
    public function __construct(private DatabaseBackup $backup) {}

    public function index(): Response
    {
        $c = config('proakses.backup');

        return Inertia::render('Backups/Index', [
            'backups' => $this->backup->all(),
            'settings' => [
                'enabled' => Setting::backupEnabled(),
                'interval_days' => Setting::backupIntervalDays(),
                'keep' => Setting::backupKeep(),
                'max_interval_days' => $c['max_interval_days'],
                'max_keep' => $c['max_keep'],
                'time' => $c['time'],
            ],
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $result = $this->backup->create('manual');
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Backup gagal dibuat. Detail galat ada di Log Sistem.');
        }
        Activity::note($result['name'].' ('.Number::fileSize($result['size'], 1).')');

        return back()->with('success', "Backup dibuat: {$result['tables']} tabel, ".number_format($result['rows'], 0, ',', '.').' baris ('.Number::fileSize($result['size'], 1).').');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $c = config('proakses.backup');
        $data = $request->validate([
            'enabled' => ['boolean'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:'.$c['max_interval_days']],
            'keep' => ['required', 'integer', 'min:1', 'max:'.$c['max_keep']],
        ], [], ['interval_days' => 'Selang hari', 'keep' => 'Jumlah backup disimpan']);

        Setting::put(Setting::BACKUP_ENABLED, $request->boolean('enabled'));
        Setting::put(Setting::BACKUP_INTERVAL_DAYS, (string) $data['interval_days']);
        Setting::put(Setting::BACKUP_KEEP, (string) $data['keep']);
        $deleted = $this->backup->prune($data['keep']);

        return back()->with('success', 'Pengaturan backup disimpan.'.($deleted ? " {$deleted} backup lama dihapus." : ''));
    }

    public function download(string $file): BinaryFileResponse
    {
        $path = $this->backup->path($file);
        abort_if($path === null, 404);
        Activity::note($file);

        return response()->download($path, $file, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(string $file): RedirectResponse
    {
        abort_unless($this->backup->delete($file), 404);
        Activity::note($file);

        return back()->with('success', "Backup {$file} dihapus.");
    }
}
