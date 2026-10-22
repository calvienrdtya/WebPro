<x-mail::message>
# Kode reset password

Halo {{ $name }},

Gunakan kode berikut untuk mengatur ulang password akun ProAkses kamu:

<x-mail::panel>
**{{ $spacedCode }}**
</x-mail::panel>

Kode berlaku **{{ $minutes }} menit** dan hanya bisa dipakai sekali. Jangan bagikan kode ini ke siapa pun, termasuk yang mengaku dari ProAkses.

Kalau kamu tidak meminta reset password, abaikan email ini. Password kamu tidak berubah.

Salam,<br>
{{ \App\Support\StoreProfile::name() }}
</x-mail::message>
