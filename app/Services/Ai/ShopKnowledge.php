<?php

namespace App\Services\Ai;

use App\Enums\OrderStatus;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceFee;
use App\Models\Voucher;
use App\Models\Warranty;
use App\Services\VoucherPresenter;
use Illuminate\Support\Collection;

/**
 * Data toko otomatis untuk pengetahuan AI, ditulis sebagai Markdown. Memakai logika yang sama dengan halaman toko
 * (harga dari saleOptions, stok dari withStoreCounts, garansi aktif yang berlaku, voucher yang tampil di /voucher),
 * jadi jawaban AI selalu sama dengan yang dilihat pelanggan. Data internal (HPP, akun, pelanggan) tidak pernah ikut.
 */
class ShopKnowledge
{
    /** @var Collection<int, Product>|null */
    private ?Collection $products = null;

    /** Teks satu bagian (kunci dari config ai.knowledge_sections), diawali judul bagian. */
    public function section(string $key): string
    {
        $body = match ($key) {
            'products' => $this->products(),
            'warranties' => $this->warranties(),
            'service_fee' => $this->serviceFee(),
            'vouchers' => $this->vouchers(),
            'faqs' => $this->faqs(),
            'sales' => $this->sales(),
        };

        return '## '.config("ai.knowledge_sections.{$key}")."\n".$body;
    }

    private function products(): string
    {
        if ($this->activeProducts()->isEmpty()) {
            return 'Belum ada produk yang dijual.';
        }

        $lines = ['Harga sudah termasuk diskon produk, belum termasuk garansi, biaya layanan, dan voucher.'];

        foreach ($this->activeProducts() as $product) {
            $lines[] = '';
            $lines[] = "### {$product->name} ({$product->category->label()})";
            $lines[] = '- Halaman produk: '.route('products.show', $product->slug);
            $lines[] = '- Stok: '.($product->stock_count > 0 ? "{$product->stock_count} akun tersedia" : 'habis');

            if ($description = $this->plain($product->description)) {
                $lines[] = '- Deskripsi: '.$description;
            }

            $lines[] = '- Pilihan durasi:';
            foreach ($product->saleOptions() as $option) {
                $detail = $option['months'] > 1 ? ' ('.$this->rupiah(intdiv($option['final_price'], $option['months'])).' per bulan)' : '';
                $discount = $option['discount'] > 0 ? ", diskon {$option['discount']}% dari ".$this->rupiah($option['list_price']) : '';
                $lines[] = "  - {$option['title']}: ".$this->rupiah($option['final_price']).$detail.$discount;
            }
        }

        return implode("\n", $lines);
    }

    /** Jumlah pesanan lunas (dibayar atau selesai) per produk aktif: angka nyata dari database. */
    private function sales(): string
    {
        if ($this->activeProducts()->isEmpty()) {
            return 'Belum ada produk yang dijual.';
        }

        $counts = Order::query()->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->whereIn('product_id', $this->activeProducts()->pluck('id'))
            ->selectRaw('product_id, count(*) as total')->groupBy('product_id')->pluck('total', 'product_id');

        return "Jumlah pesanan lunas sejak toko berjalan:\n".$this->activeProducts()
            ->map(fn (Product $p) => "- {$p->name}: ".(int) ($counts[$p->id] ?? 0).' pesanan')
            ->join("\n");
    }

    private function warranties(): string
    {
        $lines = [];

        foreach ($this->activeProducts() as $product) {
            $options = Warranty::active()->applicableTo($product)->orderBy('price')->orderBy('id')->get()
                ->map(fn (Warranty $w) => "{$w->name} ({$w->duration_days} hari) ".$this->rupiah($w->price));

            $lines[] = "- {$product->name}: ".($options->isEmpty() ? 'tidak ada pilihan garansi' : $options->join(', '));
        }

        return $lines ? "Garansi wajib dipilih saat membeli. Pilihan per produk:\n".implode("\n", $lines) : 'Belum ada produk yang dijual.';
    }

    private function serviceFee(): string
    {
        $fee = ServiceFee::current();
        $text = $fee > 0 ? 'Setiap pesanan dikenai biaya layanan '.$this->rupiah($fee).'.' : 'Tidak ada biaya layanan.';

        // Produk dengan biaya layanan khusus (berbeda dari biaya umum).
        $custom = Product::query()->where('is_active', true)->whereNotNull('service_fee')->where('service_fee', '!=', $fee)->orderBy('name')->get(['name', 'service_fee']);
        if ($custom->isNotEmpty()) {
            $text .= ' Kecuali: '.$custom->map(fn ($p) => $p->name.' '.($p->service_fee > 0 ? 'biaya layanan '.$this->rupiah($p->service_fee) : 'tanpa biaya layanan'))->join('; ').'.';
        }

        return $text;
    }

    private function vouchers(): string
    {
        $vouchers = Voucher::withStatus('running')->where('is_listed', true)->with('products:id,name')->latest('id')->get();

        if ($vouchers->isEmpty()) {
            return 'Tidak ada voucher yang sedang berlaku.';
        }

        $lines = ['Daftar lengkap dan tombol klaim ada di '.route('vouchers.index').'. Potongan voucher hanya untuk harga produk (tidak untuk garansi dan biaya layanan).'];

        foreach ($vouchers as $voucher) {
            $v = VoucherPresenter::present($voucher, 'direct');
            $parts = array_filter([
                "{$v['label']} {$v['value']}",
                $v['max_discount'],
                $v['min_purchase'],
                'berlaku untuk '.$v['scope'],
                $v['until'],
                $v['remaining'] !== null ? "sisa kuota {$v['remaining']}" : null,
                $voucher->requires_claim ? 'harus diklaim dulu di halaman voucher' : 'bisa langsung diketik saat checkout',
            ]);
            $lines[] = "- **{$v['code']}** ({$v['title']}): ".implode(', ', $parts).'.';
        }

        return implode("\n", $lines);
    }

    private function faqs(): string
    {
        $faqs = Faq::active()->ordered()->get();

        if ($faqs->isEmpty()) {
            return 'Belum ada FAQ.';
        }

        return $faqs->map(fn (Faq $f) => "### {$f->question}\n".trim($f->answer))->join("\n\n");
    }

    /** @return Collection<int, Product> */
    private function activeProducts(): Collection
    {
        return $this->products ??= Product::active()->withStoreCounts()->with('productVariants.variant')
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    private function plain(?string $html): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html))));

        return mb_strimwidth($text, 0, 400, '...');
    }

    private function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
