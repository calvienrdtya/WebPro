<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\ClaimReason;
use App\Enums\ReportCategory;
use App\Enums\ReportResolution;
use App\Exceptions\OrderException;
use App\Models\Account;
use App\Models\CustomerReport;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Klaim garansi: catatan klaim dari Laporan, penggantian akun, dan pengembalian akun rusak dari Bengkel. */
class ClaimService
{
    public function __construct(private OrderService $orders) {}

    /** Pesanan masih punya akun terkirim yang garansinya belum habis. */
    public static function warrantyOpen(Order $order): bool
    {
        return $order->isPaid() && $order->deliveries()
            ->whereNotNull('delivered_at')->whereDate('warranty_ends_on', '>=', today())->exists();
    }

    /** Pesanan pelanggan yang bisa diklaim dari sebuah laporan. */
    public function claimableOrders(User $user): Collection
    {
        return Order::where('user_id', $user->id)->whereNotNull('warranty_days')
            ->whereIn('status', ['paid', 'completed'])->latest('id')->get()
            ->filter(fn (Order $o) => self::warrantyOpen($o))->values();
    }

    /** Memindahkan laporan "Akun bermasalah" ke Klaim Garansi (menunggu penggantian). */
    public function openFromReport(CustomerReport $report, Order $order, User $admin): WarrantyClaim
    {
        if (! $report->isOpen() || $report->category !== ReportCategory::Account) {
            throw new OrderException('Hanya laporan "Akun bermasalah" yang masih menunggu yang bisa dipindahkan ke Klaim Garansi.');
        }

        if ($order->user_id !== $report->user_id) {
            throw new OrderException('Pesanan ini bukan milik pelapor.');
        }

        if (! self::warrantyOpen($order)) {
            throw new OrderException('Garansi pesanan ini sudah habis atau pesanan tidak bergaransi.');
        }

        return DB::transaction(function () use ($report, $order, $admin) {
            if (WarrantyClaim::where('report_id', $report->id)->where('status', WarrantyClaim::OPEN)->lockForUpdate()->exists()) {
                throw new OrderException('Laporan ini sudah dipindahkan ke Klaim Garansi.');
            }

            return WarrantyClaim::create([
                'order_id' => $order->id,
                'report_id' => $report->id,
                'source' => 'report',
                'status' => WarrantyClaim::OPEN,
                'note' => $report->subject,
                'handled_by' => $admin->id,
            ]);
        });
    }

    public function cancel(WarrantyClaim $claim): void
    {
        if (! $claim->isOpen()) {
            throw new OrderException('Hanya klaim yang menunggu penggantian yang bisa dibatalkan.');
        }

        $claim->forceFill(['status' => WarrantyClaim::CANCELLED])->save();
    }

    /**
     * Akun pengganti dari stok, diurutkan HPP terendah (HPP 0 lebih dulu) lalu yang paling lama masuk.
     *
     * @return Collection<int, Account>
     */
    public function stockCandidates(Order $order): Collection
    {
        return Account::available()
            ->where('product_id', $order->product_id)
            ->whereDate('expires_at', '>=', today())
            ->orderByRaw('coalesce(cogs, 0) asc')
            ->orderBy('id')
            ->limit(50)
            ->get();
    }

    /**
     * Akun replacement: akun terjual dari pesanan lain yang semua pemakainya sudah tidak punya hak klaim
     * (garansi habis atau tanpa garansi) dan masih aktif. Dipakai bersama pelanggan yang mengklaim.
     *
     * @return Collection<int, Account>
     */
    public function replacementCandidates(Order $order, ?int $excludeAccountId = null): Collection
    {
        return Account::query()
            ->where('status', AccountStatus::Sold->value)
            ->where('product_id', $order->product_id)
            ->whereDate('expires_at', '>=', today())
            ->when($excludeAccountId, fn ($q) => $q->whereKeyNot($excludeAccountId))
            // Dijual lewat pesanan (bukan input manual) dan tidak ada pemakai yang garansinya masih aktif.
            ->whereHas('deliveries', fn ($d) => $d->whereNotNull('delivered_at'))
            ->whereDoesntHave('deliveries', fn ($d) => $d->whereDate('warranty_ends_on', '>=', today()))
            ->whereDoesntHave('deliveries', fn ($d) => $d->where('order_id', $order->id))
            ->withCount(['deliveries as users_count' => fn ($d) => $d->whereNotNull('delivered_at')])
            ->with(['deliveries' => fn ($d) => $d->whereNotNull('delivered_at')->with('order:id,code')->latest('delivered_at')])
            ->orderBy('users_count')
            ->orderBy('expires_at', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Mengganti akun satu kiriman dengan akun dari stok. Akun lama otomatis Bermasalah (masuk Bengkel),
     * akun baru Terjual (mode stock) atau dipakai bersama (mode replacement), dan pelanggan diberi email. Biaya klaim belum dicatat.
     * Boleh dilakukan saat garansi sudah habis, tetapi harus dikonfirmasi admin ($confirmOutside).
     */
    public function replace(Order $order, OrderDelivery $delivery, int $accountId, ClaimReason $reason, ?string $note, User $admin, bool $confirmOutside, ?int $claimId = null, string $mode = 'stock'): WarrantyClaim
    {
        [$claim, $delivery] = DB::transaction(function () use ($order, $delivery, $accountId, $reason, $note, $admin, $confirmOutside, $claimId, $mode) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->isPaid()) {
                throw new OrderException('Pesanan ini belum lunas.');
            }

            $delivery = $order->deliveries()->whereKey($delivery->id)->lockForUpdate()->first();

            if (! $delivery || ! $delivery->isDelivered() || ! $delivery->account_id) {
                throw new OrderException('Akun untuk kiriman ini belum terkirim.');
            }

            $open = $claimId
                ? (WarrantyClaim::whereKey($claimId)->where('order_id', $order->id)->where('status', WarrantyClaim::OPEN)->lockForUpdate()->first()
                    ?? throw new OrderException('Klaim ini sudah tidak menunggu penggantian.'))
                : null;

            $outside = ! ($delivery->warranty_ends_on && $delivery->warranty_ends_on->gte(today()));

            if ($outside && ! $confirmOutside) {
                throw new OrderException('Garansi akun ini sudah habis atau pesanan tanpa garansi. Centang konfirmasi bila tetap ingin menggantinya.');
            }

            $old = Account::lockForUpdate()->findOrFail($delivery->account_id);
            $new = Account::lockForUpdate()->find($accountId);

            $shared = $mode === 'replacement';
            $usable = $new && $new->product_id === $order->product_id && ! $new->expires_at?->lt(today())
                && ($shared
                    ? $this->replacementCandidates($order, $old->id)->contains('id', $new->id)
                    : $new->status === AccountStatus::Available);

            if (! $usable) {
                throw new OrderException('Akun pengganti sudah tidak tersedia. Pilih akun lain.');
            }

            $log = 'Diganti ('.$order->code.', '.now()->format('d/m/Y').'): '.$reason->label().($note ? " - {$note}" : '');
            $old->forceFill(['status' => AccountStatus::Problem, 'note' => trim(($old->note ? $old->note."\n" : '').$log)])->save();

            if ($shared) {
                // Dipakai bersama: akun tetap Terjual atas nama pemakai awalnya; hanya dicatat siapa saja yang ikut memakai.
                $new->forceFill(['note' => trim(($new->note ? $new->note."\n" : '').'Dipakai bersama '.$order->code.' ('.now()->format('d/m/Y').')')])->save();
            } else {
                $new->forceFill(['status' => AccountStatus::Sold, 'sold_to' => $order->customer_name.' ('.$order->code.')'])->save();
            }

            $delivery->update(['account_id' => $new->id]);

            $attributes = [
                'order_id' => $order->id,
                'delivery_id' => $delivery->id,
                'status' => WarrantyClaim::DONE,
                'mode' => $mode,
                'reason' => $reason->value,
                'note' => $note ?: ($open?->note),
                'old_account_id' => $old->id,
                'new_account_id' => $new->id,
                'outside_warranty' => $outside,
                'handled_by' => $admin->id,
                'replaced_at' => now(),
            ];

            if ($open) {
                $open->forceFill($attributes)->save();
                $claim = $open;
            } else {
                $claim = WarrantyClaim::create($attributes + ['source' => 'order']);
            }

            if ($claim->report_id && ($report = CustomerReport::lockForUpdate()->find($claim->report_id)) && $report->isOpen()) {
                $report->resolve(ReportResolution::AccountReplaced, $note, null, $admin);
            }

            return [$claim, $delivery];
        });

        $this->orders->sendDelivery($order, $delivery->fresh('account'), replacement: true);

        return $claim;
    }

    /** Akun rusak yang sudah selesai diperbaiki kembali ke stok. */
    public function restock(WarrantyClaim $claim): void
    {
        $account = $claim->oldAccount;

        if ($claim->status !== WarrantyClaim::DONE || ! $account || $account->status !== AccountStatus::Problem) {
            throw new OrderException('Akun ini sudah tidak berstatus bermasalah.');
        }

        if ($account->expires_at?->lt(today())) {
            throw new OrderException('Masa aktif akun ini sudah habis, tidak bisa dikembalikan ke stok.');
        }

        $account->forceFill(['status' => AccountStatus::Available])->save();
    }
}
