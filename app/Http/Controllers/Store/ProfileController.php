<?php

namespace App\Http\Controllers\Store;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\Order;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Services\VoucherPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const TABS = ['profil', 'pesanan', 'transaksi', 'voucher', 'laporan', 'pengaturan'];

    /** Pengelompokan status pesanan untuk filter pesanan dan transaksi. */
    private const GROUPS = [
        'selesai' => [OrderStatus::Paid, OrderStatus::Completed],
        'diproses' => [OrderStatus::Verifying],
        'pending' => [OrderStatus::Pending],
        'menunggu' => [OrderStatus::Pending, OrderStatus::Verifying],
        'gagal' => [OrderStatus::Expired, OrderStatus::Rejected, OrderStatus::Cancelled],
    ];

    public function show(Request $request): View
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'profil';

        $values = fn (array $group) => array_map(fn (OrderStatus $s) => $s->value, $group);
        $byStatus = Order::where('user_id', $user->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $countOf = fn (string $group) => (int) collect(self::GROUPS[$group])->sum(fn (OrderStatus $s) => $byStatus[$s->value] ?? 0);

        $filter = fn (string $param) => array_key_exists($request->query($param), self::GROUPS) ? $request->query($param) : 'all';
        $search = fn (string $param) => trim((string) $request->query($param));

        $list = function (string $filterParam, string $searchParam, string $pageName) use ($user, $filter, $search, $values, $request) {
            $f = $filter($filterParam);
            $q = $search($searchParam);

            return Order::where('user_id', $user->id)
                ->with(['product:id,slug,image', 'review'])
                ->when($f !== 'all', fn (Builder $query) => $query->whereIn('status', $values(self::GROUPS[$f])))
                ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w->where('product_name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
                ->latest('id')
                ->paginate(6, ['*'], $pageName)
                ->appends(['tab' => $request->query('tab')] + $request->query());
        };

        return view('store.profil', [
            'user' => $user,
            'tab' => $tab,
            'stats' => [
                'orders' => (int) $byStatus->sum(),
                'spent' => (int) Order::where('user_id', $user->id)->whereIn('status', $values(self::GROUPS['selesai']))->sum('total'),
                'success' => $countOf('selesai'),
                'waiting' => $countOf('menunggu'),
                'failed' => $countOf('gagal'),
            ],
            'orders' => $list('status', 'q', 'hal_pesanan'),
            'orderFilter' => $filter('status'),
            'orderSearch' => $search('q'),
            'transactions' => $list('tx', 'qtx', 'hal_transaksi'),
            'txFilter' => $filter('tx'),
            'txSearch' => $search('qtx'),
            'vouchers' => $this->vouchers($user->id, in_array($request->query('vc'), ['avail', 'used', 'expired'], true) ? $request->query('vc') : 'avail'),
            'voucherFilter' => in_array($request->query('vc'), ['avail', 'used', 'expired'], true) ? $request->query('vc') : 'avail',
            'reports' => $user->reports()->latest('id')->limit(30)->get(),
            'canDelete' => ! $user->is_admin,
            'hasPassword' => $user->password !== null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^[0-9+][0-9\s-]{7,}$/', 'max:20'],
        ], ['phone.regex' => 'Nomor WhatsApp belum benar.'], ['name' => 'Nama lengkap', 'phone' => 'Nomor WhatsApp']);

        $request->user()->update($data);

        return redirect()->route('profil', ['tab' => 'profil'])->with('success', 'Profil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            // Akun yang hanya masuk lewat Google belum punya sandi, jadi tidak ada sandi lama yang perlu dicek.
            'current_password' => [$user->password !== null ? 'required' : 'nullable', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], ['current_password.current_password' => 'Kata sandi saat ini salah.'], ['current_password' => 'Kata sandi saat ini', 'password' => 'Kata sandi baru']);

        $user->forceFill(['password' => $data['password']])->save();

        return redirect()->route('profil', ['tab' => 'pengaturan'])->with('success', 'Kata sandi diperbarui.');
    }

    /**
     * Hapus akun = anonimisasi: data pribadi dihapus, tetapi pesanan, ulasan, dan laporan tetap ada
     * agar riwayat transaksi toko utuh.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['confirm' => ['required', 'in:HAPUS']], ['confirm.in' => 'Ketik HAPUS (huruf besar) untuk mengonfirmasi.', 'confirm.required' => 'Ketik HAPUS untuk mengonfirmasi.']);

        if ($user->password !== null && ! Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Kata sandi salah.'])->withFragment('hapus');
        }

        if ($user->is_admin) {
            return back()->with('error', 'Akun admin tidak bisa dihapus dari sini.');
        }

        if ($user->orders()->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Verifying->value])->exists()) {
            return redirect()->route('profil', ['tab' => 'pengaturan'])->with('error', 'Masih ada pesanan yang belum selesai dibayar. Selesaikan atau batalkan dulu sebelum menghapus akun.');
        }

        DB::transaction(function () use ($user) {
            UserVoucher::where('user_id', $user->id)->delete();
            AiConversation::anonymizeUser($user->id);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->forceFill([
                'name' => 'Pengguna Terhapus',
                'email' => 'terhapus-'.$user->id.'-'.Str::lower(Str::random(6)).'@proakses.invalid',
                'phone' => null,
                'google_id' => null,
                'avatar' => null,
                'password' => Str::random(40),
                'remember_token' => null,
                'blocked_at' => now(),
            ])->save();
        });

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Akun kamu sudah dihapus.');
    }

    /**
     * Voucher milik user: yang diklaim ditambah yang pernah dipakai di pesanan.
     * state: claimed (siap dipakai), used (jatah pakai habis), expired (berakhir atau nonaktif).
     *
     * @return array<string, list<array>>
     */
    private function vouchers(int $userId, string $filter): array
    {
        $usedCounts = Order::where('user_id', $userId)->whereNotNull('voucher_id')
            ->whereNotIn('status', array_map(fn (OrderStatus $s) => $s->value, array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s->isClosedUnpaid())))
            ->selectRaw('voucher_id, count(*) as total')->groupBy('voucher_id')->pluck('total', 'voucher_id');
        $ids = UserVoucher::where('user_id', $userId)->pluck('voucher_id')->merge($usedCounts->keys())->unique()->all();

        $groups = ['avail' => [], 'used' => [], 'expired' => []];

        Voucher::with('products:id,name')->whereIn('id', $ids)->latest('id')->get()->each(function (Voucher $v) use ($usedCounts, &$groups) {
            $limit = $v->per_user_limit ?? PHP_INT_MAX;
            [$group, $state] = match (true) {
                ($usedCounts[$v->id] ?? 0) >= $limit => ['used', 'used'],
                $v->status() !== 'running' => ['expired', 'expired'],
                default => ['avail', 'claimed'],
            };
            $groups[$group][] = VoucherPresenter::present($v, $state);
        });

        return ['counts' => array_map('count', $groups), 'items' => $groups[$filter]];
    }
}
