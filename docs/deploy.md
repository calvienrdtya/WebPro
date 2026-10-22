# Panduan deploy ProAkses

Panduan ini untuk hosting PHP biasa (cPanel atau sejenisnya) tanpa Node.js. Cek kesiapan di atas halaman ini membaca kondisi server tempat aplikasi berjalan, jadi buka lagi halaman ini setelah aplikasi online sampai semuanya hijau.

## 1. Kebutuhan server

- **PHP 8.4** dengan ekstensi: pdo_mysql, mbstring, openssl, curl, fileinfo, tokenizer, xml, ctype, dan gd.
- **MySQL 8** atau MariaDB 10.6 ke atas.
- **Cron** yang bisa berjalan setiap menit.
- **HTTPS** (SSL) aktif di domain.
- Pengaturan PHP yang disarankan:
  - `upload_max_filesize = 40M`
  - `post_max_size = 40M`
  - `memory_limit = 256M`
  - `max_execution_time = 120`

## 2. Siapkan file di komputer

1. Build tampilan admin dan toko (hosting tidak punya Node.js):
   ```
   cd backend
   npm ci
   npm run build
   ```
   Hasilnya ada di `public/build` dan **ikut diunggah**.
2. Pasang dependensi PHP tanpa paket pengembangan:
   ```
   composer install --no-dev --optimize-autoloader
   ```
   Kalau server punya Composer, langkah ini boleh dijalankan di server.
3. Jangan ikut mengunggah: `node_modules`, `.env` lokal, `storage/logs/*`, dan `tests`.

## 3. Unggah ke hosting

1. Unggah seluruh isi folder `backend` ke folder **di luar** `public_html`, misalnya `/home/USER/proakses`.
2. Arahkan **document root** domain ke folder `public` di dalamnya, misalnya `/home/USER/proakses/public`. Di cPanel: Domains, lalu ubah Document Root.
3. Pastikan folder ini bisa ditulis oleh PHP (izin 775):
   - `storage` beserta isinya
   - `bootstrap/cache`
   - `public/uploads`

## 4. Database

1. Buat database dan user MySQL baru di panel hosting.
2. Isi `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` di `.env` (langkah 5).
3. Jalankan migrasi:
   ```
   php artisan migrate --force
   ```
4. **Jangan pernah** menjalankan `migrate:fresh`, `migrate:refresh`, atau `db:seed` di production. Perintah itu menghapus data atau mengisi data contoh.

## 5. Isi file .env

Salin `.env.example` menjadi `.env`, lalu isi:

| Variabel | Nilai |
|---|---|
| `APP_NAME` | Nama toko |
| `APP_ENV` | `production` |
| `APP_KEY` | Buat **sekali saja** dengan `php artisan key:generate`, lalu cadangkan nilainya |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://domainkamu.com` (tanpa garis miring di akhir) |
| `LOG_LEVEL` | `error` |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` | `database` |
| `CACHE_STORE` | `database` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` | Dari penyedia email (Resend, Brevo, dan sejenisnya) |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Alamat pengirim di domain sendiri, mis. `noreply@domainkamu.com` |
| `ADMIN_WHATSAPP_NUMBER` | Nomor WhatsApp admin, mis. `0812...` |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Dari Google Cloud Console; redirect `https://domainkamu.com/auth/google/callback` |
| `PASSWORD_RESET_THROTTLE` | `true` |

**Penting soal `APP_KEY`:** API key router AI disimpan terenkripsi dengan kunci ini. Kalau `APP_KEY` hilang atau berubah, semua sesi login keluar dan API key AI harus diisi ulang di menu Koneksi.

## 6. Akun admin pertama

Buat akun admin dengan email dan sandi kuat milik sendiri:

```
php artisan tinker
>>> $u = new App\Models\User;
>>> $u->forceFill(['name' => 'Nama Admin', 'email' => 'emailkamu@domain.com', 'password' => 'SandiKuatPanjang!', 'is_admin' => true, 'email_verified_at' => now()])->save();
>>> exit
```

Kalau database berasal dari salinan lokal, **hapus atau ganti** akun `admin@gmail.com`.

## 7. Optimasi

Jalankan setelah setiap deploy dan setiap kali `.env` berubah:

```
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Sekali saja setelah data dan gambar lama dipindahkan ke server: kecilkan gambar yang sudah ada (file asli tetap disimpan,
bisa dikembalikan per gambar dari admin). Lihat daftarnya dulu dengan `--dry-run`.

```
php artisan images:optimize --dry-run
php artisan images:optimize
```

## 8. Cron (wajib)

Tambahkan satu cron yang berjalan **setiap menit**. Di cPanel: Cron Jobs, interval "Once Per Minute":

```
cd /home/USER/proakses && php artisan schedule:run >> /dev/null 2>&1
```

Kalau `php` di cron bukan PHP 8.4, pakai path lengkapnya (tanyakan ke hosting, misalnya `/usr/local/bin/ea-php84`).

Tanpa cron:
- **email tidak terkirim sama sekali** (akun ke pelanggan, pembayaran diterima, notifikasi admin): semuanya menunggu di antrean,
- pesanan yang tidak dibayar tidak kedaluwarsa,
- akun bulan berikutnya tidak terkirim,
- akun yang habis masa aktifnya tidak ditandai,
- entri Pengetahuan AI tertahan di status Diproses.

## 9. Email dan DNS

Di pengaturan DNS domain, tambahkan catatan **SPF** dan **DKIM** dari penyedia email. Tanpa itu, email berisi akun pelanggan mudah masuk spam.

## 10. Setelah online

1. Buka menu ini lagi dan bereskan semua yang belum hijau.
2. Isi data asli: produk dan harga, stok akun, gambar QRIS (Pengaturan), Tampilan Toko (logo, sosial media, email kontak), dan Halaman Legal.
3. Hapus data contoh (ulasan contoh, produk dan stok uji, voucher uji).
4. Lakukan satu transaksi sungguhan: daftar, beli, bayar QRIS, verifikasi di admin, pastikan email akun masuk.

## 11. Memperbarui aplikasi

1. **Cadangkan dulu** database, folder `public/uploads`, folder `storage/app/private`, dan file `.env`.
2. Di komputer: `npm run build` dan `composer install --no-dev --optimize-autoloader`.
3. Di server:
   ```
   php artisan down
   ```
4. Unggah file yang berubah, termasuk `public/build`. Jangan menimpa `.env`, `public/uploads`, dan `storage`.
5. Di server:
   ```
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan up
   ```

## 12. Cadangan rutin

Cadangkan setiap hari, lewat fitur backup hosting atau cron:
- database MySQL: sudah otomatis lewat menu admin **Backup** (bawaan setiap hari, 7 file). Unduh salinannya ke luar server secara berkala,
- `public/uploads` (logo, gambar produk, QRIS),
- `storage/app/private` (bukti transfer, lampiran laporan dan pengetahuan, file asli gambar di `image-originals`),
- file `.env` (simpan di tempat aman, terpisah dari server).

## 13. Masalah umum

| Gejala | Penyebab dan solusi |
|---|---|
| Semua halaman error 500 | Lihat menu admin Log Sistem, atau file `storage/logs/laravel-YYYY-MM-DD.log` bila admin tidak bisa dibuka. Biasanya izin folder `storage` atau isi `.env` salah. |
| "Page Expired" (419) saat login | `APP_URL` tidak sama dengan alamat yang dibuka, atau https belum aktif padahal `SESSION_SECURE_COOKIE=true`. |
| Perubahan `.env` tidak terbaca | Jalankan ulang `php artisan config:cache`. |
| Email tidak terkirim | Pastikan cron berjalan (email lewat antrean). Lalu cek "Antrean tidak macet" di Panduan Deploy: job gagal berarti SMTP menolak; cek `MAIL_*`, Log Sistem, dan verifikasi domain di penyedia email, lalu `php artisan queue:retry all`. |
| Pesanan tidak kedaluwarsa, akun bulanan tidak terkirim | Cron belum berjalan. Lihat cek "Cron schedule:run berjalan" di atas. |
| Gambar unggahan tidak muncul | Folder `public/uploads` tidak bisa ditulis atau tidak ikut diunggah. |
| Unggah file gagal dengan galat 413 | Naikkan `upload_max_filesize` dan `post_max_size` di pengaturan PHP hosting. |
