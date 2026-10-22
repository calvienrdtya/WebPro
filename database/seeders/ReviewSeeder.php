<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Seeder;

/**
 * Ulasan CONTOH untuk pengembangan lokal (DatabaseSeeder tidak memanggil seeder ini di production).
 * Bukan ulasan pembeli sungguhan: hapus lewat menu Ulasan di admin sebelum toko dibuka.
 * Dilewati bila sudah ada ulasan.
 */
class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (Review::exists()) {
            return;
        }

        $samples = [
            'netflix-premium' => [
                ['Rizky Anwar', 5, 'Akun langsung dikirim kurang dari 10 menit, aktif normal sampai sekarang. Recommended!', 'approved'],
                ['Salsa Maharani', 5, 'Prosesnya gampang dan admin fast respon. Harganya jauh lebih murah dari langganan resmi.', 'approved'],
                ['Dimas Pratama', 4, 'Sempat ada kendala login, tapi langsung diganti lewat garansi. Mantap.', 'approved'],
                ['Nabila Rahma', 5, 'Sudah beberapa kali order di sini dan selalu lancar. Pasti repeat order lagi.', 'approved'],
                ['Fajar Hidayat', 4, 'Harga bersahabat, pengiriman cepat. Semoga stok selalu tersedia.', 'approved'],
                ['Calon Pembeli', 3, 'Ulasan contoh yang masih menunggu persetujuan admin.', 'pending'],
                ['Pembeli Tersembunyi', 1, 'Ulasan contoh yang disembunyikan admin.', 'hidden'],
            ],
            'chatgpt-plus' => [
                ['Alya Putri', 5, 'Akses berjalan lancar dan penjelasan aktivasinya jelas.', 'approved'],
                ['Bagas Setiawan', 4, 'Cukup puas, proses verifikasi pembayaran cepat.', 'approved'],
            ],
        ];

        foreach ($samples as $slug => $items) {
            $product = Product::where('slug', $slug)->first();

            if (! $product) {
                continue;
            }

            foreach ($items as $i => [$name, $rating, $body, $status]) {
                $review = new Review(['product_id' => $product->id, 'reviewer_name' => $name, 'rating' => $rating, 'body' => $body]);
                $review->status = $status;
                $review->created_at = now()->subDays(2 + $i * 4);
                $review->updated_at = $review->created_at;
                $review->save();
            }
        }
    }
}
