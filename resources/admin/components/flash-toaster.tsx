import { useEffect } from 'react'
import { usePage } from '@inertiajs/react'
import { toast } from 'sonner'
import { type SharedProps } from '@/types'

export function FlashToaster() {
  const { flash } = usePage<SharedProps>().props

  useEffect(() => {
    if (flash?.success) toast.success(flash.success)
    if (flash?.error) toast.error(flash.error)
    if (flash?.warning) toast.warning(flash.warning)
  }, [flash])

  return null
}
