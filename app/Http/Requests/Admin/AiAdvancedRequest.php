<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Pengaturan lanjutan AI (menu Koneksi). Batas minimal dan maksimal tiap angka ada di config/ai.php. */
class AiAdvancedRequest extends FormRequest
{
    use NormalizesWholeNumbers;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            ...collect(config('ai.advanced'))->keys()->mapWithKeys(fn ($k) => [$k => $this->wholeNumber($this->input($k))])->all(),
            // Satu awalan per baris; baris kosong dan duplikat dibuang.
            'strip_prefixes' => $this->lines('strip_prefixes'),
            'whatsapp_triggers' => $this->lines('whatsapp_triggers'),
        ]);
    }

    public function rules(): array
    {
        return [
            ...collect(config('ai.advanced'))->map(fn ($r) => ['required', 'integer', "min:{$r[1]}", "max:{$r[2]}"])->all(),
            'strip_prefixes' => ['array', 'max:20'],
            'strip_prefixes.*' => ['string', 'max:100'],
            'whatsapp_triggers' => ['array', 'max:30'],
            'whatsapp_triggers.*' => ['string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'thinking_headroom' => 'Ruang berpikir',
            'request_timeout' => 'Batas waktu permintaan',
            'connect_timeout' => 'Batas waktu koneksi',
            'test_tokens' => 'Batas jawaban tes',
            'history_messages' => 'Riwayat yang dikirim',
            'chat_per_minute' => 'Pesan chat per menit',
            'chat_image_mb' => 'Ukuran gambar chat',
            'chat_image_days' => 'Simpan gambar chat',
            'conversation_idle_hours' => 'Lanjutkan percakapan',
            'asker_orders' => 'Pesanan user untuk AI',
            'strip_prefixes' => 'Awalan baris yang dibuang',
            'strip_prefixes.*' => 'Awalan baris yang dibuang',
            'whatsapp_triggers' => 'Pemicu tombol WhatsApp',
            'whatsapp_triggers.*' => 'Pemicu tombol WhatsApp',
        ];
    }

    /** @return list<string> satu nilai per baris; baris kosong dan duplikat dibuang */
    private function lines(string $field): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) $this->input($field))))));
    }
}
