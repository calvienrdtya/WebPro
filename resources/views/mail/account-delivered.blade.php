<x-mail::message>
# {{ $replacement ? 'Akun pengganti kamu sudah siap' : 'Akun kamu sudah siap' }}

@if ($replacement)
Halo {{ $name }}, akun untuk pesanan **{{ $code }}** sudah kami ganti. Gunakan akun di bawah ini, akun sebelumnya tidak berlaku lagi.
@else
Halo {{ $name }}, pembayaran pesanan **{{ $code }}** sudah kami verifikasi.
@endif
@if ($months > 1)
Ini akun untuk **bulan ke-{{ $sequence }} dari {{ $months }}**.
@endif

<x-mail::panel>
**{{ $product }}**<br>
Email: {{ $email ?: '-' }}<br>
Password: {{ $password ?: '-' }}<br>
@if ($profile)
Profil: {{ $profile }}<br>
@endif
@if ($pin)
PIN: {{ $pin }}<br>
@endif
@if ($warrantyEnds)
Garansi akun ini berlaku sampai {{ $warrantyEnds }}.
@endif
</x-mail::panel>

Jangan ubah email, password, atau PIN akun, dan jangan bagikan ke orang lain. Detail pesanan juga bisa dilihat kapan saja di halaman pesanan.

<x-mail::button :url="$url">
Lihat pesanan
</x-mail::button>

{{ \App\Support\StoreProfile::name() }}
</x-mail::message>
