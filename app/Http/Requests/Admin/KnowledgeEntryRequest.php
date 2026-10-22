<?php

namespace App\Http\Requests\Admin;

use App\Models\KnowledgeEntry;
use App\Support\AiSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Tambah atau ubah entri pengetahuan. Batas lampiran (jumlah gambar, ukuran, halaman PDF) dari Pengaturan lanjutan.
 * Satu entri boleh punya banyak gambar dan paling banyak satu PDF.
 */
class KnowledgeEntryRequest extends FormRequest
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'use_ai' => $this->boolean('use_ai'),
            'with_qa' => $this->boolean('with_qa'),
            'products' => array_values(array_filter((array) $this->input('products', []))),
            'remove_attachments' => array_values(array_filter((array) $this->input('remove_attachments', []))),
        ]);
    }

    public function rules(): array
    {
        $maxMb = max(AiSettings::advanced('image_mb'), AiSettings::advanced('pdf_mb'));

        return [
            'title' => ['required', 'string', 'max:150'],
            'raw_text' => ['nullable', 'string', 'max:20000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'use_ai' => ['boolean'],
            'with_qa' => ['boolean'],
            'products' => ['array'],
            'products.*' => ['integer', 'exists:products,id'],
            'attachments' => ['array'],
            'attachments.*' => ['file', 'max:'.($maxMb * 1024)],
            'remove_attachments' => ['array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var KnowledgeEntry|null $entry */
            $entry = $this->route('entry');
            $kept = $entry ? $entry->attachments->whereNotIn('id', $this->input('remove_attachments'))->values() : collect();
            $files = collect($this->file('attachments', []));

            $images = $kept->where('kind', 'image')->count();
            $pdfs = $kept->where('kind', 'pdf')->count();

            foreach ($files as $i => $file) {
                /** @var UploadedFile $file */
                $mime = $file->getMimeType();
                $name = $file->getClientOriginalName();

                if (in_array($mime, self::IMAGE_MIMES, true)) {
                    $images++;
                    if ($file->getSize() > AiSettings::advanced('image_mb') * 1024 * 1024) {
                        $validator->errors()->add("attachments.{$i}", "Gambar {$name} melebihi ".AiSettings::advanced('image_mb').' MB.');
                    }
                } elseif ($mime === 'application/pdf') {
                    $pdfs++;
                    if ($file->getSize() > AiSettings::advanced('pdf_mb') * 1024 * 1024) {
                        $validator->errors()->add("attachments.{$i}", "PDF {$name} melebihi ".AiSettings::advanced('pdf_mb').' MB.');
                    } elseif (($pages = static::pdfPages($file->getRealPath())) > AiSettings::advanced('pdf_pages')) {
                        $validator->errors()->add("attachments.{$i}", "PDF {$name} berisi {$pages} halaman, maksimal ".AiSettings::advanced('pdf_pages').'.');
                    }
                } else {
                    $validator->errors()->add("attachments.{$i}", "{$name} tidak didukung. Pakai gambar JPG, PNG, WebP, atau PDF.");
                }
            }

            if ($images > AiSettings::advanced('max_images')) {
                $validator->errors()->add('attachments', 'Maksimal '.AiSettings::advanced('max_images').' gambar per entri.');
            }
            if ($pdfs > 1) {
                $validator->errors()->add('attachments', 'Maksimal 1 PDF per entri. Pecah dokumen lain menjadi entri sendiri.');
            }

            $hasText = trim((string) $this->input('raw_text')) !== '';
            if (! $this->boolean('use_ai') && ! $hasText && trim((string) $this->input('body')) === '') {
                $validator->errors()->add('raw_text', 'Isi teks dulu. Tanpa dirapikan AI, teks inilah yang dipakai.');
            } elseif (! $hasText && $images + $pdfs === 0) {
                $validator->errors()->add('raw_text', 'Isi teks atau unggah minimal satu lampiran.');
            }
        }];
    }

    /** Perkiraan jumlah halaman PDF dari struktur filenya; 0 bila tidak terbaca (mis. PDF terkompresi), tidak memblokir. */
    public static function pdfPages(string $path): int
    {
        $content = (string) @file_get_contents($path);

        return preg_match_all('/\/Type\s*\/Page(?!s)/', $content);
    }

    public function attributes(): array
    {
        return [
            'title' => 'Judul',
            'raw_text' => 'Teks',
            'body' => 'Isi Markdown',
            'products' => 'Produk terkait',
            'attachments' => 'Lampiran',
            'attachments.*' => 'Lampiran',
        ];
    }
}
