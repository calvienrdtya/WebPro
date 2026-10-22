<?php

namespace App\Console\Commands;

use App\Enums\ClaimReason;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Impor data sistem lama (koneksi `legacy`) ke skema sekarang, satu tabel per perintah.
 * Aman diulang: baris lama yang sudah tercatat di `legacy_id_maps` dilewati.
 * Waktu disalin apa adanya (sistem lama menyimpan jam WIB).
 */
class ImportLegacy extends Command
{
    protected $signature = 'legacy:import {table : Tabel lama yang diimpor (users, accounts, orders, vouchers, order_account_histories)} {--dry-run : Hanya tampilkan ringkasan, tanpa menyimpan}';

    protected $description = 'Impor data sistem lama ke database sekarang';

    /** Kolom `type` akun lama -> slug produk sekarang. */
    private const ACCOUNT_PRODUCTS = [
        'netflix' => 'netflix-premium',
        'claude' => 'claude-pro',
        'chatgpt' => 'chatgpt-plus',
        'canva' => 'canva-pro',
        'capcut' => 'capcut-pro',
        'spotify' => 'spotify-premium',
        'youtube' => 'youtube-premium',
    ];

    /** products.id sistem lama (tidak ada di dump) -> slug produk sekarang. */
    private const ORDER_PRODUCTS = [
        13 => 'netflix-premium',
        19 => 'capcut-pro',
        20 => 'claude-pro',
        21 => 'chatgpt-plus',
    ];

    /** warranty_types sistem lama (tidak ada di dump, dari tabel produksi): nama dan hari disalin ke pesanan; `local` = garansi sekarang yang setara. */
    private const LEGACY_WARRANTIES = [
        4 => ['name' => 'Garansi 15 Hari (Minimal)', 'days' => 15, 'local' => null],
        5 => ['name' => 'Garansi Full (Maksimal)', 'days' => 28, 'local' => 'Garansi Penuh'],
    ];

    public function handle(): int
    {
        $table = $this->argument('table');

        $method = 'import'.Str::studly($table);
        if (! method_exists($this, $method)) {
            $this->error("Tabel {$table} belum didukung.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stats = ['diimpor' => 0, 'dilewati (sudah ada)' => 0, 'tidak diimpor' => 0];

        DB::beginTransaction();
        try {
            $ok = $this->{$method}($stats);
            ($ok && ! $dryRun) ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Hasil', 'Jumlah'], collect($stats)->map(fn ($n, $k) => [$k, $n])->values());
        if ($ok && $dryRun) {
            $this->warn('Mode --dry-run: tidak ada yang disimpan.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** User lama. Admin lama tidak diimpor (admin memakai akun lokal). Email yang sudah dipakai user lokal menghentikan impor. */
    private function importUsers(array &$stats): bool
    {
        $rows = DB::connection('legacy')->table('users')->orderBy('id')->get();
        $mapped = $this->mappedIds('users');

        $pending = $rows->reject(fn ($r) => $mapped->has($r->id));
        $stats['dilewati (sudah ada)'] = $rows->count() - $pending->count();

        $conflicts = DB::table('users')
            ->whereIn('email', $pending->where('is_admin', 0)->map(fn ($r) => $this->email($r->email))->all())
            ->pluck('email');
        if ($conflicts->isNotEmpty()) {
            $this->error('Email berikut sudah dipakai user lokal, selesaikan dulu: '.$conflicts->map(fn ($e) => Str::mask($e, '*', 2, -10))->join(', '));

            return false;
        }

        foreach ($pending as $r) {
            if ($r->is_admin) {
                $this->map('users', $r->id, null, 'Admin sistem lama, tidak diimpor');
                $stats['tidak diimpor']++;

                continue;
            }

            $id = DB::table('users')->insertGetId([
                'name' => trim($r->name),
                'email' => $this->email($r->email),
                'email_verified_at' => $r->email_verified_at,
                'password' => $r->password,
                'phone' => ($phone = trim((string) $r->phone)) !== '' ? $phone : null,
                'is_admin' => false,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]);
            $this->map('users', $r->id, $id);
            $stats['diimpor']++;
        }

        return true;
    }

    /**
     * Akun stok lama. `type` dipetakan ke produk lewat slug; masa aktif dibuat persis sama (mulai = tanggal ditambahkan,
     * lama = selisih hari sampai expired_at). Reserved di sistem lama diisi manual (disisihkan), jadi diperlakukan
     * seperti Tersedia; Tersedia yang sudah lewat masa aktif langsung Kedaluwarsa. replaced/problematic = Bermasalah.
     * Pembeli, waktu jual, dan HPP akun terjual diisi saat impor orders.
     */
    private function importAccounts(array &$stats): bool
    {
        $products = DB::table('products')->whereIn('slug', self::ACCOUNT_PRODUCTS)->pluck('id', 'slug');
        $rows = DB::connection('legacy')->table('accounts')->orderBy('id')->get();

        $unknown = $rows->pluck('type')->unique()->reject(fn ($t) => isset($products[self::ACCOUNT_PRODUCTS[$t] ?? '']));
        if ($unknown->isNotEmpty()) {
            $this->error('Jenis akun tanpa produk lokal: '.$unknown->join(', '));

            return false;
        }

        $mapped = $this->mappedIds('accounts');
        $sold = DB::connection('legacy')->table('order_items')->whereNotNull('accountable_id')->pluck('accountable_id')->flip();
        $today = today()->toDateString();

        foreach ($rows as $r) {
            if ($mapped->has($r->id)) {
                $stats['dilewati (sudah ada)']++;

                continue;
            }

            $startedAt = substr($r->created_at, 0, 10);
            $status = match ($r->status) {
                'available', 'reserved' => $r->expired_at < $today ? 'expired' : 'available',
                'sold' => 'sold',
                default => 'problem',
            };
            $note = trim(trim((string) $r->notes)." [Lama #{$r->id}]");

            $id = DB::table('accounts')->insertGetId([
                'product_id' => $products[self::ACCOUNT_PRODUCTS[$r->type]],
                'email' => trim($r->email),
                'password' => Crypt::encryptString($r->password),
                'profile' => ($v = trim((string) $r->profile_name)) !== '' ? $v : null,
                'pin' => ($v = trim((string) $r->profile_pin)) !== '' ? $v : null,
                'cogs' => null,
                'status' => $status,
                'started_at' => $startedAt,
                'active_days' => (int) Carbon::parse($startedAt)->diffInDays(Carbon::parse($r->expired_at)),
                'expires_at' => $r->expired_at,
                // Terjual tanpa pesanan web = dijual manual di luar web.
                'sold_to' => $status === 'sold' && ! $sold->has($r->id) ? 'Penjualan luar web (sistem lama)' : null,
                'sold_at' => $status === 'sold' ? $r->updated_at : null,
                'note' => $note,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]);
            $this->map('accounts', $r->id, $id);
            $stats['diimpor']++;
        }

        return true;
    }

    /**
     * Pesanan lama (selalu 1 item = 1 bulan = 1 akun) menjadi pesanan + satu kiriman. Nomor pesanan lama dipertahankan.
     * Garansi disalin dari tabel garansi lama (tidak ada di dump, lihat LEGACY_WARRANTIES); akhir garansi = waktu bayar + hari.
     * Akun yang dipakai dilengkapi pembeli, waktu jual, dan HPP. Pesanan user yang tidak diimpor (admin lama) dilewati
     * dan akunnya dihapus karena hanya uji. Butuh impor users dan accounts lebih dulu.
     */
    private function importOrders(array &$stats): bool
    {
        $legacy = DB::connection('legacy');
        $users = $this->mappedIds('users');
        $accounts = $this->mappedIds('accounts');
        if ($users->isEmpty() || $accounts->isEmpty()) {
            $this->error('Impor users dan accounts dulu.');

            return false;
        }

        $products = DB::table('products')->whereIn('slug', self::ORDER_PRODUCTS)->get(['id', 'slug', 'name'])->keyBy('slug');
        $warranties = DB::table('warranties')->pluck('id', 'name');
        $items = $legacy->table('order_items')->get()->keyBy('order_id');
        $payments = $legacy->table('payments')->orderBy('id')->get()->keyBy('order_id'); // terakhir per pesanan
        $mapped = $this->mappedIds('orders');

        foreach ($legacy->table('orders')->orderBy('id')->get() as $r) {
            if ($mapped->has($r->id)) {
                $stats['dilewati (sudah ada)']++;

                continue;
            }

            $item = $items[$r->id] ?? null;
            $product = $products[self::ORDER_PRODUCTS[$item?->product_id] ?? ''] ?? null;
            if (! $item || ! $product || ! $users->has($r->user_id) || ($item->accountable_id && ! $accounts->has($item->accountable_id))) {
                $this->error("Pesanan lama #{$r->id} tidak lengkap (item, produk, user, atau akun belum terpetakan).");

                return false;
            }

            if ($users[$r->user_id] === null) {
                // Pesanan uji admin lama: tidak diimpor, akun yang terpakai ikut dihapus.
                if ($item->accountable_id && ($accountId = $accounts[$item->accountable_id])) {
                    DB::table('accounts')->where('id', $accountId)->delete();
                    DB::table('legacy_id_maps')->where(['source_table' => 'accounts', 'legacy_id' => $item->accountable_id])
                        ->update(['new_id' => null, 'note' => 'Akun pesanan uji admin lama, dihapus', 'updated_at' => now()]);
                }
                $this->map('orders', $r->id, null, 'Pesanan uji admin lama, tidak diimpor');
                $stats['tidak diimpor']++;

                continue;
            }

            if (DB::table('orders')->where('code', $r->order_number)->exists()) {
                $this->error("Kode {$r->order_number} sudah dipakai pesanan lokal.");

                return false;
            }

            $warranty = self::LEGACY_WARRANTIES[$r->warranty_type_id] ?? null;
            $payment = $payments[$r->id] ?? null;
            $paidAt = $r->status === 'completed' ? ($payment?->paid_at ?? $r->updated_at) : null;
            $accountId = $item->accountable_id ? $accounts[$item->accountable_id] : null;
            $cogs = $item->cogs_snapshot !== null ? (int) $item->cogs_snapshot : null;

            $orderId = DB::table('orders')->insertGetId([
                'code' => $r->order_number,
                'user_id' => $users[$r->user_id],
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_title' => '1 Bulan',
                'months' => 1,
                'customer_name' => trim($r->buyer_name),
                'customer_email' => Str::lower(trim($r->buyer_email)),
                'customer_phone' => trim($r->buyer_phone),
                // Sistem lama tidak punya harga coret.
                'list_price' => (int) $r->product_price,
                'product_price' => (int) $r->product_price,
                'warranty_id' => $warranty ? ($warranties[$warranty['local']] ?? null) : null,
                'warranty_name' => $warranty['name'] ?? null,
                'warranty_days' => $warranty['days'] ?? null,
                'warranty_price' => (int) $r->warranty_price,
                'voucher_id' => null, // kode voucher lama tidak ada di sistem sekarang; kodenya tetap tercatat
                'voucher_code' => $r->voucher_code,
                'voucher_discount' => (int) $r->voucher_discount,
                'service_fee' => (int) $r->service_fee_amount + (int) $r->biaya_admin,
                'total' => (int) $r->total_amount,
                'status' => $r->status === 'completed' ? 'completed' : 'cancelled',
                'claimed_paid_at' => $payment?->claimed_paid_at,
                'paid_at' => $paidAt,
                'admin_note' => ($n = trim((string) $r->notes)) !== '' ? $n : null,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]);

            DB::table('order_deliveries')->insert([
                'order_id' => $orderId,
                'sequence' => 1,
                'account_id' => $paidAt ? $accountId : null,
                'cogs' => $paidAt ? $cogs : null,
                'delivered_at' => $paidAt,
                'warranty_ends_on' => $paidAt && $warranty ? Carbon::parse($paidAt)->addDays($warranty['days'])->toDateString() : null,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]);

            if ($paidAt && $accountId) {
                DB::table('accounts')->where('id', $accountId)->update([
                    'sold_to' => trim($r->buyer_name).' ('.$r->order_number.')',
                    'sold_at' => $paidAt,
                    'cogs' => $cogs,
                ]);
            }

            $this->map('orders', $r->id, $orderId);
            $stats['diimpor']++;
        }

        return true;
    }

    /**
     * Voucher yang dipakai pesanan lama (tabel vouchers lama tidak ada di dump). Kode yang belum ada di sistem sekarang
     * dibuat sebagai voucher potongan tetap sebesar potongan yang tercatat, dalam keadaan NONAKTIF dan tidak tampil
     * sampai admin mengatur ulang aturannya. Pesanan impor yang memakai kode itu ditautkan dan dihitung sebagai pemakaian.
     */
    private function importVouchers(array &$stats): bool
    {
        $orders = $this->mappedIds('orders')->filter();
        if ($orders->isEmpty()) {
            $this->error('Impor orders dulu.');

            return false;
        }

        $used = DB::connection('legacy')->table('orders')->whereNotNull('voucher_code')
            ->selectRaw('voucher_code, max(voucher_discount) as discount, min(created_at) as first_used')
            ->groupBy('voucher_code')->get();

        foreach ($used as $v) {
            $code = Str::upper(trim($v->voucher_code));
            $voucher = DB::table('vouchers')->where('code', $code)->first();

            if (! $voucher) {
                $id = DB::table('vouchers')->insertGetId([
                    'code' => $code,
                    'title' => "{$code} (sistem lama)",
                    'description' => 'Dibuat dari pesanan sistem lama. Atur ulang aturan sebelum diaktifkan.',
                    'discount_type' => 'fixed',
                    'discount_value' => (int) $v->discount,
                    'per_user_limit' => 1,
                    'requires_claim' => false,
                    'is_listed' => false,
                    'is_active' => false,
                    'applies_to_all' => true,
                    'created_at' => $v->first_used,
                    'updated_at' => now(),
                ]);
                $voucher = DB::table('vouchers')->find($id);
                $stats['diimpor']++;
            } else {
                $stats['dilewati (sudah ada)']++;
            }

            $pending = DB::table('orders')->whereIn('id', $orders->values())->where('voucher_code', $v->voucher_code)->whereNull('voucher_id');
            // Pesanan batal tidak menghabiskan kuota (sama seperti pesanan batal di sistem sekarang).
            $paid = (clone $pending)->whereIn('status', ['paid', 'completed'])->count();
            $linked = $pending->update(['voucher_id' => $voucher->id]);
            if ($paid > 0) {
                DB::table('vouchers')->where('id', $voucher->id)->increment('used_count', $paid);
            }
            $this->line("{$code}: {$linked} pesanan lama ditautkan, {$paid} dihitung terpakai.");
        }

        return true;
    }

    /**
     * Riwayat ganti akun lama menjadi klaim garansi selesai (dari pesanan, ambil dari stok, tanpa biaya). Akun lama
     * mendapat baris catatan yang sama seperti Ganti Akun sehingga tampil di Bengkel. Butuh impor orders lebih dulu.
     */
    private function importOrderAccountHistories(array &$stats): bool
    {
        $legacy = DB::connection('legacy');
        $accounts = $this->mappedIds('accounts');
        $orders = $this->mappedIds('orders');
        if ($orders->isEmpty()) {
            $this->error('Impor orders dulu.');

            return false;
        }

        $items = $legacy->table('order_items')->pluck('order_id', 'id');
        $mapped = $this->mappedIds('order_account_histories');

        foreach ($legacy->table('order_account_histories')->orderBy('id')->get() as $h) {
            if ($mapped->has($h->id)) {
                $stats['dilewati (sudah ada)']++;

                continue;
            }

            $orderId = $orders[$items[$h->order_item_id] ?? 0] ?? null;
            $oldId = $accounts[$h->old_accountable_id] ?? null;
            $newId = $accounts[$h->new_accountable_id] ?? null;
            if (! $orderId || ! $oldId || ! $newId) {
                $this->error("Riwayat lama #{$h->id} tidak lengkap (pesanan atau akun belum terpetakan).");

                return false;
            }

            $order = DB::table('orders')->find($orderId);
            $delivery = DB::table('order_deliveries')->where('order_id', $orderId)->where('sequence', 1)->first();
            $reason = ClaimReason::tryFrom(Str::snake(Str::lower(trim((string) $h->reason)))) ?? ClaimReason::Other;

            $claimId = DB::table('warranty_claims')->insertGetId([
                'order_id' => $orderId,
                'delivery_id' => $delivery?->id,
                'source' => 'order',
                'status' => 'done',
                'mode' => 'stock',
                'reason' => $reason->value,
                'note' => "Riwayat sistem lama #{$h->id}",
                'old_account_id' => $oldId,
                'new_account_id' => $newId,
                'outside_warranty' => ! $delivery?->warranty_ends_on || substr($h->replaced_at, 0, 10) > $delivery->warranty_ends_on,
                'claim_cost' => 0,
                'handled_by' => null, // admin lama tidak diimpor
                'replaced_at' => $h->replaced_at,
                'created_at' => $h->created_at ?? $h->replaced_at,
                'updated_at' => $h->created_at ?? $h->replaced_at,
            ]);

            $old = DB::table('accounts')->find($oldId);
            $log = 'Diganti ('.$order->code.', '.Carbon::parse($h->replaced_at)->format('d/m/Y').'): '.$reason->label();
            DB::table('accounts')->where('id', $oldId)->update(['note' => trim(($old->note ? $old->note."\n" : '').$log)]);

            $this->map('order_account_histories', $h->id, $claimId);
            $stats['diimpor']++;
        }

        return true;
    }

    private function email(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function mappedIds(string $table)
    {
        return DB::table('legacy_id_maps')->where('source_table', $table)->pluck('new_id', 'legacy_id');
    }

    private function map(string $table, int $legacyId, ?int $newId, ?string $note = null): void
    {
        DB::table('legacy_id_maps')->insert([
            'source_table' => $table,
            'legacy_id' => $legacyId,
            'new_id' => $newId,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
