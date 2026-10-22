import { type FormEvent } from 'react'
import { useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { FieldError } from '@/features/products/field-error'

export type AdvancedKey =
  | 'thinking_headroom' | 'request_timeout' | 'connect_timeout' | 'test_tokens' | 'history_messages'
  | 'chat_per_minute' | 'chat_image_mb' | 'chat_image_days' | 'conversation_idle_hours' | 'asker_orders'
  | 'convert_tokens' | 'convert_timeout' | 'knowledge_cache_seconds' | 'provider_overhead' | 'full_context_limit'
  | 'max_images' | 'image_mb' | 'pdf_mb' | 'pdf_pages'
export type AdvancedRange = { default: number; min: number; max: number }
type TextKey = AdvancedKey | 'strip_prefixes' | 'whatsapp_triggers'

type Props = {
  values: Record<AdvancedKey, number> & { strip_prefixes: string; whatsapp_triggers: string }
  ranges: Record<AdvancedKey, AdvancedRange>
  defaults: { strip_prefixes: string; whatsapp_triggers: string }
}

const FIELDS: { key: AdvancedKey; label: string; unit: string; hint: string }[] = [
  { key: 'thinking_headroom', label: 'Ruang berpikir', unit: 'token', hint: 'Ditambahkan di atas batas panjang jawaban. Model yang berpikir (mis. Gemini 3) menghitung token berpikir di dalamnya; terlalu kecil membuat balasan kosong.' },
  { key: 'request_timeout', label: 'Batas waktu permintaan', unit: 'detik', hint: 'Lama menunggu jawaban router sebelum dianggap gagal.' },
  { key: 'connect_timeout', label: 'Batas waktu koneksi', unit: 'detik', hint: 'Lama menunggu router menerima sambungan.' },
  { key: 'test_tokens', label: 'Batas jawaban tes', unit: 'token', hint: 'Batas panjang jawaban untuk Tes chat dan Tes vision.' },
  { key: 'history_messages', label: 'Riwayat yang dikirim', unit: 'pesan', hint: 'Jumlah pesan terakhir percakapan yang ikut dikirim ke AI. Makin banyak makin paham konteks, tetapi makin boros token. 0 = tanpa riwayat.' },
  { key: 'chat_per_minute', label: 'Pesan chat per menit', unit: 'pesan', hint: 'Pengaman spam di widget toko per penanya, di luar batas harian di menu Perilaku.' },
  { key: 'chat_image_mb', label: 'Ukuran gambar chat', unit: 'MB', hint: 'Ukuran maksimal gambar yang dikirim pelanggan di widget toko.' },
  { key: 'chat_image_days', label: 'Simpan gambar chat', unit: 'hari', hint: 'Gambar dari pelanggan disimpan privat untuk log, lalu dihapus otomatis setelah sekian hari. Teks percakapan tetap tersimpan.' },
  { key: 'conversation_idle_hours', label: 'Lanjutkan percakapan', unit: 'jam', hint: 'User login melanjutkan percakapan terakhirnya (juga di perangkat lain) bila belum lewat sekian jam; setelahnya mulai percakapan baru.' },
  { key: 'asker_orders', label: 'Pesanan user untuk AI', unit: 'pesanan', hint: 'Jumlah pesanan terakhir user login yang diberikan ke AI agar bisa menjawab status pesanan.' },
  { key: 'convert_tokens', label: 'Batas hasil rapikan pengetahuan', unit: 'token', hint: 'Panjang maksimal Markdown hasil merapikan satu entri.' },
  { key: 'convert_timeout', label: 'Batas waktu rapikan pengetahuan', unit: 'detik', hint: 'PDF tebal butuh waktu lebih lama.' },
  { key: 'knowledge_cache_seconds', label: 'Cache pengetahuan', unit: 'detik', hint: 'Stok dan harga di jawaban AI paling lambat terbarui setelah selang ini. 0 = selalu baru (lebih berat).' },
  { key: 'provider_overhead', label: 'Beban tersembunyi penyedia', unit: 'token', hint: 'Perkiraan token tambahan dari penyedia model per pesan, hanya untuk meteran di menu Pengetahuan.' },
  { key: 'full_context_limit', label: 'Batas aman token per pesan', unit: 'token', hint: 'Di atas ini menu Pengetahuan memberi peringatan.' },
  { key: 'max_images', label: 'Gambar per entri', unit: 'buah', hint: 'Jumlah gambar maksimal di satu entri pengetahuan.' },
  { key: 'image_mb', label: 'Ukuran gambar', unit: 'MB', hint: 'Ukuran maksimal satu gambar pengetahuan.' },
  { key: 'pdf_mb', label: 'Ukuran PDF', unit: 'MB', hint: 'Ukuran maksimal PDF pengetahuan. Batas unggah server (upload_max_filesize) juga harus cukup.' },
  { key: 'pdf_pages', label: 'Halaman PDF', unit: 'halaman', hint: 'Jumlah halaman maksimal PDF pengetahuan.' },
]

export function AdvancedForm({ values, ranges, defaults }: Props) {
  const form = useForm({
    ...Object.fromEntries(FIELDS.map((f) => [f.key, String(values[f.key])])),
    strip_prefixes: values.strip_prefixes,
    whatsapp_triggers: values.whatsapp_triggers,
  } as Record<TextKey, string>)
  const errors = form.errors as Record<string, string | undefined>

  const set = (key: TextKey, value: string) => {
    form.clearErrors(key)
    form.setData(key, value)
  }

  const useDefaults = () => {
    form.clearErrors()
    form.setData({
      ...Object.fromEntries(FIELDS.map((f) => [f.key, String(ranges[f.key].default)])),
      strip_prefixes: defaults.strip_prefixes,
      whatsapp_triggers: defaults.whatsapp_triggers,
    } as Record<TextKey, string>)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.put('/admin/ai/lanjutan', { preserveScroll: true })
  }

  return (
    <form onSubmit={submit} noValidate className='mt-6 space-y-4 rounded-lg border p-4'>
      <div className='flex items-start justify-between gap-3'>
        <div>
          <h2 className='font-semibold'>Pengaturan lanjutan</h2>
          <p className='text-sm text-muted-foreground'>Angka teknis untuk semua permintaan ke router. Biarkan bawaan bila tidak ada masalah.</p>
        </div>
        <Button type='button' variant='link' size='sm' className='h-auto shrink-0 p-0' onClick={useDefaults}>
          Pakai semua bawaan
        </Button>
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        {FIELDS.map((f) => (
          <div key={f.key} className='space-y-2'>
            <Label htmlFor={f.key}>
              {f.label} ({f.unit})
            </Label>
            <Input id={f.key} inputMode='numeric' value={form.data[f.key]} onChange={(e) => set(f.key, e.target.value)} />
            <p className='text-xs text-muted-foreground'>
              {f.hint} {ranges[f.key].min} sampai {ranges[f.key].max}, bawaan {ranges[f.key].default}.
            </p>
            <FieldError message={errors[f.key]} />
          </div>
        ))}
      </div>

      <div className='space-y-2'>
        <Label htmlFor='strip_prefixes'>Awalan baris yang dibuang dari jawaban</Label>
        <Textarea id='strip_prefixes' rows={3} value={form.data.strip_prefixes} onChange={(e) => set('strip_prefixes', e.target.value)} />
        <p className='text-xs text-muted-foreground'>
          Satu awalan per baris, huruf besar dan kecil dianggap sama. Baris jawaban AI yang diawali teks ini dibuang sebelum sampai ke pelanggan (mis. catatan "skipped:" yang bocor dari penyedia).
        </p>
        <FieldError message={errors.strip_prefixes ?? Object.entries(errors).find(([k]) => k.startsWith('strip_prefixes.'))?.[1]} />
      </div>

      <div className='space-y-2'>
        <Label htmlFor='whatsapp_triggers'>Pemicu tombol Chat WhatsApp</Label>
        <Textarea id='whatsapp_triggers' rows={4} value={form.data.whatsapp_triggers} onChange={(e) => set('whatsapp_triggers', e.target.value)} />
        <p className='text-xs text-muted-foreground'>
          Satu kata atau frasa per baris, huruf besar dan kecil dianggap sama. Bila jawaban AI memuat salah satunya, atau memuat nomor WhatsApp admin, widget menampilkan tombol Chat WhatsApp yang sudah berisi pertanyaan pelanggan.
        </p>
        <FieldError message={errors.whatsapp_triggers ?? Object.entries(errors).find(([k]) => k.startsWith('whatsapp_triggers.'))?.[1]} />
      </div>

      <Button type='submit' disabled={form.processing}>
        {form.processing ? 'Menyimpan...' : 'Simpan'}
      </Button>
    </form>
  )
}
