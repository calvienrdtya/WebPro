import { type FormEvent } from 'react'
import { useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { FieldError } from '@/features/products/field-error'
import { type FaqForm, type FaqRow, type Option } from './types'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  categories: Option[]
  /** FAQ yang diubah; kosong berarti tambah baru. */
  faq?: FaqRow | null
  /** Kategori awal untuk FAQ baru (mengikuti tab yang sedang dibuka). */
  defaultCategory?: string
}

export function FaqFormDialog({ open, onOpenChange, ...rest }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-xl'>
        {/* Dipasang ulang tiap dibuka agar isiannya selalu segar. */}
        <FaqFormBody {...rest} onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}

function FaqFormBody({
  categories,
  faq,
  defaultCategory,
  onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
  const form = useForm<FaqForm>({
    category: faq?.category ?? defaultCategory ?? categories[0]?.value ?? '',
    question: faq?.question ?? '',
    answer: faq?.answer ?? '',
    is_active: faq?.is_active ?? true,
    show_on_home: faq?.show_on_home ?? false,
    show_on_product: faq?.show_on_product ?? false,
  })
  const { data, processing } = form
  const errors = form.errors as Record<string, string | undefined>

  const setText = (key: 'question' | 'answer', value: string) => {
    form.clearErrors(key)
    form.setData(key, value)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const opts = { preserveScroll: true, onSuccess: onClose }
    if (faq) form.put(`/admin/faq/${faq.id}`, opts)
    else form.post('/admin/faq', opts)
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4'>
      <DialogHeader>
        <DialogTitle>{faq ? 'Ubah FAQ' : 'Tambah FAQ'}</DialogTitle>
        <DialogDescription>
          Tampil di halaman FAQ toko. Centang beranda atau halaman produk untuk memunculkannya juga di sana.
        </DialogDescription>
      </DialogHeader>

      <div className='space-y-2'>
        <Label htmlFor='category'>Kategori</Label>
        <Select
          value={data.category}
          onValueChange={(v) => {
            form.clearErrors('category')
            form.setData('category', v)
          }}
        >
          <SelectTrigger id='category' className='w-full'>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {categories.map((c) => (
              <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <FieldError message={errors.category} />
      </div>

      <div className='space-y-2'>
        <Label htmlFor='question'>Pertanyaan</Label>
        <Input id='question' maxLength={200} value={data.question} onChange={(e) => setText('question', e.target.value)} placeholder='Berapa lama akun dikirim?' autoComplete='off' />
        <FieldError message={errors.question} />
      </div>

      <div className='space-y-2'>
        <Label htmlFor='answer'>Jawaban</Label>
        <Textarea id='answer' rows={5} maxLength={2000} value={data.answer} onChange={(e) => setText('answer', e.target.value)} placeholder='Tulis jawaban singkat dan jelas. Baris baru ikut tampil.' />
        <div className='flex items-start justify-between gap-2'>
          <FieldError message={errors.answer} />
          <p className='ms-auto shrink-0 text-xs tabular-nums text-muted-foreground'>{data.answer.length}/2000</p>
        </div>
      </div>

      <div className='space-y-2'>
        <SwitchRow id='is_active' label='Aktif' hint='Nonaktif = tidak tampil di toko.' checked={data.is_active} onChange={(v) => form.setData('is_active', v)} />
        <SwitchRow id='show_on_home' label='Tampil di beranda' hint='Muncul di bagian "Pertanyaan umum" pada beranda.' checked={data.show_on_home} onChange={(v) => form.setData('show_on_home', v)} />
        <SwitchRow id='show_on_product' label='Tampil di halaman produk' hint='Muncul di bagian "Pertanyaan umum" pada detail semua produk.' checked={data.show_on_product} onChange={(v) => form.setData('show_on_product', v)} />
      </div>

      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
        <Button type='submit' disabled={processing}>
          {processing ? 'Menyimpan...' : faq ? 'Simpan perubahan' : 'Tambah FAQ'}
        </Button>
      </DialogFooter>
    </form>
  )
}

function SwitchRow({ id, label, hint, checked, onChange }: { id: string; label: string; hint: string; checked: boolean; onChange: (v: boolean) => void }) {
  return (
    <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
      <div>
        <Label htmlFor={id}>{label}</Label>
        <p className='text-xs text-muted-foreground'>{hint}</p>
      </div>
      <Switch id={id} checked={checked} onCheckedChange={onChange} />
    </div>
  )
}
