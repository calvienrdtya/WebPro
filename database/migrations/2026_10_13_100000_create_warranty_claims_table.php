<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan klaim garansi: satu baris per klaim (boleh berkali-kali pada pesanan yang sama).
        // open = dipindahkan dari Laporan, menunggu penggantian; done = akun sudah diganti; cancelled = dibatalkan admin.
        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained('order_deliveries')->nullOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('customer_reports')->nullOnDelete();
            $table->string('source', 10);                 // report | order
            $table->string('status', 12)->default('open')->index();
            $table->string('reason', 30)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('old_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('new_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('outside_warranty')->default(false); // diganti walau garansi sudah habis (keputusan admin)
            $table->unsignedInteger('claim_cost')->default(0);   // HPP akun pengganti bila bukan HPP 0
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claims');
    }
};
