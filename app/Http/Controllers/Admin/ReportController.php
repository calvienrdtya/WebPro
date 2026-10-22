<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportCategory;
use App\Enums\ReportResolution;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkReportRequest;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Models\CustomerReport;
use App\Models\Order;
use App\Models\WarrantyClaim;
use App\Services\ClaimService;
use App\Support\OrderPresenter;
use App\Models\ReportAttachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan pelanggan (customer report). Satu arah: pelanggan melapor, admin menyelesaikan (resolve). */
class ReportController extends Controller
{
    /** Lampiran laporan dari penyimpanan privat; hanya bisa dibuka lewat panel admin. */
    public function attachment(ReportAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk(ReportAttachment::DISK)->exists($attachment->path), 404);

        return Storage::disk(ReportAttachment::DISK)->response($attachment->path, $attachment->name);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), [CustomerReport::OPEN, CustomerReport::RESOLVED], true) ? $request->query('status') : 'all';
        $category = ReportCategory::tryFrom((string) $request->query('category'));
        // Urutan waktu lapor: bawaan terlama dulu (asc), bisa dibalik terbaru dulu (desc).
        $sort = $request->query('sort') === 'desc' ? 'desc' : 'asc';

        $reports = CustomerReport::query()
            ->with(['user:id,name,email,phone', 'attachments', 'resolver:id,name', ...array_map(fn ($r) => 'order.'.$r, OrderPresenter::RELATIONS)])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('subject', 'like', "%{$q}%")
                ->orWhereHas('order', fn (Builder $o) => $o->where('code', 'like', "%{$q}%"))
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))))
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($category, fn (Builder $query) => $query->where('category', $category->value))
            ->orderBy('created_at', $sort)
            ->orderBy('id', $sort)
            ->paginate(15)
            ->withQueryString();

        // Laporan lain dari pelanggan yang sama (bantu admin mengenali laporan ganda).
        $userIds = $reports->getCollection()->pluck('user_id')->unique();
        $siblings = CustomerReport::query()
            ->whereIn('user_id', $userIds)
            ->latest('id')
            ->get(['id', 'user_id', 'category', 'subject', 'status', 'created_at'])
            ->groupBy('user_id');

        // Pesanan lunas milik pelapor (bahan tombol "Pindahkan ke Klaim Garansi") dan klaim yang sudah dibuat dari laporan ini.
        $orders = Order::whereIn('user_id', $userIds)->whereIn('status', ['paid', 'completed'])->latest('id')->get()->groupBy('user_id');
        $claims = WarrantyClaim::whereIn('report_id', $reports->getCollection()->pluck('id'))->latest('id')->get()->groupBy('report_id');

        $reports->through(fn (CustomerReport $r) => [
            'id' => $r->id,
            'order_code' => $r->order?->code,
            // Pesanan yang dipilih pelapor, dalam bentuk yang sama dengan modal detail pesanan.
            'order' => $r->order ? OrderPresenter::row($r->order) : null,
            'orders' => ($orders[$r->user_id] ?? collect())->take(10)->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'product_name' => $o->product_name,
                'variant_title' => $o->variant_title,
                'warranty_name' => $o->warranty_name,
                'warranty_open' => $o->warranty_days !== null && ClaimService::warrantyOpen($o),
                'paid_at' => $o->paid_at?->toIso8601String(),
            ])->values()->all(),
            'claim' => ($c = ($claims[$r->id] ?? collect())->first()) ? ['id' => $c->id, 'status' => $c->status] : null,
            'user' => ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email, 'phone' => $r->user->phone],
            'category' => $r->category->value,
            'category_label' => $r->category->label(),
            'subject' => $r->subject,
            'body' => $r->body,
            'status' => $r->status,
            'resolution' => $r->resolution?->value,
            'resolution_label' => $r->resolution?->label(),
            'resolution_note' => $r->resolution_note,
            'duplicate_of_id' => $r->duplicate_of_id,
            'resolved_by' => $r->resolver?->name,
            'resolved_at' => $r->resolved_at?->toIso8601String(),
            'created_at' => $r->created_at->toIso8601String(),
            'attachments' => $r->attachments->map(fn (ReportAttachment $a) => ['id' => $a->id, 'name' => $a->name, 'url' => $a->url])->all(),
            'others' => ($siblings[$r->user_id] ?? collect())
                ->reject(fn ($o) => $o->id === $r->id)
                ->take(10)
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'category_label' => $o->category->label(),
                    'subject' => $o->subject,
                    'status' => $o->status,
                    'created_at' => $o->created_at->toIso8601String(),
                ])->values()->all(),
        ]);

        $resolvedRecent = CustomerReport::resolved()->latest('resolved_at')->limit(200)->get(['created_at', 'resolved_at']);

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
            'filters' => ['q' => $q, 'status' => $status, 'category' => $category?->value ?? 'all', 'sort' => $sort],
            'counts' => [
                'all' => CustomerReport::count(),
                'open' => CustomerReport::open()->count(),
                'resolved' => CustomerReport::resolved()->count(),
            ],
            'stats' => [
                'waiting' => CustomerReport::open()->count(),
                'resolved_this_month' => CustomerReport::resolved()->where('resolved_at', '>=', now()->startOfMonth())->count(),
                'total' => CustomerReport::count(),
                'avg_resolution_minutes' => $resolvedRecent->isEmpty() ? null
                    : (int) round($resolvedRecent->avg(fn ($r) => $r->created_at->diffInMinutes($r->resolved_at, true))),
            ],
            'categories' => ReportCategory::options(),
            'resolutions' => ReportResolution::options(),
        ]);
    }

    public function resolve(ResolveReportRequest $request, CustomerReport $report): RedirectResponse
    {
        if (! $report->isOpen()) {
            return back()->with('error', 'Laporan ini sudah selesai.');
        }

        $report->resolve(
            ReportResolution::from($request->validated('resolution')),
            $request->validated('resolution_note'),
            $request->validated('duplicate_of_id'),
            $request->user(),
        );

        return back()->with('success', "Laporan \"{$report->subject}\" diselesaikan.");
    }

    public function reopen(CustomerReport $report): RedirectResponse
    {
        if ($report->isOpen()) {
            return back()->with('error', 'Laporan ini belum selesai.');
        }

        $report->reopen();

        return back()->with('success', "Laporan \"{$report->subject}\" dibuka kembali.");
    }

    public function bulkResolve(ResolveReportRequest $request): RedirectResponse
    {
        $resolution = ReportResolution::from($request->validated('resolution'));
        $count = 0;

        DB::transaction(function () use ($request, $resolution, &$count) {
            CustomerReport::open()->whereIn('id', $request->validated('ids'))->get()->each(function (CustomerReport $report) use ($request, $resolution, &$count) {
                $report->resolve($resolution, $request->validated('resolution_note'), null, $request->user());
                $count++;
            });
        });

        return back()->with('success', "{$count} laporan diselesaikan.");
    }

    /** Hanya laporan yang sudah selesai yang boleh dihapus, supaya keluhan yang belum ditangani tidak hilang. */
    public function destroy(CustomerReport $report): RedirectResponse
    {
        if ($report->isOpen()) {
            return back()->with('error', 'Selesaikan laporan lebih dulu sebelum menghapusnya.');
        }

        $this->deleteReports(collect([$report]));

        return back()->with('success', 'Laporan dihapus.');
    }

    public function bulkDestroy(BulkReportRequest $request): RedirectResponse
    {
        $reports = CustomerReport::resolved()->with('attachments')->whereIn('id', $request->validated('ids'))->get();
        $skipped = count($request->validated('ids')) - $reports->count();

        $this->deleteReports($reports);

        return back()->with('success', "{$reports->count()} laporan dihapus.".($skipped ? " {$skipped} laporan yang belum selesai dilewati." : ''));
    }

    private function deleteReports($reports): void
    {
        $reports->each(function (CustomerReport $report) {
            $report->loadMissing('attachments')->attachments->each(fn (ReportAttachment $a) => Storage::disk(ReportAttachment::DISK)->delete($a->path));
            $report->delete();
        });
    }
}
