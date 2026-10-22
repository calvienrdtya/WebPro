<x-mail::message>
# Akun kamu segera habis

Halo {{ $name }}, akun **{{ $product }}** dari pesanan **{{ $code }}** akan habis pada **{{ $expires }}**{{ $daysLeft > 0 ? ' ('.$daysLeft.' hari lagi)' : ' (hari ini)' }}.

Supaya tetap bisa dipakai tanpa jeda, perpanjang dengan membuat pesanan baru sebelum tanggal tersebut. Akun baru dikirim setelah pembayaran diverifikasi.

<x-mail::button :url="$url">
Perpanjang sekarang
</x-mail::button>

Abaikan email ini bila kamu tidak ingin memperpanjang.

{{ \App\Support\StoreProfile::name() }}
</x-mail::message>
