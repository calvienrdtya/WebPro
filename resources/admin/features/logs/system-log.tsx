import { useEffect, useState } from 'react'
import { Check, Copy } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'

export type LevelGroup = 'error' | 'warning' | 'info' | 'debug'

export type LogFile = { name: string; date: string | null; size: number; modified_at: string }

export type LogEntry = {
  offset: number
  datetime: string
  level: string
  group: LevelGroup
  message: string
  exception: string | null
  location: string | null
  url: string | null
  method: string | null
  user_id: number | null
}

type LogDetail = Omit<LogEntry, 'exception'> & {
  env: string
  exception: { class: string; message: string; file: string; line: number } | null
  request: { url: string | null; method: string | null; ip: string | null; user_id: number | null }
  context: string | null
  trace: string | null
  raw: string
}

export const LEVEL_LABELS: Record<LevelGroup, string> = {
  error: 'Error',
  warning: 'Peringatan',
  info: 'Info',
  debug: 'Debug',
}

const LEVEL_STYLES: Record<LevelGroup, string> = {
  error: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400',
  warning: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400',
  info: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-400',
  debug: 'border-border bg-muted text-muted-foreground',
}

export function LevelBadge({ group, level }: { group: LevelGroup; level: string }) {
  return (
    <Badge variant='outline' className={cn('font-medium', LEVEL_STYLES[group])}>
      {level}
    </Badge>
  )
}

export const formatBytes = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} KB`
  return `${(bytes / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB`
}

export const formatLogTime = (iso: string) =>
  new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' })

/** Nama file untuk pilihan: tanggal log harian, atau penanda file lama. */
export const fileLabel = (f: LogFile) =>
  f.date
    ? new Date(`${f.date}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
    : 'laravel.log (file lama)'

type DetailProps = { file: string | null; entry: LogEntry | null; onClose: () => void }

/** Detail satu entri: dimuat saat dibuka karena stack trace bisa panjang. */
export function LogDetailDialog({ file, entry, onClose }: DetailProps) {
  const [detail, setDetail] = useState<LogDetail | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [showRaw, setShowRaw] = useState(false)

  useEffect(() => {
    if (!file || !entry) return
    setDetail(null)
    setError(null)
    setShowRaw(false)
    const controller = new AbortController()
    fetch(`/admin/log-sistem/${encodeURIComponent(file)}/entri/${entry.offset}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: controller.signal,
    })
      .then(async (res) => {
        const data = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(data.message ?? `Galat ${res.status}.`)
        setDetail(data)
      })
      .catch((e: Error) => e.name !== 'AbortError' && setError(e.message || 'Tidak bisa memuat detail.'))
    return () => controller.abort()
  }, [file, entry])

  return (
    <Dialog open={entry !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl'>
        <DialogHeader className='border-b px-6 py-4 text-start'>
          <div className='flex flex-wrap items-center gap-2 pe-6'>
            {entry && <LevelBadge group={entry.group} level={entry.level} />}
            <DialogTitle className='text-base'>Detail log</DialogTitle>
          </div>
          <DialogDescription>
            {entry && new Date(entry.datetime).toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'medium' })}
            {detail && ` · ${detail.env}`}
          </DialogDescription>
        </DialogHeader>

        <div className='min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5 text-sm'>
          {error && <p className='rounded-md bg-destructive/10 px-3 py-2 text-destructive'>{error}</p>}
          {!detail && !error && (
            <div className='space-y-3'>
              <Skeleton className='h-5 w-3/4' />
              <Skeleton className='h-20 w-full' />
              <Skeleton className='h-40 w-full' />
            </div>
          )}

          {detail && (
            <>
              <Section title='Pesan'>
                <p className='break-words whitespace-pre-wrap'>{detail.message || detail.exception?.message || '-'}</p>
              </Section>

              {detail.exception && (
                <Section title='Exception'>
                  <div className='space-y-1 rounded-md border bg-muted/40 px-3 py-2'>
                    <p className='font-medium break-all'>{detail.exception.class}</p>
                    {detail.exception.message !== detail.message && (
                      <p className='break-words whitespace-pre-wrap text-muted-foreground'>{detail.exception.message}</p>
                    )}
                    <p className='break-all text-muted-foreground'>
                      {detail.exception.file}:{detail.exception.line}
                    </p>
                  </div>
                </Section>
              )}

              {(detail.request.url || detail.request.user_id || detail.request.ip) && (
                <Section title='Permintaan'>
                  <dl className='grid gap-x-4 gap-y-1.5 sm:grid-cols-[7rem_1fr]'>
                    {detail.request.url && (
                      <Row label='Halaman'>
                        {detail.request.method && <span className='me-1.5 font-medium'>{detail.request.method}</span>}
                        <span className='break-all'>{detail.request.url}</span>
                      </Row>
                    )}
                    {detail.request.ip && <Row label='IP'>{detail.request.ip}</Row>}
                    {detail.request.user_id && (
                      <Row label='User'>
                        <a href={`/admin/user?q=${detail.request.user_id}`} className='text-sky-600 hover:underline dark:text-sky-400'>
                          ID {detail.request.user_id}
                        </a>
                      </Row>
                    )}
                  </dl>
                </Section>
              )}

              {detail.context && (
                <Section title='Konteks'>
                  <Pre text={detail.context} />
                </Section>
              )}

              {detail.trace && (
                <Section title='Stack trace' copy={detail.trace}>
                  <Pre text={detail.trace} className='max-h-80' />
                </Section>
              )}

              <div>
                <Button type='button' variant='outline' size='sm' onClick={() => setShowRaw((v) => !v)}>
                  {showRaw ? 'Sembunyikan teks mentah' : 'Lihat teks mentah'}
                </Button>
                {showRaw && (
                  <div className='mt-3'>
                    <Section title='Teks mentah' copy={detail.raw}>
                      <Pre text={detail.raw} className='max-h-96' />
                    </Section>
                  </div>
                )}
              </div>

              <p className='text-xs text-muted-foreground'>
                Password, token, kunci API, dan nilai rahasia lain disamarkan. File asli di server tidak diubah.
              </p>
            </>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}

function Section({ title, copy, children }: { title: string; copy?: string; children: React.ReactNode }) {
  return (
    <section className='space-y-1.5'>
      <div className='flex items-center justify-between gap-2'>
        <h3 className='text-xs font-semibold tracking-wide text-muted-foreground uppercase'>{title}</h3>
        {copy && <CopyButton text={copy} />}
      </div>
      {children}
    </section>
  )
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <>
      <dt className='text-muted-foreground'>{label}</dt>
      <dd className='min-w-0'>{children}</dd>
    </>
  )
}

function Pre({ text, className }: { text: string; className?: string }) {
  return (
    <pre className={cn('overflow-auto rounded-md border bg-muted/40 px-3 py-2 font-sans text-xs leading-relaxed whitespace-pre', className)}>{text}</pre>
  )
}

function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false)
  return (
    <Button
      type='button'
      variant='ghost'
      size='sm'
      className='h-7 gap-1 px-2 text-xs'
      onClick={() => {
        navigator.clipboard?.writeText(text).then(() => {
          setCopied(true)
          setTimeout(() => setCopied(false), 1500)
        })
      }}
    >
      {copied ? <Check className='size-3.5' /> : <Copy className='size-3.5' />}
      {copied ? 'Tersalin' : 'Salin'}
    </Button>
  )
}
