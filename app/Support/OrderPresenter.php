<?php

namespace App\Support;

use App\Enums\ClaimReason;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\WarrantyClaim;

/** Data pesanan untuk modal detail di admin (menu Pesanan dan detail Laporan memakai bentuk yang sama). */
class OrderPresenter
{
    /** Relasi yang harus dimuat sebelum memanggil row(). */
    public const RELATIONS = ['deliveries.account', 'verifier:id,name', 'claims.oldAccount', 'claims.newAccount'];

    /** @return array<string, mixed> */
    public static function row(Order $o): array
    {
        return [
            'id' => $o->id,
            'code' => $o->code,
            'user_id' => $o->user_id,
            'customer_name' => $o->customer_name,
            'customer_email' => $o->customer_email,
            'customer_phone' => $o->customer_phone,
            'product_name' => $o->product_name,
            'variant_title' => $o->variant_title,
            'months' => $o->months,
            'warranty_name' => $o->warranty_name,
            'warranty_price' => $o->warranty_price,
            'voucher_code' => $o->voucher_code,
            'voucher_discount' => $o->voucher_discount,
            'list_price' => $o->list_price,
            'product_price' => $o->product_price,
            'service_fee' => $o->service_fee,
            'total' => $o->total,
            'status' => $o->status->value,
            'status_label' => $o->status->label(),
            'reject_reason' => $o->reject_reason,
            'admin_note' => $o->admin_note,
            'warranty_days' => $o->warranty_days,
            // Masa layanan pesanan: dari hari dibayar sepanjang (jumlah bulan x selang kiriman).
            'service_ends_on' => $o->paid_at?->copy()->addDays((int) config('proakses.delivery_interval_days') * $o->months)->toDateString(),
            'claims' => $o->claims->map(fn (WarrantyClaim $c) => [
                'id' => $c->id,
                'status' => $c->status,
                'reason' => $c->reason ? (ClaimReason::tryFrom($c->reason)?->label() ?? $c->reason) : null,
                'outside_warranty' => $c->outside_warranty,
                'mode' => $c->mode,
                'old_email' => $c->oldAccount?->email,
                'new_email' => $c->newAccount?->email,
                'created_at' => $c->created_at->toIso8601String(),
                'replaced_at' => $c->replaced_at?->toIso8601String(),
            ])->values()->all(),
            'has_proof' => $o->proof_path !== null,
            'verified_by' => $o->verifier?->name,
            'created_at' => $o->created_at->toIso8601String(),
            'expires_at' => $o->expires_at?->toIso8601String(),
            'claimed_paid_at' => $o->claimed_paid_at?->toIso8601String(),
            'paid_at' => $o->paid_at?->toIso8601String(),
            'deliveries' => $o->deliveries->map(fn (OrderDelivery $d) => [
                'id' => $d->id,
                'sequence' => $d->sequence,
                'due_on' => $d->due_on?->toDateString(),
                'delivered_at' => $d->delivered_at?->toIso8601String(),
                'warranty_ends_on' => $d->warranty_ends_on?->toDateString(),
                'cogs' => $d->cogs,
                'account' => $d->account ? [
                    'email' => $d->account->email, 'password' => $d->account->password, 'profile' => $d->account->profile, 'pin' => $d->account->pin,
                    'started_at' => $d->account->started_at?->toDateString(), 'expires_at' => $d->account->expires_at?->toDateString(),
                ] : null,
            ])->values()->all(),
        ];
    }
}
