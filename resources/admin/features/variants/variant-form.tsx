import { type FormEvent } from 'react'
import { useForm } from '@inertiajs/react'
import { Plus, Trash2 } from 'lucide-react'
import { formatRupiah } from '@/lib/format'
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
import { FieldError } from '@/features/products/field-error'
import { quote } from './pricing'
import {
  CUSTOM,
  FROM_PRODUCT,
  type ProductPriceForm,
  type VariantForm,
  type VariantOptions,
  type VariantRow,
} from './types'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  options: VariantOptions
  /** Varian yang diubah; kosong berarti tambah baru. */
  variant?: VariantRow | null
}

export function VariantFormDialog({ open, onOpenChange, ...rest }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-3xl'>
        {/* Dipasang ulang tiap dibuka agar isiannya selalu segar. */}
        <VariantFormBody {...rest} onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}

function VariantFormBody({
  options,
  variant,
  onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
  const form = useForm<VariantForm>({
    title: variant?.title ?? '',
    months: variant?.months ?? 1,
    is_active: variant?.is_active ?? true,
    products: (variant?.products ?? []).map((p) => ({
      product_id: p.product_id,
      price_mode: p.price_mode,
      price: p.price_mode === CUSTOM ? (p.price ?? '') : '',
      discount_percent: p.price_mode === CUSTOM ? p.discount_percent : 0,
    })),
  })
  const { data, processing } = form
  const errors = form.errors as Record<string, string | undefined>

  const clear = (key: string) => {
    const stale = Object.keys(errors).filter((k) => k === key || k.startsWith(`${key}.`))
    if (stale.length) (form.clearErrors as (...f: string[]) => void)(...stale)
  }

  const setField = (key: 'title' | 'months' | 'is_active', value: string | boolean) => {
    clear(key)
    // Jumlah bulan berubah: perbarui title otomatis selama title masih mengikuti pola "N Bulan".
    if (key === 'months' && /^\d+ Bulan$/.test(data.title.trim()) && Number(value) > 0)
      form.setData((d) => ({ ...d, months: String(value), title: `${Number(value)} Bulan` }))
    else form.setData(key as 'title', value as string)
  }

  const setRows = (rows: ProductPriceForm[]) => {
    clear('products')
    form.setData('products', rows)
  }

  const setRow = (i: number, patch: Partial<ProductPriceForm>) =>
    setRows(data.products.map((r, idx) => (idx === i ? { ...r, ...patch } : r)))

  const used = new Set(data.products.map((r) => r.product_id))
  const free = options.products.filter((p) => !used.has(p.id))
  const months = Number(data.months)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const opts = { preserveScroll: true, onSuccess: onClose }
    // Baris yang produknya belum dipilih tidak dikirim.
    form.transform((d) => ({ ...d, products: d.products.filter((r) => r.product_id !== '') }))
    if (variant) form.put(`/admin/varian/${variant.id}`, opts)
    else form.post('/admin/varian', opts)
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4'>
      <DialogHeader>
        <DialogTitle>{variant ? 'Ubah Varian' : 'Tambah Varian'}</DialogTitle>
        <DialogDescription>
          Varian adalah pilihan durasi di halaman produk. Pasang ke produk yang berlaku, lalu atur harganya per produk.
        </DialogDescription>
      </DialogHeader>

      <div className='grid gap-4 sm:grid-cols-[1fr_9rem_auto] sm:items-start'>
        <div className='space-y-2'>
          <Label htmlFor='title'>Title (tampil di kotak pilihan)</Label>
          <Input id='title' maxLength={30} value={data.title} onChange={(e) => setField('title', e.target.value)} placeholder='3 Bulan' autoComplete='off' />
          <FieldError message={errors.title} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='months'>Jumlah bulan</Label>
          <Input id='months' type='number' min={1} max={12} inputMode='numeric' value={data.months} onChange={(e) => setField('months', e.target.value)} />
          <FieldError message={errors.months} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='is_active'>Aktif</Label>
          <div className='flex h-9 items-center'>
            <Switch id='is_active' checked={data.is_active} onCheckedChange={(v) => setField('is_active', v)} />
          </div>
        </div>
      </div>

      <div className='space-y-3'>
        <div>
          <p className='text-sm font-medium'>Produk yang berlaku</p>
          <p className='text-xs text-muted-foreground'>
            Pilih "Ambil dari produk" untuk memakai harga jual produk dikali jumlah bulan, atau "Buat sendiri" untuk harga khusus varian ini.
          </p>
        </div>

        {data.products.length === 0 && (
          <p className='rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground'>
            Belum ada produk. Varian ini belum tampil di produk mana pun.
          </p>
        )}

        {data.products.map((row, i) => {
          const product = options.products.find((p) => p.id === row.product_id)
          const q = quote(row, product, months)
          const custom = row.price_mode === CUSTOM
          const choices = options.products.filter((p) => p.id === row.product_id || !used.has(p.id))

          return (
            // Kunci per produk (bukan indeks) agar melepas satu baris tidak membuat select baris lain terpasang ulang.
            <div key={row.product_id === '' ? 'baru' : row.product_id} className='space-y-2 rounded-lg border p-3'>
              <div className='grid gap-3 sm:grid-cols-[1fr_11rem_auto] sm:items-start'>
                <div className='space-y-1.5'>
                  <Label className='text-xs'>Produk</Label>
                  <Select
                    // Baris baru kosong: dropdown langsung terbuka agar admin memilih produknya.
                    defaultOpen={row.product_id === ''}
                    value={row.product_id === '' ? '' : String(row.product_id)}
                    onValueChange={(v) => v && setRow(i, { product_id: Number(v) })}
                  >
                    <SelectTrigger className='w-full' aria-label={`Produk ${i + 1}`}>
                      <SelectValue placeholder='Pilih produk' />
                    </SelectTrigger>
                    <SelectContent>
                      {choices.map((p) => (
                        <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FieldError message={errors[`products.${i}.product_id`]} />
                </div>
                <div className='space-y-1.5'>
                  <Label className='text-xs'>Harga</Label>
                  <Select value={row.price_mode} onValueChange={(v) => v && setRow(i, { price_mode: v })}>
                    <SelectTrigger className='w-full' aria-label={`Mode harga ${i + 1}`}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {options.modes.map((m) => (
                        <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FieldError message={errors[`products.${i}.price_mode`]} />
                </div>
                <Button
                  type='button'
                  variant='ghost'
                  size='icon'
                  className='mt-5'
                  aria-label={`Lepas produk ${i + 1}`}
                  onClick={() => setRows(data.products.filter((_, idx) => idx !== i))}
                >
                  <Trash2 />
                </Button>
              </div>

              {custom && (
                <div className='grid gap-3 sm:grid-cols-2'>
                  <div className='space-y-1.5'>
                    <Label className='text-xs'>Harga jual (Rp, sebelum diskon)</Label>
                    <Input
                      type='number'
                      min={1}
                      inputMode='numeric'
                      value={row.price}
                      onChange={(e) => setRow(i, { price: e.target.value })}
                      placeholder='110000'
                      aria-label={`Harga jual ${i + 1}`}
                    />
                    <FieldError message={errors[`products.${i}.price`]} />
                  </div>
                  <div className='space-y-1.5'>
                    <Label className='text-xs'>Diskon (%)</Label>
                    <Input
                      type='number'
                      min={0}
                      max={99}
                      inputMode='numeric'
                      value={row.discount_percent}
                      onChange={(e) => setRow(i, { discount_percent: e.target.value.replace(/^0+(?=\d)/, '') })}
                      placeholder='0'
                      aria-label={`Diskon ${i + 1}`}
                    />
                    <FieldError message={errors[`products.${i}.discount_percent`]} />
                  </div>
                </div>
              )}

              {q && (
                <div className='space-y-0.5 text-sm'>
                  <p>
                    Pelanggan membayar <strong>{formatRupiah(q.final)}</strong>
                    {q.discount > 0 && (
                      <>
                        {' '}
                        <span className='text-muted-foreground line-through'>{formatRupiah(q.list)}</span>{' '}
                        <span className='font-medium text-emerald-600'>-{q.discount}%</span>
                      </>
                    )}
                    {months > 1 && <span className='text-muted-foreground'> &middot; sekitar {formatRupiah(q.perMonth)} per bulan</span>}
                  </p>
                  {q.warnings.map((w) => (
                    <p key={w} className='text-xs text-amber-600 dark:text-amber-500'>{w}</p>
                  ))}
                </div>
              )}
            </div>
          )
        })}

        <FieldError message={errors.products} />

        <div className='flex flex-wrap gap-2'>
          <Button
            type='button'
            variant='outline'
            size='sm'
            disabled={free.length === 0 || data.products.some((r) => r.product_id === '')}
            onClick={() => setRows([...data.products, { product_id: '', price_mode: FROM_PRODUCT, price: '', discount_percent: 0 }])}
          >
            <Plus /> Tambah produk
          </Button>
          <Button
            type='button'
            variant='ghost'
            size='sm'
            disabled={free.length === 0}
            onClick={() =>
              setRows([
                ...data.products,
                ...free.map((p) => ({ product_id: p.id, price_mode: FROM_PRODUCT, price: '', discount_percent: 0 })),
              ])
            }
          >
            Pasang ke semua produk
          </Button>
        </div>
      </div>

      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
        <Button type='submit' disabled={processing}>
          {processing ? 'Menyimpan...' : variant ? 'Simpan perubahan' : 'Tambah varian'}
        </Button>
      </DialogFooter>
    </form>
  )
}
