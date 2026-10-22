<?php

namespace Database\Seeders;

use App\Enums\FaqCategory as C;
use App\Models\Faq;
use Illuminate\Database\Seeder;

/** FAQ awal. Memakai firstOrCreate per pertanyaan: FAQ yang sudah ada (mis. sudah diedit admin) tidak ditimpa. */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        // [kategori, pertanyaan, jawaban, beranda, halaman produk]
        $items = [
            [C::General, 'Apa itu ProAkses?', 'ProAkses adalah toko digital yang menjual akun premium (Netflix, Spotify, ChatGPT, dan lainnya) dengan harga rupiah, dikirim setelah pembayaran terverifikasi, dengan pilihan garansi penggantian.', false, false],
            [C::General, 'Bagaimana cara order di ProAkses?', 'Pilih produk, durasi, dan garansi, isi data pemesan, bayar lewat QRIS, lalu klik "Saya Sudah Bayar". Setelah pembayaran terverifikasi, akun dikirim ke email kamu.', false, false],
            [C::General, 'Apakah akun yang dijual aman digunakan?', 'Semua akun diperiksa sebelum dijual. Kami memberikan garansi penggantian jika ada masalah selama masa garansi yang kamu pilih berlaku.', true, false],
            [C::General, 'Bagaimana jika stok habis?', 'Pesanan tidak bisa dilanjutkan saat stok habis. Tanyakan ke admin lewat WhatsApp kapan stok tersedia lagi, atau pilih produk lain.', false, true],
            [C::Payment, 'Metode pembayaran apa saja yang tersedia?', 'Saat ini kami menerima pembayaran lewat QRIS, yang bisa di-scan dari aplikasi e-wallet atau m-banking apa pun (GoPay, OVO, Dana, ShopeePay, dan lainnya).', true, true],
            [C::Payment, 'Berapa lama akun dikirim setelah pembayaran?', 'Setelah kamu klik "Saya Sudah Bayar", tim kami memverifikasi mutasi QRIS dan mengonfirmasi pesanan secepatnya. Akun otomatis terkirim ke email kamu begitu pembayaran dikonfirmasi.', true, false],
            [C::Payment, 'Apakah pembayaran saya aman?', 'Pembayaran dilakukan lewat QRIS dan diverifikasi langsung oleh admin. Data kamu tidak dibagikan ke pihak lain.', false, false],
            [C::Account, 'Bagaimana cara login akun yang saya beli?', 'Detail login (email atau username dan password) dikirim ke email kamu dan bisa dilihat di profil. Ikuti panduan aktivasi yang disertakan. Kalau bingung, hubungi CS kami.', false, false],
            [C::Account, 'Apakah bisa ganti password akun?', 'Tergantung jenis akun. Untuk akun sharing, password tidak bisa diganti. Untuk akun pribadi, kamu bebas mengganti password.', false, false],
            [C::Account, 'Apakah akun bisa dipakai di banyak device?', 'Tergantung produk. Akun sharing biasanya terbatas 1-2 device. Akun pribadi bisa dipakai di banyak device sesuai kebijakan produk.', false, false],
            [C::Warranty, 'Apa yang dimaksud garansi penggantian akun?', 'Jika akun yang kamu terima bermasalah (tidak bisa login, diblokir, dan sejenisnya), kamu bisa melapor dan kami akan mengganti akun tersebut selama masa garansi yang kamu pilih saat checkout.', true, false],
            [C::Warranty, 'Apakah harga sudah termasuk garansi?', 'Garansi adalah pilihan terpisah dan wajib dipilih saat memesan. Pilih paket garansi di panel pembelian sesuai kebutuhan Anda.', false, true],
            [C::Warranty, 'Berapa lama garansi berlaku?', 'Sesuai paket garansi yang kamu pilih. Untuk pembelian beberapa bulan, garansi berlaku di setiap akun bulanan yang kamu terima.', false, false],
            [C::Warranty, 'Bagaimana cara klaim garansi?', 'Kirim laporan dari halaman profil atau hubungi CS lewat WhatsApp dengan menyertakan nomor order dan screenshot masalahnya. Tim kami akan memproses penggantian secepatnya.', false, false],
        ];

        foreach ($items as [$category, $question, $answer, $home, $product]) {
            Faq::firstOrCreate(['question' => $question], [
                'category' => $category,
                'answer' => $answer,
                'is_active' => true,
                'show_on_home' => $home,
                'show_on_product' => $product,
            ]);
        }
    }
}
