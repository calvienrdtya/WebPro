<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Status Direservasi dihapus: akun baru dikaitkan ke pesanan saat pembayaran dikonfirmasi. Sisa reservasi lama dilepas ke stok. */
    public function up(): void
    {
        DB::table('order_deliveries')->whereNull('delivered_at')->whereNotNull('account_id')
            ->whereIn('account_id', DB::table('accounts')->where('status', 'reserved')->select('id'))
            ->update(['account_id' => null]);

        DB::table('accounts')->where('status', 'reserved')->update(['status' => 'available', 'updated_at' => now()]);
    }

    public function down(): void
    {
        //
    }
};
