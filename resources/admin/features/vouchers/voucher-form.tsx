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
import { type ProductOption, type VoucherForm, type VoucherRow } from './types'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  products: ProductOption[]
  /** Voucher yang diubah; kosong berarti tambah baru. */
  voucher?: VoucherRow | null
}

export function VoucherFormDialog({ open, onOpenChange, ...rest }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-xl'>
        {/* Dipasang ulang tiap dibuka agar isiannya selalu segar. */}
        <VoucherFormBody {...rest} onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}

type TextKey =
  | 'code'
  | 'title'
  | 'description'
  | 'discount_value'
  | 'max_discount'
  | 'min_purchase'
  | 'starts_on'
  | 'ends_on'
  | 'quota'
  | 'per_user_limit'

function VoucherFormBody({
  products,
  voucher,
  onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
  const form = useForm<VoucherForm>({
    code: voucher?.code ?? '',
    title: voucher?.title ?? '',
    description: voucher?.description ?? '',
    discount_type: voucher?.discount_type ?? 'percent',
    discount_value: voucher?.discount_value ?? '',
    max_discount: voucher?.max_discount ?? '',
    min_purchase: voucher?.min_purchase ?? '',
    starts_on: voucher?.starts_on ?? '',
    ends_on: voucher?.ends_on ?? '',
    quota: voucher?.quota ?? '',
    per_user_limit: voucher ? (voucher.per_user_limit ?? '') : 1,
    requires_claim: voucher?.requires_claim ?? true,
    is_listed: voucher?.is_listed ?? true,
    is_active: voucher?.is_active ?? true,
    applies_to_all: voucher?.applies_to_all ?? true,
    product_ids: voucher?.products.map((p) => p.id) ?? [],
  })
  const { data, processing } = form
  const errors = form.errors as Record<string, string | undefined>
  const isPercent = data.discount_type === 'percent'

  const clear = (key: string) => {
    const stale = Object.keys(errors).filter((k) => k === key || k.startsWith(`${key}.`))
    if (stale.length) (form.clearErrors as (...f: string[]) => void)(...stale)
  }

  const setText = (key: TextKey, value: string) => {
    clear(key)
    form.setData(key, key === 'code' ? value.toUpperCase().replace(/\s+/g, '') : value)
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
    if (voucher) form.put(`/admin/voucher/${voucher.id}`, opts)
    else form.post('/admin/voucher', opts)
  }

  const allSelected = products.length > 0 && data.product_ids.length === products.length

  return (
    <form onSubmit={submit} noValidate className='space-y-4'>
      <DialogHeader>
        <DialogTitle>{voucher ? 'Ubah Voucher' : 'Tambah Voucher'}</DialogTitle>
        <DialogDescription>
          Potongan hanya berlaku untuk harga produk, tidak termasuk garansi dan biaya admin.
        </DialogDescription>
      </DialogHeader>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='code'>Kode voucher</Label>
          <Input id='code' maxLength={30} value={data.code} onChange={(e) => setText('code', e.target.value)} placeholder='HEMAT50' autoComplete='off' className='uppercase' />
          <FieldError message={errors.code} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='title'>Judul</Label>
          <Input id='title' maxLength={80} value={data.title} onChange={(e) => setText('title', e.target.value)} placeholder='Diskon 50% Semua Produk' autoComplete='off' />
          <FieldError message={errors.title} />
        </div>
      </div>

      <div className='space-y-2'>
        <Label htmlFor='description'>Deskripsi (opsional)</Label>
        <Textarea id='description' rows={2} maxLength={200} value={data.description} onChange={(e) => setText('description', e.target.value)} placeholder='Tampil di kartu voucher pada halaman toko.' />
        <FieldError message={errors.description} />
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='discount_type'>Jenis potongan</Label>
          <Select
            value={data.discount_type}
            onValueChange={(v) => {
              clear('discount_type')
              form.setData('discount_type', v as VoucherForm['discount_type'])
            }}
          >
            <SelectTrigger id='discount_type' className='w-full'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='percent'>Persen (%)</SelectItem>
              <SelectItem value='fixed'>Nominal (Rp)</SelectItem>
            </SelectContent>
          </Select>
          <FieldError message={errors.discount_type} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='discount_value'>{isPercent ? 'Besar potongan (%)' : 'Besar potongan (Rp)'}</Label>
          <Input id='discount_value' type='number' min={1} max={isPercent ? 100 : undefined} inputMode='numeric' value={data.discount_value} onChange={(e) => setText('discount_value', e.target.value)} placeholder={isPercent ? '50' : '25000'} />
          <FieldError message={errors.discount_value} />
        </div>
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        {isPercent && (
          <div className='space-y-2'>
            <Label htmlFor='max_discount'>Maksimal potongan (Rp, opsional)</Label>
            <Input id='max_discount' type='number' min={1} inputMode='numeric' value={data.max_discount} onChange={(e) => setText('max_discount', e.target.value)} placeholder='Tanpa batas' />
            <FieldError message={errors.max_discount} />
          </div>
        )}
        <div className='space-y-2'>
          <Label htmlFor='min_purchase'>Minimal pembelian (Rp, opsional)</Label>
          <Input id='min_purchase' type='number' min={1} inputMode='numeric' value={data.min_purchase} onChange={(e) => setText('min_purchase', e.target.value)} placeholder='Tanpa minimal' />
          <FieldError message={errors.min_purchase} />
        </div>
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='starts_on'>Mulai berlaku (opsional)</Label>
          <Input id='starts_on' type='date' value={data.starts_on} onChange={(e) => setText('starts_on', e.target.value)} />
          <FieldError message={errors.starts_on} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='ends_on'>Berakhir (opsional)</Label>
          <Input id='ends_on' type='date' value={data.ends_on} min={data.starts_on || undefined} onChange={(e) => setText('ends_on', e.target.value)} />
          <FieldError message={errors.ends_on} />
        </div>
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='quota'>Kuota total (opsional)</Label>
          <Input id='quota' type='number' min={1} inputMode='numeric' value={data.quota} onChange={(e) => setText('quota', e.target.value)} placeholder='Tanpa batas' />
          <FieldError message={errors.quota} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='per_user_limit'>Batas pakai per user (opsional)</Label>
          <Input id='per_user_limit' type='number' min={1} inputMode='numeric' value={data.per_user_limit} onChange={(e) => setText('per_user_limit', e.target.value)} placeholder='Tanpa batas' />
          <FieldError message={errors.per_user_limit} />
        </div>
      </div>

      <div className='space-y-2'>
        <SwitchRow id='requires_claim' label='Perlu diklaim' hint='Aktif: user harus menekan Klaim di halaman voucher sebelum bisa memakainya. Mati: kode langsung bisa diketik di checkout.' checked={data.requires_claim} onChange={(v) => form.setData('requires_claim', v)} />
        <SwitchRow id='is_listed' label='Tampil di halaman voucher' hint='Mati: voucher tidak muncul di halaman toko, hanya bisa dipakai lewat kode yang dibagikan.' checked={data.is_listed} onChange={(v) => form.setData('is_listed', v)} />
        <SwitchRow id='is_active' label='Aktif' hint='Nonaktif = voucher tidak bisa diklaim atau dipakai.' checked={data.is_active} onChange={(v) => form.setData('is_active', v)} />
      </div>

      <div className='space-y-3'>
        <p className='text-sm font-medium'>Produk yang berlaku</p>

        <SwitchRow id='applies_to_all' label='Semua produk' hint='Termasuk produk yang ditambahkan nanti.' checked={data.applies_to_all} onChange={(v) => { clear('product_ids'); form.setData('applies_to_all', v) }} />

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
          {processing ? 'Menyimpan...' : voucher ? 'Simpan perubahan' : 'Tambah voucher'}
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
