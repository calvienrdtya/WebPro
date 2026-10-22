import { useRef, type KeyboardEvent, type ReactNode } from 'react'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'

type Props = {
  /** Jumlah baris terpilih; bar tersembunyi saat 0. */
  count: number
  /** Nama satuan, mis. "akun". */
  entityName: string
  onClear: () => void
  /** Tombol aksi. */
  children: ReactNode
}

/**
 * Bar aksi massal melayang di tengah bawah layar, tampil saat ada baris terpilih
 * (mengikuti pola data-table-bulk-actions di template dashboard-admin).
 */
export function BulkActionsBar({ count, entityName, onClear, children }: Props) {
  const toolbarRef = useRef<HTMLDivElement>(null)

  if (count === 0) return null

  const onKeyDown = (event: KeyboardEvent) => {
    const buttons = Array.from(toolbarRef.current?.querySelectorAll('button') ?? [])
    const current = buttons.findIndex((b) => b === document.activeElement)

    if (event.key === 'ArrowRight' && buttons.length) {
      event.preventDefault()
      buttons[(current + 1) % buttons.length]?.focus()
    } else if (event.key === 'ArrowLeft' && buttons.length) {
      event.preventDefault()
      buttons[current <= 0 ? buttons.length - 1 : current - 1]?.focus()
    } else if (event.key === 'Escape') {
      // Escape milik menu dropdown (yang ditutup Radix dulu) tidak boleh menghapus pilihan.
      const target = event.target as HTMLElement
      if (target.closest('[data-slot="dropdown-menu-trigger"], [data-slot="dropdown-menu-content"]')) return
      event.preventDefault()
      onClear()
    }
  }

  return (
    <>
      <div aria-live='polite' aria-atomic='true' className='sr-only' role='status'>
        {count} {entityName} dipilih
      </div>

      <div
        ref={toolbarRef}
        role='toolbar'
        aria-label={`Aksi untuk ${count} ${entityName} terpilih`}
        tabIndex={-1}
        onKeyDown={onKeyDown}
        className={cn(
          'fixed bottom-6 left-1/2 z-50 max-w-[calc(100vw-1rem)] -translate-x-1/2 rounded-xl',
          'transition-all delay-100 duration-300 ease-out hover:scale-105',
          'focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none'
        )}
      >
        <div
          className={cn(
            'flex items-center gap-x-2 rounded-xl border p-2 shadow-xl',
            'bg-background/95 backdrop-blur-lg supports-backdrop-filter:bg-background/60'
          )}
        >
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type='button'
                variant='outline'
                size='icon'
                onClick={onClear}
                className='size-6 rounded-full'
                aria-label='Batalkan pilihan'
              >
                <X />
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              <p>Batalkan pilihan (Esc)</p>
            </TooltipContent>
          </Tooltip>

          <Separator className='h-5' orientation='vertical' aria-hidden='true' />

          <div className='flex items-center gap-x-1.5 whitespace-nowrap text-sm'>
            <Badge variant='default' className='min-w-8 rounded-lg' aria-label={`${count} dipilih`}>
              {count}
            </Badge>
            <span className='hidden sm:inline'>{entityName}</span> dipilih
          </div>

          <Separator className='h-5' orientation='vertical' aria-hidden='true' />

          {children}
        </div>
      </div>
    </>
  )
}
