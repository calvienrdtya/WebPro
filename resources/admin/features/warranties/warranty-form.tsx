import { type FormEvent } from 'react'
import { useForm } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
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
import { Switch } from '@/components/ui/switch'
import { FieldError } from '@/features/products/field-error'
import { type ProductOption, type WarrantyForm, type WarrantyRow } from './types'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  products: ProductOption[]
  /** Garansi yang diubah; kosong berarti tambah baru. */
  warranty?: WarrantyRow | null
}

export function WarrantyFormDialog({ open, onOpenChange, ...rest }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-xl'>
        {/* Dipasang ulang tiap dibuka agar isiannya selalu segar. */}
        <WarrantyFormBody {...rest} onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}

function WarrantyFormBody({
  products,
  warranty,
  onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
  const form = useForm<WarrantyForm>({
    name: warranty?.name ?? '',
    price: warranty?.price ?? '',
    duration_days: warranty?.duration_days ?? '',
    is_active: warranty?.is_active ?? true,
    applies_to_all: warranty?.applies_to_all ?? false,
    product_ids: warranty?.products.map((p) => p.id) ?? [],
  })
  const { data, processing } = form
  const errors = form.errors as Record<string, string | undefined>

  const clear = (key: string) => {
    const stale = Object.keys(errors).filter((k) => k === key || k.startsWith(`${key}.`))
    if (stale.length) (form.clearErrors as (...f: string[]) => void)(...stale)
  }

  const setText = (key: 'name' | 'price' | 'duration_days', value: string) => {
    clear(key)
    form.setData(key, value)
  }

  const toggleProduct = (id: number, checked: boolean) => {
    clear('product_ids')
    form.setData(
      'product_ids',
      checked ? [...data.product_ids, id] : data.product_ids.filter((x) => x !== id)
    )
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const opts = { preserveScroll: true, onSuccess: onClose }
    if (warranty) form.put(`/admin/garansi/${warranty.id}`, opts)
    else form.post('/admin/garansi', opts)
  }

  const allSelected = products.length > 0 && data.product_ids.length === products.length

  return (
    <form onSubmit={submit} noValidate className='space-y-4'>
      <DialogHeader>
        <DialogTitle>{warranty ? 'Ubah Garansi' : 'Tambah Garansi'}</DialogTitle>
        <DialogDescription>
          Paket garansi berbayar yang bisa dipilih pelanggan saat checkout. Harga Rp0 berarti garansi gratis.
        </DialogDescription>
      </DialogHeader>

      <div className='space-y-2'>
        <Label htmlFor='name'>Nama garansi</Label>
        <Input id='name' maxLength={60} value={data.name} onChange={(e) => setText('name', e.target.value)} placeholder='Garansi 7 Hari' autoComplete='off' />
        <FieldError message={errors.name} />
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='price'>Harga (Rp)</Label>
          <Input id='price' type='number' min={0} inputMode='numeric' value={data.price} onChange={(e) => setText('price', e.target.value)} placeholder='5000' />
          <FieldError message={errors.price} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='duration_days'>Masa garansi (hari)</Label>
          <Input id='duration_days' type='number' min={1} inputMode='numeric' value={data.duration_days} onChange={(e) => setText('duration_days', e.target.value)} placeholder='7' />
          <FieldError message={errors.duration_days} />
        </div>
      </div>

      <div className='flex items-center justify-between rounded-lg border px-3 py-2'>
        <div>
          <Label htmlFor='is_active'>Aktif</Label>
          <p className='text-xs text-muted-foreground'>Nonaktif = tidak ditawarkan di checkout.</p>
        </div>
        <Switch id='is_active' checked={data.is_active} onCheckedChange={(v) => form.setData('is_active', v)} />
      </div>

      <div className='space-y-3'>
        <p className='text-sm font-medium'>Produk yang berlaku</p>

        <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
          <div>
            <Label htmlFor='applies_to_all'>Semua produk</Label>
            <p className='text-xs text-muted-foreground'>Termasuk produk yang ditambahkan nanti.</p>
          </div>
          <Switch id='applies_to_all' checked={data.applies_to_all} onCheckedChange={(v) => form.setData('applies_to_all', v)} />
        </div>

        {!data.applies_to_all && (
          <div className='space-y-2'>
            <div className='flex items-center justify-between gap-2'>
              <p className='text-xs text-muted-foreground'>{data.product_ids.length} dari {products.length} produk dipilih.</p>
              <Button
                type='button'
                variant='ghost'
                size='sm'
                onClick={() => {
                  clear('product_ids')
                  form.setData('product_ids', allSelected ? [] : products.map((p) => p.id))
                }}
              >
                {allSelected ? 'Kosongkan' : 'Pilih semua yang ada'}
              </Button>
            </div>
            <div className='grid gap-1 rounded-lg border p-2 sm:grid-cols-2'>
              {products.map((p) => (
                <label key={p.id} className='flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted/60'>
                  <Checkbox
                    checked={data.product_ids.includes(p.id)}
                    onCheckedChange={(checked) => toggleProduct(p.id, checked === true)}
                    aria-label={p.name}
                  />
                  <span className='truncate'>{p.name}</span>
                </label>
              ))}
            </div>
            <FieldError message={errors.product_ids ?? Object.entries(errors).find(([k]) => k.startsWith('product_ids.'))?.[1]} />
          </div>
        )}
      </div>

      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
        <Button type='submit' disabled={processing}>
          {processing ? 'Menyimpan...' : warranty ? 'Simpan perubahan' : 'Tambah garansi'}
        </Button>
      </DialogFooter>
    </form>
  )
}
