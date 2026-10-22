<x-mail::message>
# Stok akun kurang

Kiriman akun bulanan butuh stok yang belum cukup.

<x-mail::table>
| Produk | Tertunda | Jatuh tempo 7 hari | Stok tersedia |
| :-- | --: | --: | --: |
@foreach ($lines as $l)
| {{ $l['product'] }} | {{ $l['overdue'] }} | {{ $l['upcoming'] }} | {{ $l['stock'] }} |
@endforeach
</x-mail::table>

Tambahkan stok akun di menu Stok Akun. Kiriman yang tertunda otomatis terkirim pada pengecekan berikutnya (tiap jam), atau kirim manual dari halaman Pesanan.

<x-mail::button :url="$url">
Lihat kiriman tertunda
</x-mail::button>

Email ini dikirim karena notifikasi email diaktifkan di Pengaturan.

{{ \App\Support\StoreProfile::name() }}
</x-mail::message>
