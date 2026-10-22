import { useState } from 'react'
import { router } from '@inertiajs/react'
import { Loader2, RotateCcw, Sparkles } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { formatBytes } from '@/features/logs/system-log'

/** Data dari App\Support\ImageOptimizer::info (null = tidak ada gambar). */
export type ImageInfo = {
  tracked: boolean
  optimized: boolean
  size: number
  width?: number
  height?: number
  format?: string
  quality?: number | null
  original_size?: number
  original_width?: number
  original_height?: number
  can_original: boolean
  can_optimize: boolean
} | null

/**
 * Keterangan ukuran gambar dan tombol per gambar: "Pakai file asli" (bila sedang memakai versi optimal) atau
 * "Optimalkan" (bila memakai file asli / gambar lama yang belum dioptimalkan). Mengirim PATCH {action} ke `url`.
 */
export function ImageOptimizeInfo({ info, url, compact = false }: { info: ImageInfo; url: string; compact?: boolean }) {
  const [busy, setBusy] = useState<'original' | 'optimize' | null>(null)
  if (!info) return null

  const act = (action: 'original' | 'optimize') => {
    setBusy(action)
    router.patch(url, { action }, { preserveScroll: true, onFinish: () => setBusy(null) })
  }

  const saved = info.optimized && info.original_size ? Math.round((1 - info.size / info.original_size) * 100) : 0
  const dims = (w?: number, h?: number) => (w && h ? `${w}×${h}` : '')

  return (
    <div className={compact ? 'space-y-1.5' : 'space-y-2 rounded-md border bg-muted/40 px-3 py-2'}>
      <p className='text-xs leading-relaxed text-muted-foreground'>
        {info.optimized ? (
          <>
            <span className='font-medium text-emerald-700 dark:text-emerald-400'>Dioptimalkan</span>:{' '}
            {formatBytes(info.original_size ?? 0)} → <span className='font-medium text-foreground'>{formatBytes(info.size)}</span>
            {saved > 0 && ` (hemat ${saved}%)`} · {dims(info.width, info.height)} · {info.format} {info.quality}
          </>
        ) : info.tracked ? (
          <>
            <span className='font-medium text-foreground'>Memakai file asli</span>: {formatBytes(info.size)}
            {dims(info.width, info.height) && ` · ${dims(info.width, info.height)}`}
          </>
        ) : (
          <>
            <span className='font-medium text-foreground'>Belum dioptimalkan</span>: {formatBytes(info.size)}
          </>
        )}
      </p>
      <div className='flex flex-wrap gap-2'>
        {info.can_original && (
          <Button type='button' variant='outline' size='sm' className='h-7 gap-1.5 text-xs' disabled={busy !== null} onClick={() => act('original')}>
            {busy === 'original' ? <Loader2 className='size-3.5 animate-spin' /> : <RotateCcw className='size-3.5' />} Pakai file asli
          </Button>
        )}
        {info.can_optimize && (
          <Button type='button' variant='outline' size='sm' className='h-7 gap-1.5 text-xs' disabled={busy !== null} onClick={() => act('optimize')}>
            {busy === 'optimize' ? <Loader2 className='size-3.5 animate-spin' /> : <Sparkles className='size-3.5' />} Optimalkan
          </Button>
        )}
      </div>
    </div>
  )
}
