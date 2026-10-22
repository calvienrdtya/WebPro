<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Services\VoucherPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Halaman voucher publik: daftar voucher yang bisa dilihat dan, bila perlu, diklaim pelanggan. */
class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = in_array($request->query('status'), ['tersedia', 'diklaim'], true) ? $request->query('status') : 'all';

        $claimedIds = $user ? UserVoucher::where('user_id', $user->id)->pluck('voucher_id')->all() : [];

        // Voucher berjalan yang ditampilkan di toko, ditambah voucher milik user yang sudah berakhir (tampil sebagai kadaluarsa).
        $running = Voucher::withStatus('running')->where('is_listed', true)->with('products:id,name')->latest('id')->get();
        $expired = $claimedIds
            ? Voucher::where('is_active', true)->whereIn('id', $claimedIds)->whereNotIn('id', $running->modelKeys())->with('products:id,name')->latest('id')->get()
            : collect();

        $items = $running->map(fn (Voucher $v) => VoucherPresenter::present($v, match (true) {
            in_array($v->id, $claimedIds, true) => 'claimed',
            $v->requires_claim => 'available',
            default => 'direct',
        }))->concat($expired->map(fn (Voucher $v) => VoucherPresenter::present($v, 'expired')))->values();

        $counts = [
            'all' => $items->count(),
            'tersedia' => $items->whereIn('state', ['available', 'direct'])->count(),
            'diklaim' => $items->whereIn('state', ['claimed', 'expired'])->count(),
        ];

        $shown = match ($filter) {
            'tersedia' => $items->whereIn('state', ['available', 'direct']),
            'diklaim' => $items->whereIn('state', ['claimed', 'expired']),
            default => $items,
        };

        return view('store.vouchers', [
            'vouchers' => $shown->values(),
            'filter' => $filter,
            'counts' => $counts,
            'claimedCount' => $items->where('state', 'claimed')->count(),
            'availableCount' => $counts['tersedia'],
        ]);
    }

    public function claim(Request $request, Voucher $voucher): RedirectResponse
    {
        // Hanya voucher yang tampil di toko, perlu diklaim, dan sedang berjalan yang bisa diklaim.
        if (! $voucher->is_listed || ! $voucher->requires_claim || $voucher->status() !== 'running') {
            return redirect()->route('vouchers.index')->with('error', 'Voucher ini tidak bisa diklaim.');
        }

        $claim = UserVoucher::firstOrCreate(
            ['user_id' => $request->user()->id, 'voucher_id' => $voucher->id],
            ['claimed_at' => now()],
        );

        return redirect()->route('vouchers.index')->with(
            $claim->wasRecentlyCreated ? 'success' : 'info',
            $claim->wasRecentlyCreated ? "Voucher {$voucher->code} berhasil diklaim." : "Voucher {$voucher->code} sudah kamu klaim.",
        );
    }
}
