<x-mail::message>
# Pembayaran menunggu verifikasi

**{{ $customer }}** ({{ $email }}) menekan "Saya Sudah Bayar".

<x-mail::panel>
**{{ $code }}**<br>
{{ $product }}<br>
Total: **{{ $total }}**
</x-mail::panel>

Cocokkan dengan mutasi QRIS, lalu konfirmasi atau tolak di antrean pesanan.

<x-mail::button :url="$url">
Buka antrean verifikasi
</x-mail::button>

Email ini dikirim karena notifikasi email diaktifkan di Pengaturan.

{{ \App\Support\StoreProfile::name() }}
</x-mail::message>
