<x-mail::message>
# Pembayaran diterima

Halo {{ $name }}, pembayaran pesanan **{{ $code }}** untuk **{{ $product }}** sudah kami verifikasi.

Akun kamu sedang kami siapkan dan akan dikirim ke **{{ $email }}** secepatnya. Kamu tidak perlu melakukan apa pun; begitu akun siap, kami kirim email berisi detail akunnya. Masa garansi baru mulai dihitung saat akun terkirim.

<x-mail::button :url="$url">
Lihat pesanan
</x-mail::button>

{{ \App\Support\StoreProfile::name() }}
</x-mail::message>
