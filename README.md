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
