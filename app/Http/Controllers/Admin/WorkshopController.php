<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\ClaimReason;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\WarrantyClaim;
use App\Services\ClaimService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Bengkel: akun rusak yang sudah diganti lewat klaim garansi. Di sini admin memperbaikinya lalu mengembalikannya ke stok. */
class WorkshopController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));
        $state = in_array($request->query('status'), ['workshop', 'out'], true) ? $request->query('status') : 'all';
        $problem = AccountStatus::Problem->value;

        $base = fn () => WarrantyClaim::query()->where('status', WarrantyClaim::DONE)->whereNotNull('old_account_id');

        $items = $base()
            ->with(['oldAccount', 'order'])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->whereHas('oldAccount', fn (Builder $a) => $a->where('email', 'like', "%{$q}%")->orWhere('profile', 'like', "%{$q}%"))
                ->orWhereHas('order', fn (Builder $o) => $o->where('code', 'like', "%{$q}%")->orWhere('product_name', 'like', "%{$q}%"))))
            ->when($state === 'workshop', fn (Builder $query) => $query->whereHas('oldAccount', fn (Builder $a) => $a->where('status', $problem)))
            ->when($state === 'out', fn (Builder $query) => $query->whereHas('oldAccount', fn (Builder $a) => $a->where('status', '!=', $problem)))
            ->latest('replaced_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function (WarrantyClaim $c) use ($problem) {
                $a = $c->oldAccount;

                return [
                    'id' => $c->id,
                    'in_workshop' => $a?->status->value === $problem,
                    'account' => $a ? [
                        'email' => $a->email, 'password' => $a->password, 'profile' => $a->profile, 'pin' => $a->pin,
                        'status' => $a->status->value, 'status_label' => $a->status->label(), 'expires_at' => $a->expires_at?->toDateString(),
                        'product_name' => $c->order->product_name,
                    ] : null,
                    'reason' => $c->reason ? (ClaimReason::tryFrom($c->reason)?->label() ?? $c->reason) : null,
                    'note' => $c->note,
                    'order_code' => $c->order->code,
                    'replaced_at' => $c->replaced_at?->toIso8601String(),
                ];
            });

        $inWorkshop = $base()->whereHas('oldAccount', fn (Builder $a) => $a->where('status', $problem))->count();
        $all = $base()->count();

        return Inertia::render('Workshop/Index', [
            'items' => $items,
            'filters' => ['q' => $q, 'status' => $state],
            'counts' => ['all' => $all, 'workshop' => $inWorkshop, 'out' => $all - $inWorkshop],
        ]);
    }

    public function restock(ClaimService $claims, WarrantyClaim $claim): RedirectResponse
    {
        try {
            $claims->restock($claim);
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Akun dikembalikan ke stok.');
    }
}
