<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkVoucherRequest;
use App\Http\Requests\Admin\VoucherRequest;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    private const FIELDS = [
        'code', 'title', 'description', 'discount_type', 'discount_value', 'max_discount', 'min_purchase',
        'starts_on', 'ends_on', 'quota', 'per_user_limit', 'requires_claim', 'is_listed', 'is_active', 'applies_to_all',
    ];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), Voucher::STATUSES, true) ? $request->query('status') : 'all';

        $vouchers = Voucher::query()
            ->with('products:id,name')
            ->withCount('claims')
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('code', 'like', '%'.strtoupper($q).'%')
                ->orWhere('title', 'like', "%{$q}%")))
            ->when($status !== 'all', fn (Builder $query) => $query->withStatus($status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Voucher $v) => [
                'id' => $v->id,
                'code' => $v->code,
                'title' => $v->title,
                'description' => $v->description,
                'discount_type' => $v->discount_type->value,
                'discount_value' => $v->discount_value,
                'max_discount' => $v->max_discount,
                'min_purchase' => $v->min_purchase,
                'starts_on' => $v->starts_on?->toDateString(),
                'ends_on' => $v->ends_on?->toDateString(),
                'quota' => $v->quota,
                'used_count' => $v->used_count,
                'claims_count' => $v->claims_count,
                'per_user_limit' => $v->per_user_limit,
                'requires_claim' => $v->requires_claim,
                'is_listed' => $v->is_listed,
                'is_active' => $v->is_active,
                'applies_to_all' => $v->applies_to_all,
                'status' => $v->status(),
                'products' => $v->products->sortBy('name')->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ]);

        $counts = ['all' => Voucher::count()];
        foreach (Voucher::STATUSES as $s) {
            $counts[$s] = Voucher::withStatus($s)->count();
        }

        return Inertia::render('Vouchers/Index', [
            'vouchers' => $vouchers,
            'filters' => ['q' => $q, 'status' => $status],
            'counts' => $counts,
            'stats' => [
                'total' => $counts['all'],
                'running' => $counts['running'],
                'claims' => (int) DB::table('user_vouchers')->count(),
                'used' => (int) Voucher::sum('used_count'),
            ],
            'products' => Product::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->all(),
        ]);
    }

    public function store(VoucherRequest $request): RedirectResponse
    {
        $voucher = DB::transaction(function () use ($request) {
            $voucher = Voucher::create($request->safe()->only(self::FIELDS));
            $this->syncProducts($voucher, $request);

            return $voucher;
        });

        return back()->with('success', "Voucher {$voucher->code} ditambahkan.");
    }

    public function update(VoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        DB::transaction(function () use ($request, $voucher) {
            $voucher->update($request->safe()->only(self::FIELDS));
            $this->syncProducts($voucher, $request);
        });

        return back()->with('success', "Voucher {$voucher->code} diperbarui.");
    }

    /** Voucher "semua produk" tidak menyimpan daftar produk; berlaku otomatis untuk produk baru juga. */
    private function syncProducts(Voucher $voucher, VoucherRequest $request): void
    {
        $voucher->products()->sync($voucher->applies_to_all ? [] : $request->validated('product_ids', []));
    }

    public function toggle(Voucher $voucher): RedirectResponse
    {
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('success', "Voucher {$voucher->code} ".($voucher->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function bulkActive(BulkVoucherRequest $request): RedirectResponse
    {
        $active = $request->boolean('active');
        $count = Voucher::whereIn('id', $request->validated('ids'))->update(['is_active' => $active]);

        return back()->with('success', "{$count} voucher ".($active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function bulkDestroy(BulkVoucherRequest $request): RedirectResponse
    {
        $count = Voucher::whereIn('id', $request->validated('ids'))->delete();

        return back()->with('success', "{$count} voucher dihapus.");
    }

    public function destroy(Voucher $voucher): RedirectResponse
    {
        $voucher->delete();

        return back()->with('success', "Voucher {$voucher->code} dihapus.");
    }
}
