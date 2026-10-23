import { type FormEvent, useEffect, useMemo } from 'react'
import { Link, useForm } from '@inertiajs/react'
import { ArrowLeft, ImageOff, Upload } from 'lucide-react'
import { ImageOptimizeInfo } from '@/features/images/image-info'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
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
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { formatRupiah } from '@/lib/format'
import { CategoryField } from './category-field'
import { FieldError } from './field-error'
import { HighlightsField, SpecsField } from './list-fields'
import {
  type EditableProduct,
  type ProductForm,
  type ProductOptions,
  type VariantSummary,
  type WarrantySummary,
} from './types'

const NONE = 'none'

function VariantSummaryList({ variants, editing }: { variants: VariantSummary[]; editing: boolean }) {
  if (variants.length === 0)
    return (
      <p className='text-sm text-muted-foreground'>
        {editing
          ? 'Belum ada varian untuk produk ini, jadi dijual 1 bulan dengan harga di atas.'
          : 'Setelah produk disimpan, pasang varian dari menu Varian. Tanpa varian, produk dijual 1 bulan dengan harga di atas.'}
      </p>
    )

  return (
    <ul className='divide-y rounded-md border text-sm'>
      {variants.map((v) => (
        <li key={v.id} className='flex flex-wrap items-center justify-between gap-2 px-3 py-2'>
          <span className='flex items-center gap-2'>
            <span className='font-medium'>{v.title}</span>
            <span className='text-xs text-muted-foreground'>{v.months} bulan &middot; {v.mode_label}</span>
            {!v.is_active && <span className='rounded bg-muted px-1.5 text-xs text-muted-foreground'>Nonaktif</span>}
          </span>
          <span className='tabular-nums'>
            <strong>{formatRupiah(v.final_price)}</strong>
            {v.discount > 0 && (
              <span className='ms-2 text-xs text-muted-foreground'>
                <span className='line-through'>{formatRupiah(v.list_price)}</span>{' '}
                <span className='text-emerald-600'>-{v.discount}%</span>
              </span>
            )}
          </span>
        </li>
      ))}
    </ul>
  )
}

function WarrantySummaryList({ warranties, editing }: { warranties: WarrantySummary[]; editing: boolean }) {
  if (warranties.length === 0)
    return (
      <p className='text-sm text-muted-foreground'>
        {editing
          ? 'Belum ada garansi untuk produk ini.'
          : 'Garansi bertanda "Semua produk" otomatis berlaku untuk produk ini setelah disimpan. Garansi lain dipasang dari menu Garansi.'}
      </p>
    )

  return (
    <ul className='divide-y rounded-md border text-sm'>
      {warranties.map((w) => (
        <li key={w.id} className='flex flex-wrap items-center justify-between gap-2 px-3 py-2'>
          <span className='flex items-center gap-2'>
            <span className='font-medium'>{w.name}</span>
            <span className='text-xs text-muted-foreground'>{w.duration_days} hari</span>
            {!w.is_active && <span className='rounded bg-muted px-1.5 text-xs text-muted-foreground'>Nonaktif</span>}
          </span>
          <strong className='tabular-nums'>{w.price === 0 ? 'Gratis' : formatRupiah(w.price)}</strong>
        </li>
      ))}
    </ul>
  )
}

function PricePreview({ price, discount }: { price: number | string; discount: number | string }) {
  const base = Number(price)
  const pct = Number(discount) || 0
  if (!base || base < 0 || pct < 0 || pct > 99) return null
  const final = Math.round((base * (100 - pct)) / 100)

  return (
    <p className='rounded-md bg-muted px-3 py-2 text-sm'>
      Pelanggan membayar <strong>{formatRupiah(final)}</strong>
      {pct > 0 && (
        <>
          {' '}
          <span className='text-muted-foreground line-through'>{formatRupiah(base)}</span>{' '}
          <span className='font-medium text-emerald-600'>-{pct}%</span>
        </>
      )}
    </p>
  )
}

export function ProductFormView({
  options,
  product,
}: {
  options: ProductOptions
  product?: EditableProduct
}) {
  const editing = Boolean(product)

  const form = useForm<ProductForm>({
    name: product?.name ?? '',
    slug: product?.slug ?? '',
    category: product?.category ?? options.categories[0]?.value ?? '',
    new_category: '',
    account_type: product?.account_type ?? '',
    description: product?.description ?? '',
    highlights: product?.highlights?.length ? product.highlights : [''],
    specs: product?.specs ?? [],
    price: product?.price ?? '',
    discount_percent: product?.discount_percent ?? 0,
    service_fee: product?.service_fee ?? '',
    meta_title: product?.meta_title ?? '',
    meta_description: product?.meta_description ?? '',
    sort_order: product?.sort_order ?? 0,
    is_active: product?.is_active ?? true,
    image: null,
    remove_image: false,
  })
  const { data, processing } = form
  const errors = form.errors as Record<string, string | undefined>

  // Galat hilang begitu field terkait diubah (termasuk field bersarang seperti highlights.0).
  const setData = ((keyOrData: unknown, value?: unknown) => {
    if (typeof keyOrData === 'string') {
      const stale = Object.keys(errors).filter(
        (k) => k === keyOrData || k.startsWith(`${keyOrData}.`)
      )
      if (stale.length) (form.clearErrors as (...f: string[]) => void)(...stale)
      return (form.setData as (k: string, v: unknown) => void)(keyOrData, value)
    }
    return (form.setData as (d: unknown) => void)(keyOrData)
  }) as typeof form.setData

  const preview = useMemo(
    () => (data.image ? URL.createObjectURL(data.image) : null),
    [data.image]
  )
  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview)
  }, [preview])

  const currentImage = data.remove_image ? null : (preview ?? product?.image_url ?? null)

  const submit = (e: FormEvent) => {
    e.preventDefault()

    if (product) {
      form.transform((payload) => ({ ...payload, _method: 'put' }))
      form.post(`/admin/produk/${product.id}`, {
        forceFormData: true,
        preserveScroll: true,
      })
    } else {
      form.post('/admin/produk', { forceFormData: true })
    }
  }

  return (
    <>
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <Link
            href='/admin/produk'
            className='inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground'
          >
            <ArrowLeft className='size-4' /> Kembali ke daftar produk
          </Link>
          <h1 className='text-2xl font-bold tracking-tight'>
            {editing ? `Ubah Produk: ${product?.name}` : 'Tambah Produk'}
          </h1>
        </div>

        <form onSubmit={submit} noValidate className='space-y-6'>
          <div className='grid gap-6 lg:grid-cols-3'>
            <div className='space-y-6 lg:col-span-2'>
              <Card>
                <CardHeader>
                  <CardTitle>Informasi Produk</CardTitle>
                </CardHeader>
                <CardContent className='space-y-4'>
                  <div className='space-y-2'>
                    <Label htmlFor='name'>Nama produk</Label>
                    <Input id='name' value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder='Netflix Premium' />
                    <FieldError message={errors.name} />
                  </div>
                  <div className='space-y-2'>
                    <Label htmlFor='slug'>Slug</Label>
                    <Input id='slug' value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder='otomatis dari nama' />
                    <p className='mt-1 text-xs text-muted-foreground'>
                      Dipakai di alamat halaman produk. Kosongkan untuk dibuat otomatis; ubah dengan hati-hati karena alamat lama tidak lagi berlaku.
                    </p>
                    <FieldError message={errors.slug} />
                  </div>
                  <div className='space-y-2'>
                    <Label htmlFor='description'>Deskripsi</Label>
                    <Textarea id='description' rows={4} value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder='Ringkasan produk yang tampil di halaman produk' />
                    <FieldError message={errors.description} />
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Harga Jual</CardTitle>
                  <CardDescription>
                    Harga per akun untuk 1 bulan. Harga coret di toko adalah harga jual, dan pelanggan membayar harga jual dikurangi diskon.
                  </CardDescription>
                </CardHeader>
                <CardContent className='space-y-4'>
                  <div className='grid gap-4 sm:grid-cols-2'>
                    <div className='space-y-2'>
                      <Label htmlFor='price'>Harga jual (Rp)</Label>
                      <Input id='price' type='number' min={1} inputMode='numeric' value={data.price} onChange={(e) => setData('price', e.target.value)} placeholder='45000' />
                      <FieldError message={errors.price} />
                    </div>
                    <div className='space-y-2'>
                      <Label htmlFor='discount_percent'>Diskon (%)</Label>
                      <Input id='discount_percent' type='number' min={0} max={99} inputMode='numeric' value={data.discount_percent} onChange={(e) => setData('discount_percent', e.target.value.replace(/^0+(?=\d)/, ''))} placeholder='0' />
                      <FieldError message={errors.discount_percent} />
                    </div>
                  </div>
                  <PricePreview price={data.price} discount={data.discount_percent} />
                  <div className='space-y-2'>
                    <Label htmlFor='service_fee'>Biaya layanan khusus (Rp)</Label>
                    <Input
                      id='service_fee'
                      type='number'
                      min={0}
                      inputMode='numeric'
                      value={data.service_fee ?? ''}
                      onChange={(e) => setData('service_fee', e.target.value)}
                      placeholder={`Kosong = ikut biaya layanan aktif (${formatRupiah(options.service_fee)})`}
                      className='sm:max-w-sm'
                    />
                    <p className='text-xs text-muted-foreground'>
                      Kosongkan agar produk ini memakai biaya layanan aktif ({formatRupiah(options.service_fee)}). Isi 0 bila produk ini tanpa biaya layanan.
                    </p>
                    <FieldError message={errors.service_fee} />
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Poin Unggulan</CardTitle>
                  <CardDescription>Daftar singkat keunggulan produk (maksimal 10).</CardDescription>
                </CardHeader>
                <CardContent>
                  <HighlightsField value={data.highlights} onChange={(v) => setData('highlights', v)} errors={errors} />
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Spesifikasi</CardTitle>
                  <CardDescription>Pasangan nama dan isi, mis. Perangkat: Web, iOS, Android.</CardDescription>
                </CardHeader>
                <CardContent>
                  <SpecsField value={data.specs} onChange={(v) => setData('specs', v)} errors={errors} />
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Garansi</CardTitle>
                  <CardDescription>
                    Paket garansi dipasang dari menu{' '}
                    <Link href='/admin/garansi' className='underline underline-offset-2'>Garansi</Link>.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <WarrantySummaryList warranties={product?.warranties ?? []} editing={editing} />
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Varian</CardTitle>
                  <CardDescription>
                    Pilihan durasi (1, 2, 3 bulan, dan seterusnya) dipasang dari menu{' '}
                    <Link href='/admin/varian' className='underline underline-offset-2'>Varian</Link>.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <VariantSummaryList variants={product?.variants ?? []} editing={editing} />
                </CardContent>
              </Card>
            </div>

            <div className='space-y-6'>
              <Card>
                <CardHeader>
                  <CardTitle>Status</CardTitle>
                </CardHeader>
                <CardContent className='space-y-4'>
                  <div className='flex items-center justify-between gap-4'>
                    <div className='space-y-2'>
                      <Label htmlFor='is_active'>Tampil di toko</Label>
                      <p className='text-xs text-muted-foreground'>Nonaktif = disembunyikan dari pelanggan.</p>
                    </div>
                    <Switch id='is_active' checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                  </div>
                  <div className='space-y-2'>
                    <Label htmlFor='sort_order'>Urutan tampil</Label>
                    <Input id='sort_order' type='number' min={0} value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} />
                    <p className='mt-1 text-xs text-muted-foreground'>Angka kecil tampil lebih dulu.</p>
                    <FieldError message={errors.sort_order} />
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Kategori</CardTitle>
                </CardHeader>
                <CardContent className='space-y-4'>
                  <CategoryField
                    categories={options.categories}
                    category={data.category}
                    newCategory={data.new_category}
                    onChange={(category, newCategory) => {
                      setData('category', category)
                      setData('new_category', newCategory)
                    }}
                    error={errors.category ?? errors.new_category}
                  />
                  <div className='space-y-2'>
                    <Label htmlFor='account_type'>Jenis akun (stok)</Label>
                    <Select value={data.account_type || NONE} onValueChange={(v) => setData('account_type', v === NONE ? '' : v)}>
                      <SelectTrigger id='account_type' className='w-full'>
                        <SelectValue placeholder='Pilih jenis akun' />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value={NONE}>Belum ditentukan</SelectItem>
                        {options.account_types.map((o) => (
                          <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className='mt-1 text-xs text-muted-foreground'>Menghubungkan produk ke stok akun yang sejenis.</p>
                    <FieldError message={errors.account_type} />
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Gambar</CardTitle>
                  <CardDescription>JPG, PNG, atau WebP, maksimal 2 MB.</CardDescription>
                </CardHeader>
                <CardContent className='space-y-3'>
                  <div className='flex aspect-video items-center justify-center overflow-hidden rounded-lg border bg-muted'>
                    {currentImage ? (
                      <img src={currentImage} alt='Pratinjau gambar produk' className='h-full w-full object-contain' />
                    ) : (
                      <ImageOff className='size-8 text-muted-foreground' aria-hidden='true' />
                    )}
                  </div>
                  <div className='flex flex-wrap gap-2'>
                    <Button type='button' variant='outline' size='sm' asChild>
                      <label htmlFor='image' className='cursor-pointer'>
                        <Upload /> {currentImage ? 'Ganti gambar' : 'Pilih gambar'}
                      </label>
                    </Button>
                    {(product?.image_url || data.image) && (
                      <Button
                        type='button'
                        variant='ghost'
                        size='sm'
                        onClick={() => {
                          setData((prev) => ({
                            ...prev,
                            image: null,
                            remove_image: Boolean(product?.image_url) && !data.remove_image ? true : false,
                          }))
                        }}
                      >
                        {data.remove_image ? 'Batalkan hapus' : 'Hapus gambar'}
                      </Button>
                    )}
                  </div>
                  <input
                    id='image'
                    type='file'
                    accept='image/png,image/jpeg,image/webp'
                    className='sr-only'
                    onChange={(e) => {
                      const file = e.target.files?.[0] ?? null
                      form.clearErrors('image')
                      setData((prev) => ({ ...prev, image: file, remove_image: false }))
                      e.target.value = ''
                    }}
                  />
                  <FieldError message={errors.image} />
                  {product?.id && product.image_info && !data.image && !data.remove_image && (
                    <ImageOptimizeInfo info={product.image_info} url={`/admin/produk/${product.id}/gambar`} />
                  )}
                  <p className='text-xs text-muted-foreground'>Gambar otomatis dikecilkan dan disimpan WebP; file asli tetap disimpan.</p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>SEO</CardTitle>
                  <CardDescription>Opsional. Kosong = memakai nama dan deskripsi produk.</CardDescription>
                </CardHeader>
                <CardContent className='space-y-4'>
                  <div className='space-y-2'>
                    <Label htmlFor='meta_title'>Judul SEO</Label>
                    <Input id='meta_title' value={data.meta_title} maxLength={160} onChange={(e) => setData('meta_title', e.target.value)} />
                    <p className='mt-1 text-xs text-muted-foreground'>{data.meta_title.length}/160</p>
                    <FieldError message={errors.meta_title} />
                  </div>
                  <div className='space-y-2'>
                    <Label htmlFor='meta_description'>Deskripsi SEO</Label>
                    <Textarea id='meta_description' rows={3} value={data.meta_description} maxLength={300} onChange={(e) => setData('meta_description', e.target.value)} />
                    <p className='mt-1 text-xs text-muted-foreground'>{data.meta_description.length}/300</p>
                    <FieldError message={errors.meta_description} />
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>

          <div className='flex items-center justify-end gap-2 border-t pt-4'>
            <Button type='button' variant='outline' asChild>
              <Link href='/admin/produk'>Batal</Link>
            </Button>
            <Button type='submit' disabled={processing}>
              {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Simpan Produk'}
            </Button>
          </div>
        </form>
      </Main>
    </>
  )
}
