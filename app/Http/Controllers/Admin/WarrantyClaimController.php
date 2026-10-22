<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClaimReason;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CustomerReport;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\WarrantyClaim;
use App\Services\ClaimService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WarrantyClaimController extends Controller
{
    private const STATUSES = [WarrantyClaim::OPEN, WarrantyClaim::DONE, WarrantyClaim::CANCELLED];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : 'all';

        $claims = WarrantyClaim::query()
            ->with(['order', 'report:id,subject', 'oldAccount', 'newAccount', 'handler:id,name'])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->whereHas('order', fn (Builder $o) => $o->where('code', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")->orWhere('customer_email', 'like', "%{$q}%")->orWhere('product_name', 'like', "%{$q}%"))
                ->orWhere('note', 'like', "%{$q}%")))
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            // Yang menunggu penggantian paling atas, lalu terbaru.
            ->orderByRaw("status = 'open' desc")
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (WarrantyClaim $c) => [
                'id' => $c->id,
                'status' => $c->status,
                'source' => $c->source,
                'reason' => $c->reason ? (ClaimReason::tryFrom($c->reason)?->label() ?? $c->reason) : null,
                'note' => $c->note,
                'outside_warranty' => $c->outside_warranty,
                'mode' => $c->mode,
                'order' => [
                    'id' => $c->order->id, 'code' => $c->order->code, 'customer_name' => $c->order->customer_name,
                    'customer_email' => $c->order->customer_email, 'product_name' => $c->order->product_name,
                    'warranty_name' => $c->order->warranty_name,
                ],
                'report' => $c->report ? ['id' => $c->report->id, 'subject' => $c->report->subject] : null,
                'old_account' => $c->oldAccount ? ['email' => $c->oldAccount->email, 'profile' => $c->oldAccount->profile] : null,
                'new_account' => $c->newAccount ? ['email' => $c->newAccount->email, 'profile' => $c->newAccount->profile] : null,
                'handled_by' => $c->handler?->name,
                'created_at' => $c->created_at->toIso8601String(),
                'replaced_at' => $c->replaced_at?->toIso8601String(),
            ]);

        $counts = WarrantyClaim::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $done = WarrantyClaim::where('status', WarrantyClaim::DONE);

        return Inertia::render('Claims/Index', [
            'claims' => $claims,
            'filters' => ['q' => $q, 'status' => $status],
            'counts' => [
                'all' => (int) $counts->sum(),
                'open' => (int) ($counts[WarrantyClaim::OPEN] ?? 0),
                'done' => (int) ($counts[WarrantyClaim::DONE] ?? 0),
                'cancelled' => (int) ($counts[WarrantyClaim::CANCELLED] ?? 0),
            ],
            'stats' => [
                'total' => (int) ($counts[WarrantyClaim::DONE] ?? 0),
                'waiting' => (int) ($counts[WarrantyClaim::OPEN] ?? 0),
                'this_month' => (clone $done)->where('replaced_at', '>=', now()->startOfMonth())->count(),
            ],
        ]);
    }

    /** Bahan dialog Ganti Akun: kiriman yang sudah terkirim, status garansi, dan akun pengganti dari stok. */
    public function options(Order $order, ClaimService $claims): JsonResponse
    {
        abort_unless($order->isPaid(), 404);

        $deliveries = $order->deliveries()->with('account')->whereNotNull('delivered_at')->get()->map(function (OrderDelivery $d) {
            $a = $d->account;

            return [
                'id' => $d->id,
                'sequence' => $d->sequence,
                'delivered_at' => $d->delivered_at->toIso8601String(),
                'warranty_ends_on' => $d->warranty_ends_on?->toDateString(),
                'within_warranty' => $d->warranty_ends_on !== null && $d->warranty_ends_on->gte(today()),
                'account' => $a ? [
                    'email' => $a->email, 'profile' => $a->profile,
                    'started_at' => $a->started_at?->toDateString(), 'expires_at' => $a->expires_at?->toDateString(),
                    'days_left' => $a->expires_at ? max(0, (int) today()->diffInDays($a->expires_at, false)) : null,
                ] : null,
            ];
        })->values();

        return response()->json([
            'order' => [
                'id' => $order->id, 'code' => $order->code, 'customer_name' => $order->customer_name, 'product_name' => $order->product_name,
                'months' => $order->months, 'paid_at' => $order->paid_at?->toDateString(), 'warranty_name' => $order->warranty_name,
                'age_days' => $order->paid_at ? (int) $order->paid_at->startOfDay()->diffInDays(today()) : null,
            ],
            'deliveries' => $deliveries,
            'open_claim_id' => $order->claims()->where('status', WarrantyClaim::OPEN)->value('id'),
            'reasons' => ClaimReason::options(),
            'replacements' => $claims->replacementCandidates($order)->map(fn (Account $a) => [
                'id' => $a->id, 'email' => $a->email, 'profile' => $a->profile,
                'expires_at' => $a->expires_at?->toDateString(),
                'days_left' => $a->expires_at ? max(0, (int) today()->diffInDays($a->expires_at, false)) : null,
                'users' => (int) $a->users_count,
                'from_order' => $a->deliveries->first()?->order?->code,
            ])->values(),
            'candidates' => $claims->stockCandidates($order)->map(fn (Account $a) => [
                'id' => $a->id, 'email' => $a->email, 'profile' => $a->profile, 'cogs' => (int) ($a->cogs ?? 0),
                'expires_at' => $a->expires_at?->toDateString(),
                'days_left' => $a->expires_at ? max(0, (int) today()->diffInDays($a->expires_at, false)) : null,
            ])->values(),
        ]);
    }

    public function replace(Request $request, ClaimService $claims, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'delivery_id' => ['required', 'integer', Rule::exists('order_deliveries', 'id')->where('order_id', $order->id)],
            'account_id' => ['required', 'integer'],
            'reason' => ['required', Rule::enum(ClaimReason::class)],
            'note' => ['nullable', 'string', 'max:200'],
            'confirm_outside' => ['boolean'],
            'claim_id' => ['nullable', 'integer'],
            'mode' => ['nullable', Rule::in(['stock', 'replacement'])],
        ], [], ['delivery_id' => 'Akun yang diganti', 'account_id' => 'Akun pengganti', 'reason' => 'Alasan', 'note' => 'Catatan']);

        try {
            $claims->replace(
                $order, $order->deliveries()->findOrFail($data['delivery_id']), (int) $data['account_id'],
                ClaimReason::from($data['reason']), $data['note'] ?? null, $request->user(),
                (bool) ($data['confirm_outside'] ?? false), isset($data['claim_id']) ? (int) $data['claim_id'] : null, $data['mode'] ?? 'stock',
            );
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Akun pesanan {$order->code} diganti dan email akun baru dikirim ke {$order->customer_email}.");
    }

    public function cancel(ClaimService $claims, WarrantyClaim $claim): RedirectResponse
    {
        try {
            $claims->cancel($claim);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Klaim dibatalkan.');
    }

    /** Laporan "Akun bermasalah" dipindahkan ke Klaim Garansi untuk pesanan tertentu milik pelapor. */
    public function openFromReport(Request $request, ClaimService $claims, CustomerReport $report): RedirectResponse
    {
        $data = $request->validate(['order_id' => ['required', 'integer', 'exists:orders,id']], [], ['order_id' => 'Pesanan']);

        try {
            $claims->openFromReport($report, Order::findOrFail($data['order_id']), $request->user());
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Laporan dipindahkan ke Klaim Garansi. Lanjutkan penggantian akun dari halaman Klaim Garansi.');
    }
}
