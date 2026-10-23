import { type FormEvent, useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import { formatNumber } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { FieldError } from '@/features/products/field-error'

type Props = {
  notifications: { email_enabled: boolean; email_to: string; types: { type: string; label: string; enabled: boolean }[] }
  qris: { url: string; custom: boolean }
  chat: {
    ai: boolean
    wa: boolean
    number: string | null
    messages: {
      product: string
      general: string
      max_length: number
      defaults: { product: string; general: string }
      placeholders: { product: string[]; general: string[] }
    }
  }
  renewal: { enabled: boolean; days: number; min_days: number; max_days: number }
  logs: { system_days: number; activity_days: number; min_days: number; max_system_days: number; max_activity_days: number }
  images: { quality: number; min: number; max: number; webp: boolean }
}

export default function Index({ notifications, qris, chat, renewal, logs, images }: Props) {
  const form = useForm({
    email_enabled: notifications.email_enabled,
    email_to: notifications.email_to,
    types: Object.fromEntries(notifications.types.map((t) => [t.type, t.enabled])) as Record<string, boolean>,
  })
  const errors = form.errors as Record<string, string | undefined>

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.put('/admin/pengaturan/notifikasi', { preserveScroll: true })
  }

  const messagesForm = useForm({ product: chat.messages.product, general: chat.messages.general })
  const submitMessages = (e: FormEvent) => {
    e.preventDefault()
    messagesForm.put('/admin/pengaturan/pesan-wa', { preserveScroll: true })
  }

  // Saklar pop up chat langsung tersimpan saat digeser (untuk mematikan cepat saat ada perbaikan atau deploy).
  const [chatBusy, setChatBusy] = useState<'chat_ai_enabled' | 'chat_wa_enabled' | null>(null)
  const toggleChat = (field: 'chat_ai_enabled' | 'chat_wa_enabled', value: boolean) => {
    setChatBusy(field)
    router.put('/admin/pengaturan/chat', { [field]: value }, { preserveScroll: true, onFinish: () => setChatBusy(null) })
  }

  const renewalForm = useForm({ enabled: renewal.enabled, days: String(renewal.days) })
  const submitRenewal = (e: FormEvent) => {
    e.preventDefault()
    renewalForm.put('/admin/pengaturan/pengingat', { preserveScroll: true })
  }

  const logsForm = useForm({ system_days: String(logs.system_days), activity_days: String(logs.activity_days) })
  const submitLogs = (e: FormEvent) => {
    e.preventDefault()
    logsForm.put('/admin/pengaturan/log', { preserveScroll: true })
  }

  const imagesForm = useForm({ quality: String(images.quality) })
  const submitImages = (e: FormEvent) => {
    e.preventDefault()
    imagesForm.put('/admin/pengaturan/gambar', { preserveScroll: true })
  }

  const qrisForm = useForm<{ qris: File | null }>({ qris: null })
  const qrisErrors = qrisForm.errors as Record<string, string | undefined>

  const submitQris = (e: FormEvent) => {
    e.preventDefault()
    qrisForm.post('/admin/pengaturan/qris', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => qrisForm.reset(),
    })
  }

  return (
    <>
      <Head title='Pengaturan' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Pengaturan</h1>
          <p className='text-sm text-muted-foreground'>Pengaturan toko. Biaya layanan kini punya menu sendiri (menu Katalog, Biaya Layanan).</p>
        </div>

        <form onSubmit={submit} noValidate className='max-w-2xl space-y-4 rounded-lg border p-4'>
          <div>
            <h2 className='font-semibold'>Notifikasi admin</h2>
            <p className='text-sm text-muted-foreground'>Beri tahu admin saat ada laporan baru, pembayaran yang menunggu verifikasi, dan kiriman bulanan yang tertunda karena stok kurang.</p>
          </div>

          <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
            <div>
              <Label htmlFor='email_enabled'>Email</Label>
              <p className='text-xs text-muted-foreground'>Kirim email untuk laporan baru, pembayaran menunggu verifikasi, dan stok kurang.</p>
            </div>
            <Switch
              id='email_enabled'
              checked={form.data.email_enabled}
              onCheckedChange={(v) => {
                form.clearErrors('email_to')
                form.setData('email_enabled', v)
              }}
            />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='email_to'>Email tujuan</Label>
            <Input
              id='email_to'
              type='email'
              value={form.data.email_to}
              onChange={(e) => {
                form.clearErrors('email_to')
                form.setData('email_to', e.target.value)
              }}
              placeholder='admin@tokokamu.com'
              autoComplete='off'
            />
            <FieldError message={errors.email_to} />
          </div>

          <div className='space-y-2 rounded-lg border px-3 py-2'>
            <p className='text-sm font-medium'>Jenis notifikasi</p>
            <p className='text-xs text-muted-foreground'>Berlaku bila saklar Email di atas menyala.</p>
            {notifications.types.map((t) => (
              <div key={t.type} className='flex items-center justify-between gap-3'>
                <Label htmlFor={`type_${t.type}`} className='font-normal'>{t.label}</Label>
                <Switch
                  id={`type_${t.type}`}
                  checked={form.data.types[t.type]}
                  disabled={!form.data.email_enabled}
                  onCheckedChange={(v) => form.setData('types', { ...form.data.types, [t.type]: v })}
                />
              </div>
            ))}
          </div>

          <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2 opacity-60'>
            <div>
              <Label>WhatsApp</Label>
              <p className='text-xs text-muted-foreground'>Kirim pesan WhatsApp setiap ada laporan baru.</p>
            </div>
            <Badge variant='secondary'>Segera</Badge>
          </div>

          <Button type='submit' disabled={form.processing}>
            {form.processing ? 'Menyimpan...' : 'Simpan'}
          </Button>
        </form>

        <form onSubmit={submitRenewal} noValidate className='mt-6 max-w-2xl space-y-4 rounded-lg border p-4'>
          <div>
            <h2 className='font-semibold'>Pengingat ke pelanggan</h2>
            <p className='text-sm text-muted-foreground'>
              Email pengingat perpanjangan dikirim sekali per pesanan, setiap hari pukul 09.00, bila akun terakhir pesanan akan habis. Tidak dikirim bila pelanggan sudah memesan produk yang sama lagi.
            </p>
          </div>

          <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
            <div>
              <Label htmlFor='renewal_enabled'>Email pengingat perpanjangan</Label>
              <p className='text-xs text-muted-foreground'>{renewalForm.data.enabled ? 'Menyala.' : 'Dimatikan, tidak ada pengingat yang dikirim.'}</p>
            </div>
            <Switch id='renewal_enabled' checked={renewalForm.data.enabled} onCheckedChange={(v) => renewalForm.setData('enabled', v)} />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='renewal_days'>Kirim berapa hari sebelum akun habis</Label>
            <Input
              id='renewal_days'
              type='number'
              min={renewal.min_days}
              max={renewal.max_days}
              inputMode='numeric'
              value={renewalForm.data.days}
              onChange={(e) => {
                renewalForm.clearErrors('days')
                renewalForm.setData('days', e.target.value)
              }}
              className='max-w-32'
            />
            <p className='text-xs text-muted-foreground'>Antara {renewal.min_days} dan {renewal.max_days} hari.</p>
            <FieldError message={renewalForm.errors.days} />
          </div>

          <Button type='submit' disabled={renewalForm.processing}>
            {renewalForm.processing ? 'Menyimpan...' : 'Simpan'}
          </Button>
        </form>

        <form id='masa-simpan-log' onSubmit={submitLogs} noValidate className='mt-6 max-w-2xl scroll-mt-20 space-y-4 rounded-lg border p-4'>
          <div>
            <h2 className='font-semibold'>Masa simpan log</h2>
            <p className='text-sm text-muted-foreground'>
              Log yang lebih lama dari ini dihapus otomatis setiap hari pukul 03.15. Menambah masa simpan berlaku untuk log yang masih ada; log yang sudah terhapus tidak kembali.
            </p>
          </div>

          <div className='grid gap-4 sm:grid-cols-2'>
            {([
              ['system_days', 'Log Sistem (hari)', logs.max_system_days, 'Error dan peringatan teknis, satu file per hari.'],
              ['activity_days', 'Log Aktivitas (hari)', logs.max_activity_days, 'Catatan siapa melakukan apa di admin dan toko.'],
            ] as const).map(([field, label, max, hint]) => (
              <div key={field} className='space-y-2'>
                <Label htmlFor={`logs_${field}`}>{label}</Label>
                <Input
                  id={`logs_${field}`}
                  type='number'
                  min={logs.min_days}
                  max={max}
                  inputMode='numeric'
                  value={logsForm.data[field]}
                  onChange={(e) => {
                    logsForm.clearErrors(field)
                    logsForm.setData(field, e.target.value)
                  }}
                  className='max-w-32'
                />
                <p className='text-xs text-muted-foreground'>{hint} Antara {logs.min_days} dan {formatNumber(max)} hari.</p>
                <FieldError message={logsForm.errors[field]} />
              </div>
            ))}
          </div>

          <Button type='submit' disabled={logsForm.processing}>
            {logsForm.processing ? 'Menyimpan...' : 'Simpan'}
          </Button>
        </form>

        <form onSubmit={submitImages} noValidate className='mt-6 max-w-2xl space-y-4 rounded-lg border p-4'>
          <div>
            <h2 className='font-semibold'>Gambar unggahan</h2>
            <p className='text-sm text-muted-foreground'>
              Gambar produk, hero, logo, dan logo kampus otomatis dikecilkan ke ukuran tampilnya lalu disimpan WebP. File asli selalu disimpan; tiap gambar bisa dikembalikan ke file asli dari halamannya masing-masing. QRIS dan favicon tidak diubah.
            </p>
          </div>
          {!images.webp && (
            <p className='rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300'>
              Server ini belum mendukung WebP (ekstensi GD). Gambar disimpan apa adanya sampai hosting mengaktifkannya.
            </p>
          )}
          <div className='space-y-2'>
            <Label htmlFor='image_quality'>Kualitas WebP</Label>
            <Input
              id='image_quality'
              type='number'
              min={images.min}
              max={images.max}
              inputMode='numeric'
              value={imagesForm.data.quality}
              onChange={(e) => {
                imagesForm.clearErrors('quality')
                imagesForm.setData('quality', e.target.value)
              }}
              className='max-w-32'
            />
            <p className='text-xs text-muted-foreground'>
              Antara {images.min} dan {images.max}. Disarankan 90: praktis sama dengan aslinya dengan ukuran sekitar 10 kali lebih kecil. Berlaku untuk unggahan berikutnya dan saat menekan Optimalkan.
            </p>
            <FieldError message={imagesForm.errors.quality} />
          </div>
          <Button type='submit' disabled={imagesForm.processing}>
            {imagesForm.processing ? 'Menyimpan...' : 'Simpan'}
          </Button>
        </form>

        <section className='mt-6 max-w-2xl space-y-4 rounded-lg border p-4'>
          <div>
            <h2 className='font-semibold'>Pop up chat</h2>
            <p className='text-sm text-muted-foreground'>
              Dua kartu mengambang di pojok kanan bawah semua halaman toko. Matikan yang sedang diperbaiki atau di-deploy, lalu nyalakan lagi setelah beres. Perubahan langsung tersimpan dan berlaku di halaman toko berikutnya yang dibuka pengunjung.
            </p>
          </div>

          <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
            <div>
              <Label htmlFor='chat_ai_enabled'>Pop up Tanya AI</Label>
              <p className='text-xs text-muted-foreground'>
                Asisten jawaban otomatis seputar produk, order, pembayaran, dan garansi. {chat.ai ? 'Sedang tampil di toko.' : 'Sedang dimatikan.'}
              </p>
            </div>
            <Switch id='chat_ai_enabled' checked={chat.ai} disabled={chatBusy !== null} onCheckedChange={(v) => toggleChat('chat_ai_enabled', v)} />
          </div>

          <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
            <div>
              <Label htmlFor='chat_wa_enabled'>Pop up WhatsApp</Label>
              <p className='text-xs text-muted-foreground'>
                Tombol chat langsung ke WhatsApp admin. {!chat.number ? 'Belum bisa tampil: nomor belum diisi.' : chat.wa ? 'Sedang tampil di toko.' : 'Sedang dimatikan.'}
              </p>
            </div>
            <Switch id='chat_wa_enabled' checked={chat.wa} disabled={chatBusy !== null || !chat.number} onCheckedChange={(v) => toggleChat('chat_wa_enabled', v)} />
          </div>

          <div className='rounded-lg bg-muted/50 px-3 py-2 text-sm'>
            {chat.number ? (
              <>Nomor WhatsApp: <span className='font-medium'>+{chat.number}</span>. Diatur lewat <span>ADMIN_WHATSAPP_NUMBER</span> di file .env. Tautan WhatsApp lain di toko (beranda, footer, FAQ, dan lainnya) tidak ikut mati.</>
            ) : (
              <span className='text-destructive'>Nomor WhatsApp belum diisi. Isi <span>ADMIN_WHATSAPP_NUMBER</span> di file .env agar pop up WhatsApp dan tautan WhatsApp berfungsi.</span>
            )}
          </div>
        </section>

        <form onSubmit={submitMessages} noValidate className='mt-6 max-w-2xl space-y-4 rounded-lg border p-4'>
          <div>
            <h2 className='font-semibold'>Teks pembuka chat WhatsApp</h2>
            <p className='text-sm text-muted-foreground'>
              Teks yang sudah terisi saat pelanggan membuka chat WhatsApp admin. Pelanggan masih bisa mengubahnya sebelum mengirim.
            </p>
          </div>
          {(['product', 'general'] as const).map((type) => (
            <div key={type} className='space-y-2'>
              <Label htmlFor={`wa_message_${type}`}>
                {type === 'product' ? 'Tombol WhatsApp di produk' : 'Pop up WhatsApp'}
              </Label>
              <Textarea
                id={`wa_message_${type}`}
                rows={2}
                maxLength={chat.messages.max_length}
                value={messagesForm.data[type]}
                onChange={(e) => messagesForm.setData(type, e.target.value)}
              />
              <p className='text-xs text-muted-foreground'>
                Penanda: {chat.messages.placeholders[type].map((p) => `{${p}}`).join(', ')}.
                {type === 'product' ? ' Contoh: untuk Netflix, {produk} menjadi "Netflix Premium".' : ''}{' '}
                <button
                  type='button'
                  className='underline'
                  onClick={() => messagesForm.setData(type, chat.messages.defaults[type])}
                >
                  Pakai teks bawaan
                </button>
              </p>
              <FieldError message={messagesForm.errors[type]} />
            </div>
          ))}
          <Button type='submit' disabled={messagesForm.processing}>
            {messagesForm.processing ? 'Menyimpan...' : 'Simpan'}
          </Button>
        </form>

        <form onSubmit={submitQris} noValidate className='mt-6 max-w-2xl space-y-4 rounded-lg border p-4'>
          <div>
            <h2 className='font-semibold'>QRIS pembayaran</h2>
            <p className='text-sm text-muted-foreground'>
              Gambar QRIS yang tampil di halaman bayar pelanggan. Tanpa unggahan, dipakai gambar bawaan.
            </p>
          </div>

          <div className='flex items-start gap-4'>
            <img src={qris.url} alt='QRIS saat ini' className='size-32 shrink-0 rounded-lg border object-contain' />
            <div className='min-w-0 flex-1 space-y-2'>
              <Label htmlFor='qris'>Unggah gambar baru</Label>
              <Input
                id='qris'
                type='file'
                accept='image/jpeg,image/png,image/webp'
                onChange={(e) => {
                  qrisForm.clearErrors('qris')
                  qrisForm.setData('qris', e.target.files?.[0] ?? null)
                }}
              />
              <FieldError message={qrisErrors.qris} />
              <p className='text-xs text-muted-foreground'>JPG, PNG, atau WebP, maksimal 2 MB. {qris.custom ? 'Sedang memakai gambar unggahan.' : 'Sedang memakai gambar bawaan.'}</p>
            </div>
          </div>

          <div className='flex gap-2'>
            <Button type='submit' disabled={qrisForm.processing || !qrisForm.data.qris}>
              {qrisForm.processing ? 'Mengunggah...' : 'Simpan'}
            </Button>
            {qris.custom && (
              <Button type='button' variant='outline' onClick={() => router.delete('/admin/pengaturan/qris', { preserveScroll: true })}>
                Pakai gambar bawaan
              </Button>
            )}
          </div>
        </form>
      </Main>
    </>
  )
}
