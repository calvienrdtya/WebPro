import { type FormEvent, useState } from 'react'
import { useForm } from '@inertiajs/react'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { postJson } from '@/features/ai/api'
import { FieldError } from '@/features/products/field-error'

export type TemplateName = 'system' | 'test_question' | 'vision_test' | 'knowledge_convert' | 'knowledge_qa' | 'asker_guest' | 'asker_user'
export type Templates = Record<TemplateName, string>

type Props = {
  templates: Templates
  defaults: Templates
  placeholders: Record<TemplateName, string[]>
  /** Isian form Perilaku saat ini (boleh belum disimpan), dipakai Pratinjau. */
  behavior: { name: string; instructions: string; fallback: string }
}

const PLACEHOLDER_HINTS: Record<string, string> = {
  nama: 'nama asisten',
  tanggal: 'tanggal hari ini',
  instruksi: 'isi kolom Instruksi',
  pesan_tidak_tahu: 'isi kolom Pesan saat tidak tahu',
  whatsapp: 'nomor WhatsApp admin',
  pengetahuan: 'data toko dan menu Pengetahuan (wajib)',
  judul: 'judul entri',
  produk: 'produk terkait',
  teks: 'teks dari admin (wajib)',
  url_lapor: 'alamat halaman Lapor Masalah',
  data_penanya: 'data penanya: arahan login untuk tamu atau pesanan user (lihat di bawah)',
  url_login: 'alamat halaman login',
  nama_pelanggan: 'nama user',
  pesanan: 'daftar pesanan terakhir user (tanpa email, password, PIN)',
  url_pesanan: 'alamat halaman Pesanan Saya',
}

const KNOWLEDGE: { name: 'knowledge_convert' | 'knowledge_qa' | 'asker_guest' | 'asker_user'; label: string; hint: string; rows: number }[] = [
  { name: 'asker_guest', label: 'Data penanya: tamu', hint: 'Mengisi {data_penanya} bila penanya belum login, termasuk arahan untuk login saat menanyakan pesanan.', rows: 4 },
  { name: 'asker_user', label: 'Data penanya: user login', hint: 'Mengisi {data_penanya} bila penanya sudah login. Jumlah pesanan diatur di Pengaturan lanjutan.', rows: 7 },
  { name: 'knowledge_convert', label: 'Perintah merapikan pengetahuan', hint: 'Dikirim ke model vision bersama lampiran saat entri di menu Pengetahuan dirapikan.', rows: 12 },
  { name: 'knowledge_qa', label: 'Tambahan perintah tanya-jawab', hint: 'Ditambahkan di akhir perintah di atas bila saklar "Buat tanya-jawab" menyala.', rows: 4 },
]

const SINGLE: { name: 'test_question' | 'vision_test'; label: string; hint: string }[] = [
  { name: 'test_question', label: 'Pertanyaan Tes chat', hint: 'Dikirim saat Tes chat di menu Koneksi. Tes lulus bila jawaban menyebut nama asisten, jadi sebaiknya menanyakan identitas.' },
  { name: 'vision_test', label: 'Perintah Tes vision', hint: 'Dikirim bersama gambar angka acak saat Tes vision. Tes lulus bila angkanya disebut.' },
]

export function TemplatesForm({ templates, defaults, placeholders, behavior }: Props) {
  const form = useForm<Templates>({ ...templates })
  const errors = form.errors as Record<string, string | undefined>

  const set = (name: TemplateName, value: string) => {
    form.clearErrors(name)
    form.setData(name, value)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.put('/admin/ai/template', { preserveScroll: true })
  }

  const [preview, setPreview] = useState<{ prompt: string; tokens: number } | null>(null)
  const [previewError, setPreviewError] = useState<string | null>(null)
  const [previewing, setPreviewing] = useState(false)

  const openPreview = async () => {
    setPreviewing(true)
    setPreviewError(null)
    const res = await postJson<{ prompt?: string; tokens?: number }>('/admin/ai/pratinjau', { template: form.data.system, ...behavior })
    setPreviewing(false)
    if (res.failed || res.prompt === undefined) {
      setPreviewError(res.message ?? 'Gagal membuat pratinjau.')
      return
    }
    setPreview({ prompt: res.prompt, tokens: res.tokens ?? 0 })
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4 rounded-lg border p-4'>
      <div>
        <h2 className='font-semibold'>Template prompt</h2>
        <p className='text-sm text-muted-foreground'>
          Teks yang benar-benar dikirim ke AI. Chat pelanggan, Tes chat, dan Uji coba memakai template system yang sama.
        </p>
      </div>

      <div className='space-y-2'>
        <div className='flex items-center justify-between gap-2'>
          <Label htmlFor='system'>Template system</Label>
          <Button type='button' variant='link' size='sm' className='h-auto p-0' onClick={() => set('system', defaults.system)}>
            Pakai bawaan
          </Button>
        </div>
        <Textarea id='system' rows={16} className='text-xs' value={form.data.system} onChange={(e) => set('system', e.target.value)} />
        <div className='flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground'>
          {placeholders.system.map((p) => (
            <span key={p}>
              <span className='text-foreground'>{`{${p}}`}</span> {PLACEHOLDER_HINTS[p] ?? ''}
            </span>
          ))}
        </div>
        <FieldError message={errors.system} />
        <div className='flex items-center gap-3'>
          <Button type='button' variant='outline' size='sm' disabled={previewing} onClick={openPreview}>
            {previewing ? <Loader2 className='size-4 animate-spin' /> : 'Pratinjau'}
          </Button>
          <p className='text-xs text-muted-foreground'>Menampilkan hasil akhir dengan penanda terisi, memakai isian form saat ini (termasuk yang belum disimpan).</p>
        </div>
        {previewError && <p className='text-sm text-destructive'>{previewError}</p>}
      </div>

      {SINGLE.map((t) => (
        <div key={t.name} className='space-y-2'>
          <div className='flex items-center justify-between gap-2'>
            <Label htmlFor={t.name}>{t.label}</Label>
            <Button type='button' variant='link' size='sm' className='h-auto p-0' onClick={() => set(t.name, defaults[t.name])}>
              Pakai bawaan
            </Button>
          </div>
          <Input id={t.name} value={form.data[t.name]} onChange={(e) => set(t.name, e.target.value)} />
          <p className='text-xs text-muted-foreground'>{t.hint}</p>
          <FieldError message={errors[t.name]} />
        </div>
      ))}

      {KNOWLEDGE.map((t) => (
        <div key={t.name} className='space-y-2'>
          <div className='flex items-center justify-between gap-2'>
            <Label htmlFor={t.name}>{t.label}</Label>
            <Button type='button' variant='link' size='sm' className='h-auto p-0' onClick={() => set(t.name, defaults[t.name])}>
              Pakai bawaan
            </Button>
          </div>
          <Textarea id={t.name} rows={t.rows} className='text-xs' value={form.data[t.name]} onChange={(e) => set(t.name, e.target.value)} />
          <p className='text-xs text-muted-foreground'>
            {t.hint}
            {placeholders[t.name].length > 0 && <> Penanda: {placeholders[t.name].map((p) => <span key={p} className='me-2'><span className='text-foreground'>{`{${p}}`}</span> {PLACEHOLDER_HINTS[p] ?? ''}</span>)}</>}
          </p>
          <FieldError message={errors[t.name]} />
        </div>
      ))}

      <Button type='submit' disabled={form.processing}>
        {form.processing ? 'Menyimpan...' : 'Simpan template'}
      </Button>

      <Dialog open={preview !== null} onOpenChange={(open) => !open && setPreview(null)}>
        <DialogContent className='sm:max-w-3xl'>
          <DialogHeader>
            <DialogTitle>Pratinjau instruksi system</DialogTitle>
            <DialogDescription>Perkiraan {preview?.tokens.toLocaleString('id-ID')} token, belum termasuk beban tersembunyi dari penyedia model.</DialogDescription>
          </DialogHeader>
          <pre className='max-h-[60vh] overflow-auto rounded-md bg-muted/50 p-3 text-xs whitespace-pre-wrap'>{preview?.prompt}</pre>
        </DialogContent>
      </Dialog>
    </form>
  )
}
