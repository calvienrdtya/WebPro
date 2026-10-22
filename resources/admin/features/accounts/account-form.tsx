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
import { Textarea } from '@/components/ui/textarea'
import { FieldError } from '@/features/products/field-error'
import { addDays, formatDate, localToday } from './dates'
import { type AccountForm, type AccountFormOptions, type AccountRow } from './types'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  options: AccountFormOptions
  /** Akun yang diubah; kosong berarti tambah akun baru. */
  account?: AccountRow | null
  /** Produk terpilih awal saat menambah (mis. dari halaman per produk). */
  defaultProductId?: number | null
  /** Masa aktif bawaan akun baru (hari), dari pengaturan server. */
  defaultActiveDays: number
}

const profilePreview = (prefix: string, count: number) => {
  const base = prefix.trim() || 'Profil'
  const names = Array.from({ length: Math.min(count, 20) }, (_, i) => `${base} ${i + 1}`)
  return names.length > 3 ? `${names[0]}, ${names[1]}, ... ${names[names.length - 1]}` : names.join(', ')
}

export function AccountFormDialog({ open, onOpenChange, ...rest }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-xl'>
        {/* Form dipasang ulang tiap dibuka agar isiannya selalu segar. */}
        <AccountFormBody {...rest} onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}

function AccountFormBody({
  options,
  account,
  defaultProductId,
  defaultActiveDays,
  onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
  const form = useForm<AccountForm>({
    product_id: String(account?.product_id ?? defaultProductId ?? options.products[0]?.value ?? ''),
    email: account?.email ?? '',
    password: account?.password ?? '',
    profile: account?.profile ?? '',
    pin: account?.pin ?? '',
    cogs: account?.cogs != null ? String(account.cogs) : '',
    profile_count: '1',
    status: account?.status ?? 'available',
    sold_to: account?.sold_to ?? '',
    started_at: account?.started_at ?? localToday(),
    active_days: String(account?.active_days ?? defaultActiveDays),
    note: account?.note ?? '',
  })
  const { data, processing } = form
  const errors = form.errors as Record<string, string | undefined>

  const days = Number(data.active_days)
  const expiry =
    data.started_at && Number.isInteger(days) && days > 0 ? addDays(data.started_at, days) : null

  const set = (key: keyof AccountForm, value: string) => {
    if (errors[key]) form.clearErrors(key)
    form.setData(key, value)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const opts = { preserveScroll: true, onSuccess: onClose }
    if (account) form.transform(({ profile_count: _unused, ...rest }) => rest)
    if (account) form.put(`/admin/akun/${account.id}`, opts)
    else form.post('/admin/akun', opts)
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4'>
      <DialogHeader>
        <DialogTitle>{account ? 'Ubah Akun' : 'Tambah Akun'}</DialogTitle>
        <DialogDescription>
          Semua isian kredensial opsional dan bebas format. Kosongkan yang tidak dipakai produk ini.
        </DialogDescription>
      </DialogHeader>

      <div className='space-y-2'>
        <Label htmlFor='product_id'>Produk</Label>
        <Select value={data.product_id} onValueChange={(v) => set('product_id', v)}>
          <SelectTrigger id='product_id' className='w-full'>
            <SelectValue placeholder='Pilih produk' />
          </SelectTrigger>
          <SelectContent>
            {options.products.map((p) => (
              <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <FieldError message={errors.product_id} />
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='email'>Email</Label>
          <Input id='email' value={data.email} onChange={(e) => set('email', e.target.value)} autoComplete='off' placeholder='Email, username, atau link' />
          <FieldError message={errors.email} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='password'>Password</Label>
          <Input id='password' value={data.password} onChange={(e) => set('password', e.target.value)} autoComplete='off' placeholder='Bebas, tanpa aturan' />
          <FieldError message={errors.password} />
        </div>
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='profile'>Profil</Label>
          <Input id='profile' value={data.profile} onChange={(e) => set('profile', e.target.value)} autoComplete='off' placeholder='Profil 1' />
          <FieldError message={errors.profile} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='pin'>PIN</Label>
          <Input id='pin' value={data.pin} onChange={(e) => set('pin', e.target.value)} autoComplete='off' placeholder='1234' />
          <FieldError message={errors.pin} />
        </div>
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='cogs'>HPP per profil (Rp)</Label>
          <Input id='cogs' type='number' min={0} inputMode='numeric' value={data.cogs} onChange={(e) => set('cogs', e.target.value)} autoComplete='off' placeholder='20000' />
          <FieldError message={errors.cogs} />
        </div>
        <div className='space-y-2'>
          <Label htmlFor='active_days'>Masa aktif (hari)</Label>
          <Input id='active_days' type='number' min={1} inputMode='numeric' value={data.active_days} onChange={(e) => set('active_days', e.target.value)} />
          <FieldError message={errors.active_days} />
        </div>
      </div>

      <div className='space-y-2'>
        <Label htmlFor='started_at'>Tanggal mulai aktif</Label>
        <Input id='started_at' type='date' value={data.started_at} onChange={(e) => set('started_at', e.target.value)} />
        <FieldError message={errors.started_at} />
        <p className='text-xs text-muted-foreground'>
          {expiry ? <>Kedaluwarsa otomatis: <strong className='text-foreground'>{formatDate(expiry)}</strong>. </> : null}
          Isi tanggal mulai lebih awal bila akun sudah dibeli sebelumnya.
          {account && <> Ditambahkan ke sistem: {formatDate(account.created_at)}.</>}
        </p>
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className={`space-y-2 ${account ? 'sm:col-span-2' : ''}`}>
          <Label htmlFor='status'>Status</Label>
          <Select value={data.status} onValueChange={(v) => set('status', v)}>
            <SelectTrigger id='status' className='w-full'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {options.statuses.map((s) => (
                <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FieldError message={errors.status} />
        </div>
        {!account && (
          <div className='space-y-2'>
            <Label htmlFor='profile_count'>Jumlah profil</Label>
            <Input id='profile_count' type='number' min={1} max={20} inputMode='numeric' value={data.profile_count} onChange={(e) => set('profile_count', e.target.value)} />
            <FieldError message={errors.profile_count} />
          </div>
        )}
      </div>
      {!account && Number(data.profile_count) > 1 && (
        <p className='-mt-2 text-xs text-muted-foreground'>
          Akan dibuat {data.profile_count} baris dengan email, password, dan HPP yang sama: {profilePreview(data.profile, Number(data.profile_count))}. Isian Profil di atas dipakai sebagai awalan nama.
        </p>
      )}

      {data.status === 'sold' && (
        <div className='space-y-2'>
          <Label htmlFor='sold_to'>Terjual ke</Label>
          <Input id='sold_to' value={data.sold_to} onChange={(e) => set('sold_to', e.target.value)} autoComplete='off' placeholder='Nama / nomor WhatsApp pembeli' />
          <FieldError message={errors.sold_to} />
        </div>
      )}

      <div className='space-y-2'>
        <Label htmlFor='note'>Catatan</Label>
        <Textarea id='note' rows={2} value={data.note} onChange={(e) => set('note', e.target.value)} placeholder='Catatan internal (tidak tampil ke pelanggan)' />
        <FieldError message={errors.note} />
      </div>

      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
        <Button type='submit' disabled={processing}>
          {processing ? 'Menyimpan...' : account ? 'Simpan perubahan' : 'Tambah akun'}
        </Button>
      </DialogFooter>
    </form>
  )
}
