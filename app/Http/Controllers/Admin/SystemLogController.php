<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\SystemLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Menu Log Sistem: penampil file log Laravel (storage/logs) per tanggal. */
class SystemLogController extends Controller
{
    public function __construct(private SystemLog $log) {}

    public function index(Request $request): Response
    {
        $files = $this->log->files();
        $names = array_column($files, 'name');
        $file = in_array($request->query('file'), $names, true) ? $request->query('file') : ($names[0] ?? null);
        $level = array_key_exists((string) $request->query('level'), SystemLog::LEVELS) ? $request->query('level') : null;
        $q = mb_substr(trim((string) $request->query('q')), 0, 200);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) config('proakses.logs.per_page');

        $result = $file
            ? $this->log->read($file, $level, $q, $page, $perPage)
            : ['entries' => [], 'total' => 0, 'counts' => array_fill_keys(array_keys(SystemLog::LEVELS), 0), 'partial' => false];

        // Membuka menu ini berarti error yang ada sudah dilihat: badge sidebar kembali nol.
        $this->log->markSeen();

        return Inertia::render('Logs/System', [
            'files' => $files,
            'file' => $file,
            'filters' => ['level' => $level ?? 'all', 'q' => $q],
            'entries' => $result['entries'],
            'total' => $result['total'],
            'counts' => $result['counts'],
            'partial' => $result['partial'],
            'page' => $page,
            'perPage' => $perPage,
            'retentionDays' => Setting::systemLogDays(),
            'maxReadMb' => (int) config('proakses.logs.max_read_mb'),
            'logLevel' => (string) config('logging.channels.daily.level'),
        ]);
    }

    public function show(string $file, int $offset): JsonResponse
    {
        $entry = $this->log->entry($file, $offset);
        abort_if($entry === null, 404, 'Entri log tidak ditemukan. File mungkin sudah berubah, muat ulang halaman.');

        return response()->json($entry);
    }

    public function download(string $file): BinaryFileResponse
    {
        $path = $this->log->path($file);
        abort_if($path === null, 404);

        return response()->download($path, $file, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function destroy(string $file): RedirectResponse
    {
        abort_if($this->log->path($file) === null, 404);
        $this->log->delete($file);

        return redirect()->route('admin.logs.system')->with('success', "File {$file} dihapus.");
    }
}
