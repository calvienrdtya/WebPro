# ProAkses (backend)

Toko akun premium: storefront Blade + Alpine.js (tanpa Livewire), panel admin Inertia + React (Laravel 13).

- Rencana, keputusan bisnis, dan status per modul: [`../Develop.md`](../Develop.md)
- Panduan memasang di hosting: [`docs/deploy.md`](docs/deploy.md) (cek kesiapan juga ada di admin, menu Panduan Deploy)

## Perintah dev

```bash
composer install && npm install
php artisan migrate          # jangan migrate:fresh di database yang berisi data
npm run build                # aset toko dan admin ke public/build
php artisan test             # PHPUnit (SQLite memori, mail palsu, log dimatikan)
```

## Email dan antrean

Email ke pelanggan dan admin **tidak dikirim langsung**, tetapi masuk antrean database (tabel `jobs`) lalu dikirim
oleh worker yang dijalankan cron setiap menit (`queue:work --stop-when-empty` di `routes/console.php`).

| Email | Lewat antrean | Keterangan |
|---|---|---|
| Akun terkirim / akun pengganti (`AccountDeliveredMail`) | ya | kredensial dibaca ulang saat email dikirim |
| Pembayaran diterima (`PaymentReceivedMail`) | ya | |
| Pengingat perpanjangan (`RenewalReminderMail`) | ya | |
| Notifikasi admin: laporan baru, pembayaran menunggu, stok kurang | ya | sesuai saklar di Pengaturan |
| Kode lupa sandi (`PasswordResetCodeMail`) | **tidak** | harus sampai dalam hitungan detik; dikirim setelah respons (`defer`) |

Aturannya:
- Email baru yang lewat antrean cukup `extends App\Mail\QueuedMail` dan dikirim dengan `Mail::to(...)->queue(...)`.
- Masuk antrean **setelah transaksi database selesai** (`ShouldQueueAfterCommit`); bila transaksi batal, email tidak terkirim.
- Bila SMTP gagal, dicoba ulang sesuai `config/proakses.php` → `mail_queue` (bawaan 3 kali, jeda 60 dan 300 detik).
  Setelah itu masuk tabel `failed_jobs`; cek "Antrean tidak macet" di Panduan Deploy menghitungnya.
  Kirim ulang: `php artisan queue:retry all`.
- Email sampai paling lambat sekitar 1 menit setelah aksi (jeda cron). **Tanpa cron, email tidak pernah terkirim.**
- Di lokal tanpa cron, jalankan `php artisan queue:work` di terminal terpisah agar email terkirim.

## Log

- **Log Sistem** (admin, menu Log): file `storage/logs/laravel-YYYY-MM-DD.log`, satu per hari.
- **Log Aktivitas** (admin, menu Log): tabel `activity_logs`. Aksi baru yang mengubah data didaftarkan di
  `config/activity.php` (rute) atau memakai trait `App\Console\Concerns\RecordsActivity` (perintah terjadwal).
- Masa simpan keduanya diatur di Pengaturan; penghapusan oleh `logs:prune` setiap hari pukul 03.15.

## Backup database

- Menu admin **Backup**: buat backup sekarang, unduh, hapus, dan atur backup otomatis (nyala/mati, setiap berapa hari,
  jumlah file disimpan). Bawaan di `config/proakses.php` → `backup` (otomatis tiap 1 hari, simpan 7 file).
- File `storage/app/private/backups/backup-{otomatis|manual}-YYYY-MM-DD-HHMMSS.sql.gz`, dibuat murni PHP
  (`App\Support\DatabaseBackup`, tanpa mysqldump). Hanya tabel database aplikasi; isi `sessions`, `cache`, `jobs`,
  `password_reset_tokens` tidak disalin (strukturnya tetap).
- Otomatis: cron menjalankan `backup:database --auto` setiap hari pukul 02.30; dibuat bila sudah lewat selang hari.
- Memulihkan: buat database kosong lalu impor file `.sql.gz` (phpMyAdmin > Import, atau `gunzip -c file.sql.gz | mysql nama_db`).
  Sandi stok akun terenkripsi dengan `APP_KEY`; simpan `APP_KEY` di tempat aman.
- Gambar unggahan dan file privat (bukti transfer, lampiran) **tidak** termasuk; cadangkan `public/uploads` dan
  `storage/app/private` lewat panel hosting.

## Gambar unggahan (otomatis dikecilkan + WebP)

- `App\Support\ImageOptimizer`: setiap unggahan gambar produk, hero, logo toko, gambar bagikan, dan logo kampus
  disimpan **file aslinya** di `storage/app/private/image-originals`, lalu toko memakai versi yang dikecilkan ke ukuran
  maksimal per jenis (`config/proakses.php` → `images.profiles`) dan disimpan **WebP** (kualitas di Pengaturan, bawaan 90).
  Gambar produk/hero juga dibuatkan varian **JPG** untuk pratinjau tautan (og:image).
- Tidak diubah: QRIS dan favicon.
- Pengaman: hasil hanya dipakai bila minimal 10% lebih kecil (`images.min_saving`); gambar tak terbaca, terlalu besar
  untuk memori PHP, atau server tanpa GD WebP disimpan apa adanya tanpa galat.
- Per gambar di admin: **Pakai file asli** atau **Optimalkan** (form Produk dan Tampilan Toko). Tabel `uploaded_images`.
- Gambar lama: `php artisan images:optimize` (`--dry-run` untuk melihat daftar). Aman dijalankan ulang.
- Folder `storage/app/private/image-originals` ikut dicadangkan bersama `public/uploads`.

## Catatan perubahan

### 2026-10-23

- **Kategori produk otomatis dari form produk** (menggantikan enum `ProductCategory`; tidak ada menu CRUD khusus).
  - Tabel baru `product_categories` (`name`, `slug` unik). `products.category` (string) diganti `products.category_id`.
    Migrasi `2026_10_26_100000_create_product_categories_table` memindahkan data lama (`ai` = Akun AI, `streaming`, `tools`).
  - Form produk: dropdown kategori punya opsi **+ Kategori baru** dengan pratinjau nama dan URL. Kategori dibuat saat produk disimpan;
    nama yang sama (huruf besar/kecil diabaikan) memakai kategori yang sudah ada.
  - Ikon pensil di samping dropdown untuk **ganti nama**. Slug dikunci agar URL `?kategori=...` yang sudah terindeks tetap hidup.
  - Toko: filter kategori hanya menampilkan kategori yang punya produk aktif. Kategori kosong disembunyikan, tidak dihapus.
  - Tidak ada hapus kategori lewat dashboard; baris kategori kosong dibiarkan.
- **Card produk di toko memakai gambar 16:9** (`aspect-video`, `400x225`) pada layout grid (`components/store/product-card.blade.php`).
  Gambar utama halaman detail produk (`store/products/show.blade.php`) juga 16:9 (`800x450`). Hanya card `featured` di beranda (thumbnail persegi) yang tidak diubah.
- **Beranda: bagian "Produk Terlaris" dihapus**, diganti satu bagian per kategori (Akun AI, Streaming, Tools, dan kategori baru),
  masing-masing hanya berisi produk kategorinya (maks. 8 card, tautan "Lihat semua" ke `/produk?kategori=slug`).
  Kategori tanpa produk aktif tidak tampil. Bagian "Semua produk" tidak diubah.
  File: `Store/HomeController.php`, `store/home.blade.php`.
- **Admin, menu Produk: tanpa paginasi.** Semua produk tampil sekaligus (tetap bisa dicari dan difilter); footer hanya menampilkan jumlah produk.
- **Dashboard: batang merah HPP** di chart (sekarang 3 batang: Revenue, HPP, Total laba). HPP di batang hanya dari akun yang sudah terkirim (tanpa estimasi); perhitungan Total laba tidak diubah.
- **Dashboard: chart mengikuti filter periode** (sebelumnya tetap 12 bulan). Bulan ini, bulan lalu, 7 hari, dan 30 hari tampil per hari; tahun ini dan semua tampil per bulan.
  Logika di `DashboardStats::chart()`; prop Inertia `monthly` diganti `chart` (`unit` + `points`).
- **Dashboard: chart berubah dari batang ke garis** (3 garis halus: Revenue, HPP merah, Total laba hijau). Hanya tampilan di
  `resources/admin/features/dashboard/components/overview.tsx`; data, filter, tooltip, dan legend tidak berubah. Titik data digambar
  bila titiknya 12 atau kurang (7 hari, bulanan), selebihnya muncul saat di-hover. Animasi dimatikan agar pindah filter terasa ringan.
- **Admin, menu Produk: kolom Stok** (jumlah akun berstatus tersedia per produk; "Habis" bila 0, oranye bila 5 atau kurang, sama dengan batas di toko). Scope baru `Product::withStockCount()`.
- **Card produk (layout grid):** badge "akun terjual" di atas judul diganti badge diskon (di sebelah badge stok, hanya bila ada diskon); di baris bawah ada ikon WhatsApp kecil di kiri (link `wa.me`
  dengan pesan "Halo ProAkses, saya mau tanya {produk}", buka tab baru) dan tombol Beli di kanan. Ikon disembunyikan bila nomor WhatsApp admin belum diisi.
  Card `featured` di beranda tidak diubah. File: `components/store/product-card.blade.php`.
- **Footer toko:** email dihapus dari kolom Kontak (tersisa WhatsApp admin dan media sosial). Isian email di Tampilan Toko tidak dihapus. File: `store/partials/footer.blade.php`.
- **Pengaturan: teks pembuka chat WhatsApp.** Di menu Pengaturan admin, dua teks yang bisa diubah: tombol WhatsApp di produk (card dan halaman detail) dan pop up WhatsApp.
  Penanda `{toko}`, `{produk}`, `{link}` (untuk teks produk) diisi otomatis, mis. produk Netflix membuka chat "Halo ProAkses, saya mau tanya produk Netflix Premium.".
  Penanda tak dikenal atau teks lebih dari 300 huruf ditolak; kosong atau sama dengan bawaan = pakai bawaan (`config/proakses.php` → `whatsapp_messages`).
  Disimpan di tabel `settings` (kunci `chat.wa_message.product|general`), tanpa migrasi. Logika di `App\Support\Whatsapp` (`template`, `productMessage`, `generalMessage`).

**Saat deploy perubahan ini ke production:**
1. Backup database dulu (menu admin Backup).
2. Unggah kode, lalu `php artisan migrate` (menghapus kolom `products.category`; ada `down()` untuk mundur).
3. `npm run build` lalu unggah `public/build` (perlu karena halaman admin Produk berubah).
