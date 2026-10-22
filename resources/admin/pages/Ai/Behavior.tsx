import { type FormEvent } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { type TemplateName, type Templates, TemplatesForm } from '@/features/ai/templates-form'
import { FieldError } from '@/features/products/field-error'

type Behavior = {
  name: string
  instructions: string
  fallback: string
  max_tokens: number
  guest_messages: number
  guest_images: number
  user_messages: number
  user_images: number
  greeting: string
  limit_message: string
  limit_message_guest: string
  limit_image_message: string
}

type TextField = 'greeting' | 'limit_message' | 'limit_message_guest' | 'limit_image_message'

type Props = {
  behavior: Behavior
  templates: Templates
  defaults: { instructions: string; fallback: string; templates: Templates } & Record<TextField, string>
  placeholders: Record<TemplateName, string[]>
}

const LIMITS: { field: keyof Behavior; label: string }[] = [
  { field: 'guest_messages', label: 'Pesan per hari' },
  { field: 'guest_images', label: 'Gambar per hari' },
  { field: 'user_messages', label: 'Pesan per hari' },
  { field: 'user_images', label: 'Gambar per hari' },
]

export default function BehaviorPage({ behavior, templates, defaults, placeholders }: Props) {
  const form = useForm({
    name: behavior.name,
    instructions: behavior.instructions,
    fallback: behavior.fallback,
    max_tokens: String(behavior.max_tokens),
    guest_messages: String(behavior.guest_messages),
    guest_images: String(behavior.guest_images),
    user_messages: String(behavior.user_messages),
    user_images: String(behavior.user_images),
    greeting: behavior.greeting,
    limit_message: behavior.limit_message,
    limit_message_guest: behavior.limit_message_guest,
    limit_image_message: behavior.limit_image_message,
  })
  const errors = form.errors as Record<string, string | undefined>

  const set = (field: keyof typeof form.data, value: string) => {
    form.clearErrors(field)
    form.setData(field, value)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.put('/admin/ai/perilaku', { preserveScroll: true })
  }

  const numberInput = (field: keyof typeof form.data, label: string) => (
    <div className='space-y-2'>
      <Label htmlFor={field}>{label}</Label>
      <Input id={field} inputMode='numeric' value={form.data[field]} onChange={(e) => set(field, e.target.value)} />
      <FieldError message={errors[field]} />
    </div>
  )

  const textArea = (field: TextField, label: string, hint: string) => (
    <div className='space-y-2'>
      <div className='flex items-center justify-between gap-2'>
        <Label htmlFor={field}>{label}</Label>
        <Button type='button' variant='link' size='sm' className='h-auto p-0' onClick={() => set(field, defaults[field])}>
          Pakai bawaan
        </Button>
      </div>
      <Textarea id={field} rows={2} value={form.data[field]} onChange={(e) => set(field, e.target.value)} />
      <p className='text-xs text-muted-foreground'>{hint}</p>
      <FieldError message={errors[field]} />
    </div>
  )

  return (
    <>
      <Head title='Perilaku AI' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Perilaku AI</h1>
          <p className='text-sm text-muted-foreground'>Identitas, gaya menjawab, dan batas pemakaian Tanya AI di toko.</p>
        </div>

        <form onSubmit={submit} noValidate className='max-w-2xl space-y-6'>
          <section className='space-y-4 rounded-lg border p-4'>
            <h2 className='font-semibold'>Identitas dan gaya</h2>

            <div className='space-y-2'>
              <Label htmlFor='name'>Nama asisten</Label>
              <Input id='name' value={form.data.name} onChange={(e) => set('name', e.target.value)} maxLength={60} />
              <FieldError message={errors.name} />
            </div>

            <div className='space-y-2'>
              <div className='flex items-center justify-between gap-2'>
                <Label htmlFor='instructions'>Instruksi</Label>
                <Button type='button' variant='link' size='sm' className='h-auto p-0' onClick={() => set('instructions', defaults.instructions)}>
                  Pakai instruksi bawaan
                </Button>
              </div>
              <Textarea id='instructions' rows={10} value={form.data.instructions} onChange={(e) => set('instructions', e.target.value)} />
              <p className='text-xs text-muted-foreground'>
                Aturan dan gaya bahasa asisten, ditulis bebas. Data toko (produk, harga, FAQ, dan lainnya) ditambahkan otomatis dari menu Pengetahuan, tidak perlu ditulis di sini. {form.data.instructions.length}/5000
              </p>
              <FieldError message={errors.instructions} />
            </div>

            <div className='space-y-2'>
              <div className='flex items-center justify-between gap-2'>
                <Label htmlFor='fallback'>Pesan saat tidak tahu</Label>
                <Button type='button' variant='link' size='sm' className='h-auto p-0' onClick={() => set('fallback', defaults.fallback)}>
                  Pakai pesan bawaan
                </Button>
              </div>
              <Textarea id='fallback' rows={3} value={form.data.fallback} onChange={(e) => set('fallback', e.target.value)} />
              <p className='text-xs text-muted-foreground'>Dipakai bila jawaban tidak ada di pengetahuan atau AI sedang gagal. Tombol WhatsApp ditampilkan bersamanya.</p>
              <FieldError message={errors.fallback} />
            </div>

            {textArea('greeting', 'Sapaan pembuka', 'Pesan pertama di widget Tanya AI toko. Penanda {nama} = nama asisten.')}

            <div className='max-w-xs space-y-2'>
              <Label htmlFor='max_tokens'>Batas panjang jawaban (token)</Label>
              <Input id='max_tokens' inputMode='numeric' value={form.data.max_tokens} onChange={(e) => set('max_tokens', e.target.value)} />
              <p className='text-xs text-muted-foreground'>100 sampai 4000. Sekitar 600 token cukup untuk 3 sampai 4 paragraf pendek.</p>
              <FieldError message={errors.max_tokens} />
            </div>
          </section>

          <section className='space-y-4 rounded-lg border p-4'>
            <div>
              <h2 className='font-semibold'>Batas harian</h2>
              <p className='text-sm text-muted-foreground'>Mencegah penyalahgunaan dan biaya berlebih. Isi 0 untuk menutup fitur itu. Dihitung ulang setiap tengah malam.</p>
            </div>

            <div className='grid gap-4 sm:grid-cols-2'>
              <div className='space-y-3 rounded-lg border p-3'>
                <p className='text-sm font-medium'>Tamu (belum masuk, dihitung per IP)</p>
                {LIMITS.slice(0, 2).map((l) => numberInput(l.field, l.label))}
              </div>
              <div className='space-y-3 rounded-lg border p-3'>
                <p className='text-sm font-medium'>User yang sudah masuk</p>
                {LIMITS.slice(2).map((l) => numberInput(l.field, l.label))}
              </div>
            </div>

            {textArea('limit_message_guest', 'Pesan saat batas tamu habis', 'Penanda {batas} = jumlah pesan per hari, {url_login} = halaman login (tombol Login muncul otomatis).')}
            {textArea('limit_message', 'Pesan saat batas user habis', 'Penanda {batas} = jumlah pesan per hari. Tombol WhatsApp muncul otomatis.')}
            {textArea('limit_image_message', 'Pesan saat batas gambar habis', 'Penanda {batas} = jumlah gambar per hari.')}
          </section>

          <Button type='submit' disabled={form.processing}>
            {form.processing ? 'Menyimpan...' : 'Simpan'}
          </Button>
        </form>

        <div className='mt-6 max-w-2xl'>
          <TemplatesForm
            templates={templates}
            defaults={defaults.templates}
            placeholders={placeholders}
            behavior={{ name: form.data.name, instructions: form.data.instructions, fallback: form.data.fallback }}
          />
        </div>
      </Main>
    </>
  )
}
