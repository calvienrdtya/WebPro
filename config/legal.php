<?php

/*
 * DRAF BAWAAN halaman legal. Isi yang disimpan admin (menu Halaman Legal) selalu menang. Ini bukan nasihat hukum:
 * pemilik toko wajib meninjau dan menyesuaikannya. Penanda {nama_toko}, {email}, dan {situs} diganti saat ditampilkan.
 */
return [
    'pages' => [
        'terms' => [
            'title' => 'Syarat & Ketentuan',
            'slug' => 'syarat-ketentuan',
            'description' => 'Syarat dan ketentuan berbelanja akun premium di {nama_toko}.',
            'body' => <<<'MD'
Dengan membuat akun atau melakukan pembelian di {situs}, kamu dianggap telah membaca, memahami, dan menyetujui syarat dan ketentuan berikut.

## 1. Tentang layanan
- {nama_toko} menjual akses akun layanan digital premium (misalnya layanan streaming, AI, dan aplikasi kreatif) dalam bentuk akun sharing atau akun pribadi sesuai keterangan di halaman produk.
- {nama_toko} bukan pemilik maupun mitra resmi layanan tersebut. Merek dan nama layanan adalah milik pemiliknya masing-masing.

## 2. Akun pelanggan
- Kamu wajib mengisi data yang benar dan aktif, terutama alamat email, karena detail akun dikirim ke email yang diisi di form order.
- Kamu bertanggung jawab menjaga kerahasiaan kata sandi akun {nama_toko} milikmu.
- Kami berhak memblokir akun yang terindikasi penipuan, penyalahgunaan, atau melanggar ketentuan ini.

## 3. Pemesanan dan pembayaran
- Harga yang berlaku adalah harga yang tertera saat pesanan dibuat, termasuk paket garansi, biaya layanan, dan potongan voucher bila ada.
- Pembayaran hanya melalui QRIS. Bayar sesuai total pesanan, lalu tekan tombol "Saya Sudah Bayar".
- Pembayaran diverifikasi manual oleh admin. Pesanan yang tidak dibayar sampai batas waktu di halaman pesanan otomatis kedaluwarsa.

## 4. Pengiriman akun
- Akun dikirim setelah pembayaran terverifikasi, ke email yang diisi di form order, dan juga tampil di halaman Pesanan Saya.
- Paket lebih dari 1 bulan berisi 1 akun untuk setiap bulan. Akun bulan berikutnya dikirim otomatis sekitar 28 hari setelah akun sebelumnya.

## 5. Aturan pemakaian akun
- Gunakan akun sesuai keterangan produk (misalnya hanya profil yang ditentukan).
- Dilarang mengubah kata sandi, email, profil milik pengguna lain, atau pengaturan pembayaran akun.
- Dilarang membagikan atau menjual kembali akun kepada pihak lain.
- Pelanggaran aturan ini dapat membatalkan garansi tanpa penggantian.

## 6. Garansi
- Paket garansi wajib dipilih saat membeli. Masa garansi dihitung per akun, sejak akun itu dikirim.
- Bila akun bermasalah dalam masa garansi, laporkan lewat Profil > Laporan dengan memilih pesanan terkait. Admin akan memeriksa dan mengganti akun bila masalahnya tercakup garansi.
- Garansi tidak berlaku untuk masalah akibat pelanggaran aturan pemakaian.

## 7. Pembatalan dan pengembalian dana
- Pesanan yang belum dibayar bisa dibatalkan dari halaman pesanan.
- Pesanan yang sudah dibayar dan akunnya sudah dikirim tidak dapat dibatalkan. Masalah pada akun diselesaikan melalui garansi.
- Bila pembayaran sudah diterima tetapi akun tidak dapat kami kirim, hubungi admin untuk penyelesaian.

## 8. Voucher
- Voucher hanya berlaku sesuai syarat yang tertera (masa berlaku, minimal belanja, produk, dan kuota) dan tidak dapat diuangkan.

## 9. Batasan tanggung jawab
- Layanan pada akun dikelola oleh pihak ketiga. Perubahan kebijakan, fitur, atau gangguan dari layanan tersebut berada di luar kendali kami.
- Tanggung jawab kami terbatas pada penggantian akun sesuai garansi yang dipilih.

## 10. Perubahan ketentuan
- Ketentuan ini dapat diperbarui sewaktu-waktu. Versi terbaru selalu tersedia di halaman ini, dengan tanggal pembaruan terakhir di atas.

## 11. Kontak
Pertanyaan tentang ketentuan ini dapat dikirim ke {email} atau melalui WhatsApp admin.
MD,
        ],
        'privacy' => [
            'title' => 'Kebijakan Privasi',
            'slug' => 'kebijakan-privasi',
            'description' => 'Cara {nama_toko} mengumpulkan, memakai, dan melindungi data pribadi pelanggan.',
            'body' => <<<'MD'
Kebijakan ini menjelaskan data apa saja yang dikumpulkan {nama_toko} saat kamu memakai {situs}, untuk apa data itu dipakai, dan bagaimana kami melindunginya, dengan mengacu pada Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi.

## 1. Data yang kami kumpulkan
- **Data akun:** nama, alamat email, nomor WhatsApp, dan foto profil bila kamu masuk dengan Google.
- **Data pesanan:** produk yang dibeli, nama, email, dan nomor WhatsApp di form order, riwayat pembayaran, serta bukti transfer bila kamu mengunggahnya.
- **Laporan dan ulasan:** isi laporan, lampiran gambar, dan ulasan yang kamu kirim.
- **Percakapan dengan Tanya AI:** pertanyaan dan gambar yang kamu kirim ke asisten AI, bila kamu memakainya.
- **Data teknis:** alamat IP, jenis perangkat dan browser, serta data sesi yang dibutuhkan agar situs berjalan dan aman.
- **Catatan aktivitas:** waktu dan jenis tindakan yang kamu lakukan di situs, misalnya masuk, gagal masuk, keluar, membuat atau membatalkan pesanan, mengirim laporan atau ulasan, dan mengubah profil, beserta alamat IP dan perangkat yang dipakai. Isi kata sandi dan PIN tidak pernah dicatat.

## 2. Untuk apa data dipakai
- Memproses pesanan, memverifikasi pembayaran, dan mengirim detail akun ke email kamu.
- Menangani laporan, klaim garansi, dan pertanyaan pelanggan.
- Mengirim email yang berkaitan dengan pesanan dan akun kamu.
- Menjaga keamanan situs dan mencegah penyalahgunaan, misalnya mendeteksi percobaan masuk ke akun orang lain.
- Menelusuri gangguan teknis dan membantu menyelesaikan sengketa pesanan atau garansi.

## 3. Pihak lain yang terlibat
Kami tidak menjual data pribadi kamu. Data hanya dibagikan sebatas yang dibutuhkan kepada:
- penyedia layanan email untuk mengirim email pesanan dan akun;
- Google, bila kamu memilih masuk dengan akun Google;
- penyedia model AI, untuk memproses pertanyaan yang kamu kirim ke Tanya AI;
- pihak berwenang, bila diwajibkan oleh hukum.

## 4. Penyimpanan dan keamanan
- Kata sandi disimpan dalam bentuk terenkripsi satu arah dan tidak dapat dilihat siapa pun, termasuk admin.
- Bukti transfer disimpan di penyimpanan privat dan hanya dapat dibuka admin.
- Data disimpan selama akun kamu aktif dan selama dibutuhkan untuk catatan transaksi.
- Catatan aktivitas disimpan paling lama {masa_log_aktivitas} dan catatan teknis (log galat) paling lama {masa_log_sistem}, lalu dihapus otomatis. Keduanya hanya dapat dilihat admin, dan nilai rahasia seperti kata sandi disamarkan.

## 5. Cookie
Situs memakai cookie yang diperlukan agar kamu tetap masuk dan formulir aman. Kami tidak memakai cookie iklan.

## 6. Hak kamu
- Melihat dan memperbarui nama serta nomor WhatsApp di halaman Profil.
- Menghapus akun dari Profil > Pengaturan. Data pribadi pada akun akan dianonimkan, sedangkan catatan pesanan tetap disimpan untuk keperluan transaksi. Catatan aktivitas yang sudah ada tetap disimpan sampai masa simpannya habis, lalu terhapus otomatis.
- Meminta informasi tentang data kamu melalui kontak di bawah.

## 7. Perubahan kebijakan
Kebijakan ini dapat diperbarui sewaktu-waktu. Versi terbaru selalu tersedia di halaman ini, dengan tanggal pembaruan terakhir di atas.

## 8. Kontak
Pertanyaan tentang privasi dapat dikirim ke {email} atau melalui WhatsApp admin.
MD,
        ],
    ],
];
