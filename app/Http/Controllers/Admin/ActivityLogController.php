<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\ActivityPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Menu Log Aktivitas: siapa melakukan apa, terbaru dulu. */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $modules = config('activity.modules');
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q')), 0, 100),
            'actor' => array_key_exists((string) $request->query('actor'), ActivityLog::ACTORS) ? $request->query('actor') : 'all',
            'module' => array_key_exists((string) $request->query('module'), $modules) ? $request->query('module') : 'all',
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
            // Dari tombol riwayat di modal Pesanan/User: ?subjek=order:12 atau ?user=5.
            'subject' => preg_match('/^[a-z_]+:\d+$/', (string) $request->query('subjek')) ? $request->query('subjek') : null,
            'user' => ctype_digit((string) $request->query('user')) ? (int) $request->query('user') : null,
        ];

        $base = ActivityLog::query()
            ->when($filters['q'] !== '', fn (Builder $q) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.$filters['q'].'%')
                ->orWhere('actor_name', 'like', '%'.$filters['q'].'%')
                ->orWhere('actor_email', 'like', '%'.$filters['q'].'%')
                ->orWhere('subject_label', 'like', '%'.$filters['q'].'%')
                ->orWhere('ip', $filters['q'])))
            ->when($filters['module'] !== 'all', fn (Builder $q) => $q->where('module', $filters['module']))
            ->when($filters['from'], fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when($filters['to'], fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->when($filters['subject'], function (Builder $q) use ($filters) {
                [$type, $id] = explode(':', $filters['subject']);
                $q->where('subject_type', $type)->where('subject_id', $id);
            })
            ->when($filters['user'], fn (Builder $q) => $q->forUser($filters['user']));

        $counts = (clone $base)->selectRaw('actor_type, count(*) as total')->groupBy('actor_type')->pluck('total', 'actor_type');

        $logs = (clone $base)
            ->when($filters['actor'] !== 'all', fn (Builder $q) => $q->where('actor_type', $filters['actor']))
            ->latest('id')
            ->paginate((int) config('proakses.logs.per_page'))
            ->withQueryString();

        // Perubahan diterjemahkan sekaligus per halaman (nama kolom, Rupiah, status, ID menjadi nama).
        $changes = (new ActivityPresenter)->present($logs->getCollection()->map(fn (ActivityLog $l) => $l->changes ?? [])->all());
        $logs->setCollection($logs->getCollection()->values()->map(fn (ActivityLog $log, int $i) => [
            'id' => $log->id,
            'created_at' => $log->created_at->toIso8601String(),
            'actor_type' => $log->actor_type,
            'actor_id' => $log->actor_id,
            'actor_name' => $log->actor_name,
            'actor_email' => $log->actor_email,
            'action' => $log->action,
            'module' => $log->module,
            'module_label' => $modules[$log->module] ?? $log->module,
            'description' => $log->description,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'subject_label' => $log->subject_label,
            'changes' => $changes[$i],
            'ip' => $log->ip,
            'user_agent' => $log->user_agent,
        ]));

        return Inertia::render('Logs/Activity', [
            'logs' => $logs,
            'filters' => $filters,
            'counts' => ['all' => $counts->sum(), ...collect(ActivityLog::ACTORS)->map(fn ($l, $k) => (int) ($counts[$k] ?? 0))->all()],
            'actors' => collect(ActivityLog::ACTORS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'modules' => collect($modules)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'retentionDays' => Setting::activityLogDays(),
        ]);
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : null;
    }
}
