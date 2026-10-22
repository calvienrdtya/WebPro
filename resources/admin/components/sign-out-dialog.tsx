import { router } from '@inertiajs/react'
import { ConfirmDialog } from '@/components/confirm-dialog'

interface SignOutDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function SignOutDialog({ open, onOpenChange }: SignOutDialogProps) {
  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      title='Keluar'
      desc='Yakin ingin keluar? Kamu perlu masuk lagi untuk membuka panel admin.'
      confirmText='Keluar'
      cancelBtnText='Batal'
      destructive
      handleConfirm={() => router.post('/logout')}
      className='sm:max-w-sm'
    />
  )
}
