<x-mail::message>
# Laporan baru dari pelanggan

**{{ $customer }}** ({{ $email }}) mengirim laporan.

<x-mail::panel>
**{{ $category }}**<br>
{{ $subject }}
</x-mail::panel>

{{ $excerpt }}

<x-mail::button :url="$url">
Buka daftar laporan
</x-mail::button>

Email ini dikirim karena notifikasi laporan diaktifkan di Pengaturan.

{{ \App\Support\StoreProfile::name() }}
</x-mail::message>
