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
import { Switch } from '@/components/ui/switch'
import { FieldError } from '@/features/products/field-error'
import { type ServiceFeeForm, type ServiceFeeRow } from './types'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Biaya yang diubah; kosong berarti tambah baru. */
  fee?: ServiceFeeRow | null
}

export function ServiceFeeFormDialog({ open, onOpenChange, fee }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-lg'>
        {/* Dipasang ulang tiap dibuka agar isiannya selalu segar. */}
        <Body fee={fee} onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}

function Body({ fee, onClose }: { fee?: ServiceFeeRow | null; onClose: () => void }) {
  const form = useForm<ServiceFeeForm>({
    name: fee?.name ?? '',
    amount: fee?.amount ?? '',
    is_active: fee?.is_active ?? false,
  })
  const { data, processing } = form
  const errors = form.errors as Record<string, string | undefined>

  const set = (key: 'name' | 'amount', value: string) => {
    form.clearErrors(key)
    form.setData(key, value)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const opts = { preserveScroll: true, onSuccess: onClose }
    if (fee) form.put(`/admin/biaya-layanan/${fee.id}`, opts)
    else form.post('/admin/biaya-layanan', opts)
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4'>
      <DialogHeader>
        <DialogTitle>{fee ? 'Ubah Biaya Layanan' : 'Buat Biaya Layanan'}</DialogTitle>
        <DialogDescription>
          Biaya tetap yang ditambahkan ke setiap pesanan baru dan tampil di rincian harga.
        </DialogDescription>
      </DialogHeader>

      <div className='space-y-2'>
        <Label htmlFor='fee_name'>Nama Biaya <span className='text-destructive'>*</span></Label>
        <Input id='fee_name' maxLength={60} value={data.name} onChange={(e) => set('name', e.target.value)} placeholder='Biaya Admin' autoComplete='off' />
        <FieldError message={errors.name} />
      </div>

      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='space-y-2'>
          <Label htmlFor='fee_amount'>Nominal <span className='text-destructive'>*</span></Label>
          <div className='flex'>
            <span className='flex items-center rounded-s-md border border-e-0 bg-muted px-3 text-sm text-muted-foreground'>Rp</span>
            <Input
              id='fee_amount'
              type='number'
              min={1}
              inputMode='numeric'
              className='rounded-s-none'
              value={data.amount}
              onChange={(e) => set('amount', e.target.value)}
              placeholder='1000'
            />
          </div>
          <FieldError message={errors.amount} />
        </div>

        <div className='space-y-2'>
          <div className='flex items-center gap-3 pt-6'>
            <Switch id='fee_active' checked={data.is_active} onCheckedChange={(v) => form.setData('is_active', v)} />
            <Label htmlFor='fee_active'>Aktif</Label>
          </div>
          <p className='text-xs text-muted-foreground'>Hanya satu biaya layanan yang diaktifkan di satu waktu.</p>
        </div>
      </div>

      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
        <Button type='submit' disabled={processing}>
          {processing ? 'Menyimpan...' : fee ? 'Simpan perubahan' : 'Buat biaya layanan'}
        </Button>
      </DialogFooter>
    </form>
  )
}
