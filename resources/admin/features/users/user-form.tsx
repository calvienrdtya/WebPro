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
import { FieldError } from '@/features/products/field-error'
import { type UserForm, type UserRow } from './types'

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** User yang diubah; kosong berarti tambah baru. */
  user?: UserRow | null
}

export function UserFormDialog({ open, onOpenChange, ...rest }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-md'>
        {/* Dipasang ulang tiap dibuka agar isiannya selalu segar. */}
        <UserFormBody {...rest} onClose={() => onOpenChange(false)} />
      </DialogContent>
    </Dialog>
  )
}

function UserFormBody({
  user,
  onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
  const form = useForm<UserForm>({
    name: user?.name ?? '',
    email: user?.email ?? '',
    phone: user?.phone ?? '',
  })
  const { data, processing } = form
  const errors = form.errors as Record<string, string | undefined>

  const set = (key: keyof UserForm, value: string) => {
    if (errors[key]) form.clearErrors(key)
    form.setData(key, value)
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const opts = { preserveScroll: true, onSuccess: onClose }
    if (user) {
      // Email tidak bisa diubah, jadi tidak dikirim saat ubah.
      form.transform(({ email: _email, ...rest }) => rest)
      form.put(`/admin/user/${user.id}`, opts)
    } else form.post('/admin/user', opts)
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4'>
      <DialogHeader>
        <DialogTitle>{user ? 'Ubah User' : 'Tambah User'}</DialogTitle>
        <DialogDescription>
          {user
            ? 'Email menjadi identitas login (termasuk Google), jadi tidak bisa diubah.'
            : 'User yang ditambahkan admin masuk lewat Google, atau mengatur sandi sendiri dari halaman Lupa sandi.'}
        </DialogDescription>
      </DialogHeader>

      <div className='space-y-2'>
        <Label htmlFor='name'>Nama</Label>
        <Input id='name' maxLength={100} value={data.name} onChange={(e) => set('name', e.target.value)} placeholder='Nama user' autoComplete='off' />
        <FieldError message={errors.name} />
      </div>

      <div className='space-y-2'>
        <Label htmlFor='email'>Email</Label>
        <Input
          id='email'
          type='email'
          value={data.email}
          disabled={Boolean(user)}
          onChange={(e) => set('email', e.target.value)}
          placeholder='email@contoh.com'
          autoComplete='off'
        />
        <FieldError message={errors.email} />
      </div>

      <div className='space-y-2'>
        <Label htmlFor='phone'>Nomor telepon / WhatsApp</Label>
        <Input id='phone' inputMode='tel' value={data.phone} onChange={(e) => set('phone', e.target.value)} placeholder='08123456789' autoComplete='off' />
        <FieldError message={errors.phone} />
      </div>

      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
        <Button type='submit' disabled={processing}>
          {processing ? 'Menyimpan...' : user ? 'Simpan perubahan' : 'Tambah user'}
        </Button>
      </DialogFooter>
    </form>
  )
}
