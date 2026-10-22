import { type FormEvent, useEffect, useMemo, useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react'
import { formatNumber } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { FieldError } from '@/features/products/field-error'
import { ImageOptimizeInfo, type ImageInfo } from '@/features/images/image-info'

type Image = { url: string; custom: boolean; info: ImageInfo }
type Social = { platform: string; url: string; active: boolean; label: string; icon: string; color: string; managed: boolean }

type Props = {
  texts: Record<'name' | 'tagline' | 'email' | 'footer_note' | 'hero_title' | 'hero_subtitle' | 'hero_badge' | 'hero_alt' | 'campus_title', string>
  flags: Record<'stat_orders' | 'stat_rating' | 'campus_enabled', boolean>
  images: Record<'logo' | 'favicon' | 'hero_image' | 'og_image', Image>
  stats: { orders: number; customers: number; rating: number; reviews: number }
  campuses: { name: string; url: string; info: ImageInfo }[]
  socials: Social[]
  limits: { logo_kb: number; image_kb: number }
  reasons: Reasons
  reasonIcons: { key: string; label: string; svg: string }[]
}

type ReasonItem = { icon: string; title: string; text: string }
type Reasons = { enabled: boolean; badge: string; title: string; subtitle: string; items: ReasonItem[] }

const BASE = '/admin/tampilan'
// Ikon merek sosial media (sama dengan toko).
const FA_URL = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'
const sizeLabel = (kb: number) => (kb >= 1024 ? `${kb / 1024} MB` : `${kb} KB`)

export default function Appearance(props: Props) {
  useEffect(() => {
    if (!document.querySelector(`link[href="${FA_URL}"]`)) {
      const link = document.createElement('link')
      link.rel = 'stylesheet'
      link.href = FA_URL
      document.head.appendChild(link)
    }
  }, [])

  // Ikon tab admin ikut berganti begitu favicon baru tersimpan (tanpa muat ulang halaman).
  useEffect(() => {
    document.querySelectorAll<HTMLLinkElement>('link[rel="icon"]').forEach((link) => (link.href = props.images.favicon.url))
  }, [props.images.favicon.url])

  return (
    <>
      <Head title='Tampilan Toko' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Tampilan Toko</h1>
          <p className='text-sm text-muted-foreground'>Identitas, beranda, dan sosial media yang tampil di toko. Perubahan langsung berlaku di halaman berikutnya yang dibuka pengunjung.</p>
        </div>

        <Tabs defaultValue='identitas' className='max-w-3xl'>
          <TabsList>
            <TabsTrigger value='identitas'>Identitas</TabsTrigger>
            <TabsTrigger value='beranda'>Beranda</TabsTrigger>
            <TabsTrigger value='sosmed'>Sosial media</TabsTrigger>
          </TabsList>
          <TabsContent value='identitas' className='mt-4'>
            <IdentityForm {...props} />
          </TabsContent>
          <TabsContent value='beranda' className='mt-4 space-y-6'>
            <HomeForm {...props} />
            <ReasonsForm reasons={props.reasons} icons={props.reasonIcons} />
            <CampusManager campuses={props.campuses} limitKb={props.limits.logo_kb} />
          </TabsContent>
          <TabsContent value='sosmed' className='mt-4'>
            <SocialForm socials={props.socials} />
          </TabsContent>
        </Tabs>
      </Main>
    </>
  )
}

function ImageField({ id, label, hint, image, file, onFile, remove, onRemove, accept, error, preview = 'h-16', inputKey = 0 }: {
  id: string; label: string; hint: string; image: Image; file: File | null; onFile: (f: File | null) => void; inputKey?: number
  remove: boolean; onRemove: (v: boolean) => void; accept: string; error?: string; preview?: string
}) {
  const src = useMemo(() => (file ? URL.createObjectURL(file) : image.url), [file, image.url])
  useEffect(() => () => { if (file) URL.revokeObjectURL(src) }, [file, src])
  return (
    <div className='space-y-2'>
      <Label htmlFor={id}>{label}</Label>
      <div className='flex items-start gap-4'>
        <div className='flex shrink-0 items-center justify-center rounded-lg border bg-muted/40 p-2'>
          <img src={src} alt='' className={`${preview} w-auto max-w-56 object-contain ${remove ? 'opacity-30' : ''}`} />
        </div>
        <div className='min-w-0 flex-1 space-y-2'>
          <Input key={inputKey} id={id} type='file' accept={accept} onChange={(e) => onFile(e.target.files?.[0] ?? null)} />
          <p className='text-xs text-muted-foreground'>{hint} {image.custom ? 'Sedang memakai unggahan.' : 'Sedang memakai gambar bawaan.'}</p>
          {!file && !remove && image.info && ['logo', 'hero_image', 'og_image'].includes(id) && (
            <ImageOptimizeInfo info={image.info} url={`${BASE}/gambar/${id}`} />
          )}
          {image.custom && !file && (
            <label className='flex items-center gap-2 text-xs text-muted-foreground'>
              <input type='checkbox' checked={remove} onChange={(e) => onRemove(e.target.checked)} /> Kembalikan ke gambar bawaan
            </label>
          )}
          <FieldError message={error} />
        </div>
      </div>
    </div>
  )
}

function IdentityForm({ texts, images, limits }: Props) {
  const form = useForm({
    name: texts.name, tagline: texts.tagline, email: texts.email, footer_note: texts.footer_note,
    logo: null as File | null, favicon: null as File | null, remove_logo: false, remove_favicon: false,
  })
  const errors = form.errors as Record<string, string | undefined>

  // Kolom file dikosongkan setelah tersimpan supaya tidak terlihat seperti belum diunggah.
  const [inputKey, setInputKey] = useState(0)
  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.post(`${BASE}/identitas`, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        form.setData((d) => ({ ...d, logo: null, favicon: null, remove_logo: false, remove_favicon: false }))
        setInputKey((k) => k + 1)
      },
    })
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-5 rounded-lg border p-4'>
      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='name'>Nama toko</Label>
          <Input id='name' maxLength={60} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
          <p className='text-xs text-muted-foreground'>Dipakai di footer, teks alt logo, dan data SEO.</p>
          <FieldError message={errors.name} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='email'>Email kontak</Label>
          <Input id='email' type='email' value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder='halo@tokokamu.com' />
          <p className='text-xs text-muted-foreground'>Tampil di footer. Kosongkan untuk menyembunyikan.</p>
          <FieldError message={errors.email} />
        </div>
      </div>
      <div className='space-y-2'>
        <Label htmlFor='tagline'>Deskripsi singkat (footer)</Label>
        <Textarea id='tagline' rows={2} maxLength={200} value={form.data.tagline} onChange={(e) => form.setData('tagline', e.target.value)} />
        <FieldError message={errors.tagline} />
      </div>
      <div className='space-y-2'>
        <Label htmlFor='footer_note'>Catatan kecil di bawah footer</Label>
        <Input id='footer_note' maxLength={100} value={form.data.footer_note} onChange={(e) => form.setData('footer_note', e.target.value)} />
        <FieldError message={errors.footer_note} />
      </div>
      <ImageField
        id='logo' label='Logo' image={images.logo} file={form.data.logo} onFile={(f) => form.setData('logo', f)}
        remove={form.data.remove_logo} onRemove={(v) => form.setData('remove_logo', v)} accept='image/png,image/webp,image/jpeg'
        hint={`PNG (latar transparan disarankan), WebP, atau JPG, maksimal ${sizeLabel(limits.logo_kb)}. SVG tidak diterima. Tampil di navbar dan halaman lupa sandi.`}
        error={errors.logo} inputKey={inputKey}
      />
      <ImageField
        id='favicon' label='Favicon' image={images.favicon} file={form.data.favicon} onFile={(f) => form.setData('favicon', f)}
        remove={form.data.remove_favicon} onRemove={(v) => form.setData('remove_favicon', v)} accept='image/png,image/x-icon,.ico' preview='h-10'
        hint={`PNG persegi (mis. 512 x 512) atau ICO, maksimal ${sizeLabel(limits.logo_kb)}. Ikon kecil di tab browser.`}
        error={errors.favicon} inputKey={inputKey}
      />
      <Button type='submit' disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button>
    </form>
  )
}

function HomeForm({ texts, flags, images, stats, limits }: Props) {
  const form = useForm({
    hero_title: texts.hero_title, hero_subtitle: texts.hero_subtitle, hero_badge: texts.hero_badge, hero_alt: texts.hero_alt,
    stat_orders: flags.stat_orders, stat_rating: flags.stat_rating, campus_enabled: flags.campus_enabled, campus_title: texts.campus_title,
    hero_image: null as File | null, og_image: null as File | null, remove_hero_image: false, remove_og_image: false,
  })
  const errors = form.errors as Record<string, string | undefined>

  const [inputKey, setInputKey] = useState(0)
  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.post(`${BASE}/beranda`, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        form.setData((d) => ({ ...d, hero_image: null, og_image: null, remove_hero_image: false, remove_og_image: false }))
        setInputKey((k) => k + 1)
      },
    })
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-5 rounded-lg border p-4'>
      <h2 className='font-semibold'>Hero beranda</h2>
      <div className='space-y-2'>
        <Label htmlFor='hero_title'>Judul</Label>
        <Input id='hero_title' maxLength={150} value={form.data.hero_title} onChange={(e) => form.setData('hero_title', e.target.value)} />
        <p className='text-xs text-muted-foreground'>Apit kata dengan tanda * untuk menyorotnya emas, mis. <span>Nikmati *Hiburan Premium*</span>.</p>
        <FieldError message={errors.hero_title} />
      </div>
      <div className='space-y-2'>
        <Label htmlFor='hero_subtitle'>Subjudul</Label>
        <Textarea id='hero_subtitle' rows={2} maxLength={300} value={form.data.hero_subtitle} onChange={(e) => form.setData('hero_subtitle', e.target.value)} />
        <FieldError message={errors.hero_subtitle} />
      </div>
      <div className='space-y-2'>
        <Label htmlFor='hero_badge'>Label kecil di atas judul (opsional)</Label>
        <Input id='hero_badge' maxLength={80} value={form.data.hero_badge} onChange={(e) => form.setData('hero_badge', e.target.value)} placeholder='Dipercaya {pelanggan} pelanggan' />
        <p className='text-xs text-muted-foreground'>
          Kosong = tidak tampil. <span>{'{pelanggan}'}</span> dan <span>{'{pesanan}'}</span> diganti angka nyata (sekarang {formatNumber(stats.customers)} pelanggan, {formatNumber(stats.orders)} pesanan).
        </p>
        <FieldError message={errors.hero_badge} />
      </div>

      <div className='space-y-2 rounded-lg border p-3'>
        <p className='text-sm font-medium'>Chip statistik (angka nyata dari database)</p>
        <div className='flex items-center justify-between gap-3'>
          <Label htmlFor='stat_orders' className='font-normal'>"{formatNumber(stats.orders)} pesanan selesai"{stats.orders === 0 && ' (tersembunyi selama masih 0)'}</Label>
          <Switch id='stat_orders' checked={form.data.stat_orders} onCheckedChange={(v) => form.setData('stat_orders', v)} />
        </div>
        <div className='flex items-center justify-between gap-3'>
          <Label htmlFor='stat_rating' className='font-normal'>"Rating {stats.rating.toLocaleString('id-ID')}/5" dari {formatNumber(stats.reviews)} ulasan tampil{stats.reviews === 0 && ' (tersembunyi selama belum ada ulasan)'}</Label>
          <Switch id='stat_rating' checked={form.data.stat_rating} onCheckedChange={(v) => form.setData('stat_rating', v)} />
        </div>
      </div>

      <ImageField
        id='hero_image' label='Gambar hero' image={images.hero_image} file={form.data.hero_image} onFile={(f) => form.setData('hero_image', f)}
        remove={form.data.remove_hero_image} onRemove={(v) => form.setData('remove_hero_image', v)} accept='image/jpeg,image/png,image/webp' preview='h-28'
        hint={`JPG, PNG, atau WebP, maksimal ${sizeLabel(limits.image_kb)}. Disimpan apa adanya, jadi kecilkan dulu bila ukurannya besar. Ukuran ideal 1536 x 1024.`}
        error={errors.hero_image} inputKey={inputKey}
      />
      <div className='space-y-2'>
        <Label htmlFor='hero_alt'>Teks alt gambar hero</Label>
        <Input id='hero_alt' maxLength={150} value={form.data.hero_alt} onChange={(e) => form.setData('hero_alt', e.target.value)} />
        <p className='text-xs text-muted-foreground'>Deskripsi gambar untuk SEO dan pembaca layar.</p>
      </div>
      <ImageField
        id='og_image' label='Gambar bagikan (WhatsApp, Facebook, dan lainnya)' image={images.og_image} file={form.data.og_image} onFile={(f) => form.setData('og_image', f)}
        remove={form.data.remove_og_image} onRemove={(v) => form.setData('remove_og_image', v)} accept='image/jpeg,image/png,image/webp' preview='h-20'
        hint={`Tampil saat tautan toko dibagikan. Ukuran ideal 1200 x 630. Tanpa unggahan dipakai gambar hero.`}
        error={errors.og_image} inputKey={inputKey}
      />

      <div className='space-y-2 rounded-lg border p-3'>
        <div className='flex items-center justify-between gap-3'>
          <Label htmlFor='campus_enabled'>Bagian logo kampus</Label>
          <Switch id='campus_enabled' checked={form.data.campus_enabled} onCheckedChange={(v) => form.setData('campus_enabled', v)} />
        </div>
        <Input aria-label='Judul bagian kampus' maxLength={150} value={form.data.campus_title} onChange={(e) => form.setData('campus_title', e.target.value)} />
        <p className='text-xs text-muted-foreground'>Judul di atas deretan logo. Logonya diatur di kartu di bawah.</p>
        <FieldError message={errors.campus_title} />
      </div>

      <Button type='submit' disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button>
    </form>
  )
}

function CampusManager({ campuses, limitKb }: { campuses: Props['campuses']; limitKb: number }) {
  const form = useForm({ name: '', logo: null as File | null })
  const errors = form.errors as Record<string, string | undefined>
  const [deleting, setDeleting] = useState<number | null>(null)
  const [inputKey, setInputKey] = useState(0)

  const add = (e: FormEvent) => {
    e.preventDefault()
    form.post(`${BASE}/kampus`, { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset(); setInputKey((k) => k + 1) } })
  }

  return (
    <section className='space-y-4 rounded-lg border p-4'>
      <div>
        <h2 className='font-semibold'>Logo kampus</h2>
        <p className='text-sm text-muted-foreground'>Urutan di sini sama dengan urutan di beranda.</p>
      </div>
      {campuses.length === 0 ? (
        <p className='text-sm text-muted-foreground'>Belum ada logo. Bagian ini tidak tampil di beranda.</p>
      ) : (
        <ul className='divide-y rounded-md border'>
          {campuses.map((c, i) => (
            <li key={`${c.name}-${i}`} className='flex items-center gap-3 px-3 py-2'>
              <span className='w-6 text-sm text-muted-foreground'>{i + 1}</span>
              <img src={c.url} alt='' className='h-8 w-16 shrink-0 object-contain' />
              <div className='min-w-0 flex-1'>
                <p className='truncate text-sm'>{c.name}</p>
                <ImageOptimizeInfo info={c.info} url={`${BASE}/kampus/${i}/gambar`} compact />
              </div>
              <Button type='button' variant='outline' size='icon' className='size-8' disabled={i === 0} aria-label={`Naikkan ${c.name}`} title='Naikkan'
                onClick={() => router.patch(`${BASE}/kampus/${i}/urut`, { direction: 'up' }, { preserveScroll: true })}><ArrowUp /></Button>
              <Button type='button' variant='outline' size='icon' className='size-8' disabled={i === campuses.length - 1} aria-label={`Turunkan ${c.name}`} title='Turunkan'
                onClick={() => router.patch(`${BASE}/kampus/${i}/urut`, { direction: 'down' }, { preserveScroll: true })}><ArrowDown /></Button>
              <Button type='button' variant='outline' size='icon' className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive' aria-label={`Hapus ${c.name}`} title='Hapus'
                onClick={() => setDeleting(i)}><Trash2 /></Button>
            </li>
          ))}
        </ul>
      )}
      <form onSubmit={add} noValidate className='grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-start'>
        <div>
          <Input aria-label='Nama kampus' placeholder='Nama kampus' maxLength={100} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
          <FieldError message={errors.name} />
        </div>
        <div>
          <Input key={inputKey} aria-label='Logo kampus' type='file' accept='image/png,image/webp,image/jpeg' onChange={(e) => form.setData('logo', e.target.files?.[0] ?? null)} />
          <FieldError message={errors.logo} />
        </div>
        <Button type='submit' variant='outline' disabled={form.processing}>Tambah</Button>
      </form>
      <p className='text-xs text-muted-foreground'>PNG, WebP, atau JPG, maksimal {sizeLabel(limitKb)}.</p>

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(o) => !o && setDeleting(null)}
        title='Hapus logo kampus?'
        desc={`Logo "${deleting !== null ? campuses[deleting]?.name : ''}" dihapus dari beranda.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => deleting !== null && router.delete(`${BASE}/kampus/${deleting}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
      />
    </section>
  )
}

function SocialForm({ socials }: { socials: Social[] }) {
  const form = useForm({ socials: socials.map(({ platform, url, active }) => ({ platform, url, active })) })
  const errors = form.errors as Record<string, string | undefined>
  const meta = Object.fromEntries(socials.map((s) => [s.platform, s]))

  const update = (i: number, patch: Partial<{ url: string; active: boolean }>) => {
    form.clearErrors(`socials.${i}.url` as never)
    form.setData('socials', form.data.socials.map((s, j) => (j === i ? { ...s, ...patch } : s)))
  }
  const move = (i: number, d: -1 | 1) => {
    const next = [...form.data.socials]
    ;[next[i], next[i + d]] = [next[i + d], next[i]]
    form.setData('socials', next)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.put(`${BASE}/sosmed`, { preserveScroll: true })
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4 rounded-lg border p-4'>
      <div>
        <h2 className='font-semibold'>Sosial media</h2>
        <p className='text-sm text-muted-foreground'>Ikon tampil di footer sesuai urutan di sini. Yang mati atau URL-nya kosong tidak ditampilkan. Nomor WhatsApp tidak diketik di sini: diatur lewat ADMIN_WHATSAPP_NUMBER di file .env, dan juga dipakai tombol chat serta tombol WhatsApp lain di toko.</p>
      </div>
      <ul className='space-y-3'>
        {form.data.socials.map((s, i) => (
          <li key={s.platform} className='rounded-md border p-3'>
            <div className='flex items-center gap-3'>
              <i className={`fa-brands ${meta[s.platform].icon} w-5 text-center text-lg`} style={{ color: meta[s.platform].color === '#0F172A' ? 'currentColor' : meta[s.platform].color }} aria-hidden='true' />
              <Label htmlFor={`social-${s.platform}`} className='w-24 shrink-0'>{meta[s.platform].label}</Label>
              {meta[s.platform].managed ? (
                <Input
                  id={`social-${s.platform}`}
                  value={meta[s.platform].url ? `+${meta[s.platform].url.replace(/^https:\/\/wa\.me\/(\d+).*$/, '$1')}` : 'Belum diisi'}
                  disabled
                  title='Diatur lewat ADMIN_WHATSAPP_NUMBER di file .env'
                  className='min-w-0 flex-1'
                />
              ) : (
                <Input id={`social-${s.platform}`} type='url' value={s.url} placeholder='https://' onChange={(e) => update(i, { url: e.target.value })} className='min-w-0 flex-1' />
              )}
              <Switch checked={s.active} aria-label={`Tampilkan ${meta[s.platform].label}`} onCheckedChange={(v) => update(i, { active: v })} />
              <Button type='button' variant='ghost' size='icon' className='size-8' disabled={i === 0} aria-label={`Naikkan ${meta[s.platform].label}`} onClick={() => move(i, -1)}><ArrowUp /></Button>
              <Button type='button' variant='ghost' size='icon' className='size-8' disabled={i === form.data.socials.length - 1} aria-label={`Turunkan ${meta[s.platform].label}`} onClick={() => move(i, 1)}><ArrowDown /></Button>
            </div>
            <FieldError message={errors[`socials.${i}.url`]} />
          </li>
        ))}
      </ul>
      <Button type='submit' disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button>
    </form>
  )
}

function IconSvg({ svg, className = 'size-5' }: { svg: string; className?: string }) {
  // Ikon dari daftar bawaan di config/store.php (bukan masukan pengguna).
  return <svg viewBox='0 0 24 24' fill='none' stroke='currentColor' strokeWidth={1.9} strokeLinecap='round' strokeLinejoin='round' className={className} aria-hidden='true' dangerouslySetInnerHTML={{ __html: svg }} />
}

function ReasonsForm({ reasons, icons }: { reasons: Reasons; icons: Props['reasonIcons'] }) {
  const form = useForm<Reasons>({ ...reasons, items: reasons.items.map((i) => ({ ...i })) })
  const errors = form.errors as Record<string, string | undefined>
  const svgOf = (key: string) => icons.find((i) => i.key === key)?.svg ?? ''

  const setItem = (i: number, patch: Partial<ReasonItem>) => form.setData('items', form.data.items.map((it, j) => (j === i ? { ...it, ...patch } : it)))
  const move = (i: number, d: -1 | 1) => {
    const next = [...form.data.items]
    ;[next[i], next[i + d]] = [next[i + d], next[i]]
    form.setData('items', next)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.put(`${BASE}/keunggulan`, { preserveScroll: true })
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4 rounded-lg border p-4'>
      <div className='flex items-start justify-between gap-3'>
        <div>
          <h2 className='font-semibold'>Kenapa memilih kami</h2>
          <p className='text-sm text-muted-foreground'>Kartu keunggulan di beranda. Hindari klaim yang belum terbukti, misalnya jumlah pelanggan.</p>
        </div>
        <Switch checked={form.data.enabled} aria-label='Tampilkan bagian Kenapa memilih kami' onCheckedChange={(v) => form.setData('enabled', v)} />
      </div>

      <div className='grid gap-3 sm:grid-cols-[1fr_2fr]'>
        <div className='space-y-2'>
          <Label htmlFor='why_badge'>Label kecil</Label>
          <Input id='why_badge' maxLength={60} value={form.data.badge} onChange={(e) => form.setData('badge', e.target.value)} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='why_title'>Judul</Label>
          <Input id='why_title' maxLength={120} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
          <FieldError message={errors.title} />
        </div>
      </div>
      <div className='space-y-2'>
        <Label htmlFor='why_subtitle'>Subjudul</Label>
        <Textarea id='why_subtitle' rows={2} maxLength={300} value={form.data.subtitle} onChange={(e) => form.setData('subtitle', e.target.value)} />
      </div>

      <ul className='space-y-3'>
        {form.data.items.map((item, i) => (
          <li key={i} className='space-y-2 rounded-md border p-3'>
            <div className='flex items-center gap-2'>
              <span className='w-6 text-sm text-muted-foreground'>{String(i + 1).padStart(2, '0')}</span>
              <Select value={item.icon} onValueChange={(v) => setItem(i, { icon: v })}>
                <SelectTrigger className='w-40' aria-label={`Ikon kartu ${i + 1}`}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {icons.map((ic) => (
                    <SelectItem key={ic.key} value={ic.key}>
                      <span className='flex items-center gap-2'><IconSvg svg={ic.svg} className='size-4' /> {ic.label}</span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Input aria-label={`Judul kartu ${i + 1}`} maxLength={60} value={item.title} onChange={(e) => setItem(i, { title: e.target.value })} className='min-w-0 flex-1' />
              <Button type='button' variant='ghost' size='icon' className='size-8' disabled={i === 0} aria-label={`Naikkan kartu ${i + 1}`} onClick={() => move(i, -1)}><ArrowUp /></Button>
              <Button type='button' variant='ghost' size='icon' className='size-8' disabled={i === form.data.items.length - 1} aria-label={`Turunkan kartu ${i + 1}`} onClick={() => move(i, 1)}><ArrowDown /></Button>
              <Button type='button' variant='ghost' size='icon' className='size-8 text-destructive hover:text-destructive' aria-label={`Hapus kartu ${i + 1}`} onClick={() => form.setData('items', form.data.items.filter((_, j) => j !== i))}><Trash2 /></Button>
            </div>
            <Textarea aria-label={`Keterangan kartu ${i + 1}`} rows={2} maxLength={200} value={item.text} onChange={(e) => setItem(i, { text: e.target.value })} />
            <FieldError message={errors[`items.${i}.title`] ?? errors[`items.${i}.text`] ?? errors[`items.${i}.icon`]} />
          </li>
        ))}
      </ul>
      <FieldError message={errors.items} />

      <div className='flex flex-wrap gap-2'>
        <Button type='button' variant='outline' disabled={form.data.items.length >= 12} onClick={() => form.setData('items', [...form.data.items, { icon: icons[0]?.key ?? 'bolt', title: '', text: '' }])}>
          <Plus className='size-4' /> Tambah kartu
        </Button>
        <Button type='submit' disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button>
      </div>
    </form>
  )
}
