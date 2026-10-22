import { type FormEvent, useEffect, useMemo, useState } from 'react'
import { router, useForm } from '@inertiajs/react'
import { FileText, Image as ImageIcon, RotateCcw, Undo2, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { FieldError } from '@/features/products/field-error'
import { type KnowledgeEntry, type KnowledgeLimits, formatSize } from './knowledge-types'
import { MARKDOWN_CLASS, renderMarkdown } from './markdown'

type Props = {
  /** null = tutup, 'new' = tambah, entri = tinjau/ubah. */
  target: KnowledgeEntry | 'new' | null
  onClose: () => void
  products: { id: number; name: string }[]
  limits: KnowledgeLimits
}

type FormData = {
  title: string
  raw_text: string
  body: string
  use_ai: boolean
  with_qa: boolean
  products: number[]
  attachments: File[]
  remove_attachments: number[]
}

const URL = '/admin/ai/pengetahuan'

export function KnowledgeDialog({ target, onClose, products, limits }: Props) {
  const entry = target && target !== 'new' ? target : null
  const open = target !== null

  const form = useForm<FormData>({ title: '', raw_text: '', body: '', use_ai: true, with_qa: true, products: [], attachments: [], remove_attachments: [] })
  const errors = form.errors as Record<string, string | undefined>
  const [view, setView] = useState<'edit' | 'preview'>('preview')

  useEffect(() => {
    if (!open) return
    form.clearErrors()
    form.setData({
      title: entry?.title ?? '',
      raw_text: entry?.raw_text ?? '',
      body: entry?.body ?? '',
      use_ai: entry?.use_ai ?? true,
      with_qa: entry?.with_qa ?? true,
      products: entry?.products.map((p) => p.id) ?? [],
      attachments: [],
      remove_attachments: [],
    })
    setView(entry?.body ? 'preview' : 'edit')
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, entry?.id, entry?.updated_at])

  const set = <K extends keyof FormData>(key: K, value: FormData[K]) => {
    form.clearErrors(key)
    form.setData((d) => ({ ...d, [key]: value }))
  }

  const attachmentError = errors.attachments ?? Object.entries(errors).find(([k]) => k.startsWith('attachments.'))?.[1]
  const preview = useMemo(() => (view === 'preview' ? renderMarkdown(form.data.body) : ''), [view, form.data.body])

  const send = (then?: () => void) => {
    const options = { forceFormData: true, preserveScroll: true, onSuccess: () => (then ? then() : onClose()) }
    if (entry) {
      form.transform((d) => ({ ...d, _method: 'put' }))
      form.post(`${URL}/${entry.id}`, options)
    } else {
      form.transform((d) => d)
      form.post(URL, options)
    }
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    send()
  }

  const saveAndApprove = () => send(() => router.patch(`${URL}/${entry!.id}/setujui`, {}, { preserveScroll: true, onSuccess: onClose }))
  const saveAndReprocess = () => send(() => router.post(`${URL}/${entry!.id}/proses`, {}, { preserveScroll: true, onSuccess: onClose }))

  const builtin = entry?.is_builtin ?? false
  const processing = entry?.status === 'processing'
  const keptAttachments = entry?.attachments ?? []

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className={cn('max-h-[92vh] overflow-y-auto', entry ? 'sm:max-w-5xl' : 'sm:max-w-2xl')}>
        <form onSubmit={submit} noValidate>
          <DialogHeader>
            <DialogTitle>{entry ? (builtin ? 'Ubah entri bawaan' : 'Tinjau entri') : 'Tambah pengetahuan'}</DialogTitle>
            <DialogDescription>
              {entry
                ? builtin
                  ? 'Entri bawaan ditulis langsung tanpa AI. Bisa diubah atau dinonaktifkan, tidak bisa dihapus.'
                  : 'Periksa hasil Markdown di kanan, koreksi bila perlu, lalu setujui agar dipakai AI.'
                : 'Tulis teks mentah dan/atau unggah gambar dan PDF. AI merapikannya menjadi Markdown yang Anda tinjau sebelum dipakai.'}
            </DialogDescription>
          </DialogHeader>

          {entry && (
            <div className='mt-3 flex flex-wrap items-center gap-2 text-sm'>
              <Badge variant='outline'>{entry.status_label}</Badge>
              {entry.status === 'ready' && <Badge variant={entry.is_active ? 'default' : 'secondary'}>{entry.is_active ? 'Aktif' : 'Nonaktif'}</Badge>}
              {entry.error && <span className='text-destructive'>{entry.error}</span>}
              {processing && <span className='text-muted-foreground'>Sedang dirapikan AI. Halaman diperbarui otomatis.</span>}
            </div>
          )}

          <div className={cn('mt-4 grid gap-6', entry && !builtin && 'lg:grid-cols-2')}>
            <div className='space-y-4'>
              <div className='space-y-2'>
                <Label htmlFor='kb_title'>Judul</Label>
                <Input id='kb_title' maxLength={150} value={form.data.title} onChange={(e) => set('title', e.target.value)} placeholder='Error Household Netflix' />
                <FieldError message={errors.title} />
              </div>

              {!builtin && (
                <>
                  {products.length > 0 && (
                    <div className='space-y-2'>
                      <Label>Produk terkait (opsional)</Label>
                      <div className='flex flex-wrap gap-x-4 gap-y-2 rounded-md border p-3'>
                        {products.map((p) => (
                          <label key={p.id} className='flex items-center gap-2 text-sm'>
                            <Checkbox
                              checked={form.data.products.includes(p.id)}
                              onCheckedChange={(v) => set('products', v === true ? [...form.data.products, p.id] : form.data.products.filter((id) => id !== p.id))}
                            />
                            {p.name}
                          </label>
                        ))}
                      </div>
                    </div>
                  )}

                  <div className='space-y-2'>
                    <Label htmlFor='kb_raw'>Teks</Label>
                    <Textarea id='kb_raw' rows={entry ? 8 : 10} value={form.data.raw_text} onChange={(e) => set('raw_text', e.target.value)} placeholder='Tulis apa adanya, misalnya langkah-langkah mengatasi error. Tidak perlu rapi.' />
                    <FieldError message={errors.raw_text} />
                  </div>

                  <div className='space-y-2'>
                    <Label htmlFor='kb_files'>Lampiran</Label>
                    {keptAttachments.length > 0 && (
                      <ul className='space-y-1'>
                        {keptAttachments.map((a) => {
                          const removed = form.data.remove_attachments.includes(a.id)
                          return (
                            <li key={a.id} className={cn('flex items-center gap-2 rounded-md border px-2 py-1 text-sm', removed && 'opacity-50 line-through')}>
                              {a.kind === 'pdf' ? <FileText className='size-4 shrink-0' /> : <ImageIcon className='size-4 shrink-0' />}
                              <a href={a.url} target='_blank' rel='noreferrer' className='min-w-0 flex-1 truncate hover:underline'>{a.name}</a>
                              <span className='shrink-0 text-xs text-muted-foreground'>{formatSize(a.size)}{a.pages ? `, ${a.pages} hal.` : ''}</span>
                              <Button
                                type='button'
                                variant='ghost'
                                size='icon'
                                className='size-7'
                                title={removed ? 'Batal hapus' : 'Hapus lampiran'}
                                aria-label={removed ? `Batal hapus ${a.name}` : `Hapus ${a.name}`}
                                onClick={() => set('remove_attachments', removed ? form.data.remove_attachments.filter((id) => id !== a.id) : [...form.data.remove_attachments, a.id])}
                              >
                                {removed ? <Undo2 /> : <X />}
                              </Button>
                            </li>
                          )
                        })}
                      </ul>
                    )}
                    {form.data.attachments.length > 0 && (
                      <ul className='space-y-1'>
                        {form.data.attachments.map((f, i) => (
                          <li key={`${f.name}-${i}`} className='flex items-center gap-2 rounded-md border border-dashed px-2 py-1 text-sm'>
                            {f.type === 'application/pdf' ? <FileText className='size-4 shrink-0' /> : <ImageIcon className='size-4 shrink-0' />}
                            <span className='min-w-0 flex-1 truncate'>{f.name}</span>
                            <span className='shrink-0 text-xs text-muted-foreground'>{formatSize(f.size)}, baru</span>
                            <Button type='button' variant='ghost' size='icon' className='size-7' aria-label={`Batal unggah ${f.name}`} onClick={() => set('attachments', form.data.attachments.filter((_, j) => j !== i))}>
                              <X />
                            </Button>
                          </li>
                        ))}
                      </ul>
                    )}
                    <Input
                      id='kb_files'
                      type='file'
                      multiple
                      accept='image/jpeg,image/png,image/webp,application/pdf'
                      onChange={(e) => {
                        set('attachments', [...form.data.attachments, ...Array.from(e.target.files ?? [])])
                        e.target.value = ''
                      }}
                    />
                    <p className='text-xs text-muted-foreground'>
                      Gambar JPG, PNG, atau WebP maksimal {limits.max_images} buah, {limits.image_mb} MB per gambar. PDF maksimal 1, {limits.pdf_mb} MB dan {limits.pdf_pages} halaman. Tangkapan layar error membantu AI mengenali screenshot dari pelanggan.
                    </p>
                    <FieldError message={attachmentError} />
                  </div>

                  <div className='space-y-2 rounded-lg border p-3'>
                    <div className='flex items-center justify-between gap-3'>
                      <div>
                        <Label htmlFor='kb_ai'>Rapikan dengan AI</Label>
                        <p className='text-xs text-muted-foreground'>Teks dan lampiran diubah menjadi Markdown oleh model vision. Matikan untuk memakai teks apa adanya.</p>
                      </div>
                      <Switch id='kb_ai' checked={form.data.use_ai} onCheckedChange={(v) => set('use_ai', v)} />
                    </div>
                    <div className='flex items-center justify-between gap-3'>
                      <div>
                        <Label htmlFor='kb_qa'>Buat tanya-jawab</Label>
                        <p className='text-xs text-muted-foreground'>AI menambahkan contoh pertanyaan pelanggan beserta jawabannya.</p>
                      </div>
                      <Switch id='kb_qa' checked={form.data.use_ai && form.data.with_qa} disabled={!form.data.use_ai} onCheckedChange={(v) => set('with_qa', v)} />
                    </div>
                  </div>
                </>
              )}

              {builtin && <MarkdownField form={form} view={view} setView={setView} preview={preview} error={errors.body} />}
            </div>

            {entry && !builtin && <MarkdownField form={form} view={view} setView={setView} preview={preview} error={errors.body} processing={processing} />}
          </div>

          <DialogFooter className='mt-6 flex-wrap gap-2'>
            <Button type='button' variant='outline' onClick={onClose}>
              Batal
            </Button>
            {entry && !builtin && entry.use_ai && (
              <Button type='button' variant='outline' disabled={form.processing || !form.data.use_ai} onClick={saveAndReprocess} title='Simpan bahan lalu rapikan ulang dengan AI. Isi Markdown sekarang akan diganti.'>
                <RotateCcw className='size-4' /> Simpan dan proses ulang
              </Button>
            )}
            <Button type='submit' variant={entry && entry.status !== 'ready' ? 'outline' : 'default'} disabled={form.processing}>
              {form.processing ? 'Menyimpan...' : entry ? 'Simpan' : form.data.use_ai ? 'Simpan dan rapikan' : 'Simpan'}
            </Button>
            {entry && entry.status !== 'ready' && (
              <Button type='button' disabled={form.processing || processing || form.data.body.trim() === ''} onClick={saveAndApprove}>
                Simpan dan setujui
              </Button>
            )}
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

type MarkdownFieldProps = {
  form: { data: FormData; setData: (key: 'body', value: string) => void; clearErrors: (key: 'body') => void }
  view: 'edit' | 'preview'
  setView: (v: 'edit' | 'preview') => void
  preview: string
  error?: string
  processing?: boolean
}

function MarkdownField({ form, view, setView, preview, error, processing }: MarkdownFieldProps) {
  return (
    <div className='space-y-2'>
      <div className='flex items-center justify-between gap-2'>
        <Label htmlFor='kb_body'>Hasil Markdown (yang dibaca AI)</Label>
        <div className='flex rounded-md border p-0.5 text-xs'>
          {(['preview', 'edit'] as const).map((v) => (
            <button key={v} type='button' className={cn('rounded px-2 py-0.5', view === v ? 'bg-muted font-medium' : 'text-muted-foreground')} onClick={() => setView(v)}>
              {v === 'preview' ? 'Pratinjau' : 'Ubah'}
            </button>
          ))}
        </div>
      </div>
      {view === 'edit' ? (
        <Textarea
          id='kb_body'
          rows={22}
          className='text-xs'
          value={form.data.body}
          disabled={processing}
          onChange={(e) => {
            form.clearErrors('body')
            form.setData('body', e.target.value)
          }}
          placeholder={processing ? 'Menunggu hasil AI...' : 'Isi Markdown'}
        />
      ) : (
        <div className={cn('min-h-40 max-h-[60vh] overflow-y-auto rounded-md border p-3', MARKDOWN_CLASS)}>
          {form.data.body.trim() ? (
            <div dangerouslySetInnerHTML={{ __html: preview }} />
          ) : (
            <p className='text-muted-foreground'>{processing ? 'Menunggu hasil AI...' : 'Belum ada isi.'}</p>
          )}
        </div>
      )}
      <FieldError message={error} />
    </div>
  )
}
