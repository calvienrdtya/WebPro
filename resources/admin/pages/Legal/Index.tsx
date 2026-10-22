import { type FormEvent, useMemo, useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { ExternalLink } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { cn } from '@/lib/utils'
import { MARKDOWN_CLASS, renderMarkdown } from '@/features/ai/markdown'
import { FieldError } from '@/features/products/field-error'

type Page = { key: string; title: string; url: string; body: string; default_body: string; is_default: boolean; updated_at: string | null }
type Props = { pages: Page[]; placeholders: Record<string, string> }

export default function LegalIndex({ pages, placeholders }: Props) {
  return (
    <>
      <Head title='Halaman Legal' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Halaman Legal</h1>
          <p className='text-sm text-muted-foreground'>
            Syarat & Ketentuan dan Kebijakan Privasi yang tampil di toko, di footer, dan di halaman daftar akun. Draf bawaan disusun dari kebijakan toko, tetapi bukan nasihat hukum: tinjau dan sesuaikan sebelum toko dibuka.
          </p>
        </div>

        <Tabs defaultValue={pages[0]?.key} className='max-w-5xl'>
          <TabsList>
            {pages.map((p) => (
              <TabsTrigger key={p.key} value={p.key}>{p.title}</TabsTrigger>
            ))}
          </TabsList>
          {pages.map((p) => (
            <TabsContent key={p.key} value={p.key} className='mt-4'>
              <PageForm page={p} placeholders={placeholders} />
            </TabsContent>
          ))}
        </Tabs>
      </Main>
    </>
  )
}

function PageForm({ page, placeholders }: { page: Page; placeholders: Record<string, string> }) {
  const form = useForm({ body: page.body })
  const [view, setView] = useState<'edit' | 'preview'>('edit')
  const preview = useMemo(
    () => (view === 'preview' ? renderMarkdown(form.data.body.replace(/\{(nama_toko|email|situs|masa_log_aktivitas|masa_log_sistem)\}/g, (_, k: string) => placeholders[k] ?? `{${k}}`)) : ''),
    [view, form.data.body, placeholders],
  )

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.put(`/admin/halaman-legal/${page.key}`, { preserveScroll: true })
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4 rounded-lg border p-4'>
      <div className='flex flex-wrap items-center justify-between gap-2'>
        <p className='text-sm text-muted-foreground'>
          {page.updated_at ? `Terakhir disimpan ${new Date(page.updated_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}.` : 'Belum pernah disimpan.'}{' '}
          {page.is_default ? 'Memakai draf bawaan.' : 'Memakai isi buatan Anda.'}
        </p>
        <div className='flex items-center gap-2'>
          <div className='flex rounded-md border p-0.5 text-xs'>
            {(['edit', 'preview'] as const).map((v) => (
              <button key={v} type='button' className={cn('rounded px-2 py-0.5', view === v ? 'bg-muted font-medium' : 'text-muted-foreground')} onClick={() => setView(v)}>
                {v === 'edit' ? 'Ubah' : 'Pratinjau'}
              </button>
            ))}
          </div>
          <Button type='button' variant='outline' size='sm' asChild>
            <a href={page.url} target='_blank' rel='noreferrer'>
              <ExternalLink className='size-4' /> Lihat halaman
            </a>
          </Button>
        </div>
      </div>

      <div className='space-y-2'>
        <div className='flex items-center justify-between gap-2'>
          <Label htmlFor={`body-${page.key}`}>Isi (Markdown)</Label>
          <Button type='button' variant='link' size='sm' className='h-auto p-0' onClick={() => form.setData('body', page.default_body)}>
            Pakai draf bawaan
          </Button>
        </div>
        {view === 'edit' ? (
          <Textarea id={`body-${page.key}`} rows={26} value={form.data.body} onChange={(e) => { form.clearErrors('body'); form.setData('body', e.target.value) }} />
        ) : (
          <div className={cn('max-h-[70vh] overflow-y-auto rounded-md border p-4', MARKDOWN_CLASS)} dangerouslySetInnerHTML={{ __html: preview }} />
        )}
        <p className='text-xs text-muted-foreground'>
          Pakai <span className='text-foreground'>## Judul</span> untuk subjudul, tanda - untuk daftar, dan **teks** untuk tebal. Penanda {'{nama_toko}'}, {'{email}'}, dan {'{situs}'} otomatis diganti nama toko, email kontak (menu Tampilan Toko), dan alamat situs; {'{masa_log_aktivitas}'} dan {'{masa_log_sistem}'} diganti masa simpan log di Pengaturan.
        </p>
        <FieldError message={form.errors.body} />
      </div>

      <Button type='submit' disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button>
    </form>
  )
}
