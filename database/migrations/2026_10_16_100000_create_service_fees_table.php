<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Biaya layanan (biaya admin) yang ditambahkan ke setiap pesanan. Hanya satu yang boleh aktif dalam satu waktu.
        Schema::create('service_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedInteger('amount');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // Biaya lama di Pengaturan (fee.enabled + fee.amount) dipindahkan menjadi satu baris di menu baru.
        $amount = (int) DB::table('settings')->where('key', 'fee.amount')->value('value');

        if ($amount > 0) {
            DB::table('service_fees')->insert([
                'name' => 'Biaya Layanan',
                'amount' => $amount,
                'is_active' => DB::table('settings')->where('key', 'fee.enabled')->value('value') === '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('settings')->whereIn('key', ['fee.enabled', 'fee.amount'])->delete();
    }

    public function down(): void
    {
        $fee = DB::table('service_fees')->orderByDesc('is_active')->orderBy('id')->first();

        if ($fee) {
            DB::table('settings')->updateOrInsert(['key' => 'fee.amount'], ['value' => (string) $fee->amount]);
            DB::table('settings')->updateOrInsert(['key' => 'fee.enabled'], ['value' => $fee->is_active ? '1' : '0']);
        }

        Schema::dropIfExists('service_fees');
    }
};
