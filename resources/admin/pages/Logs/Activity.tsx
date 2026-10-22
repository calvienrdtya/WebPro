import { useEffect, useRef, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { Eye, Search, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatNumber } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'

type Actor = 'admin' | 'user' | 'guest' | 'system'
type Option = { value: string; label: string }

type Change =
  | { model: string; label: string; event: 'created' | 'updated' | 'deleted'; rows: [string, string | null, string | null][] }
  | { more: number }

type Row = {
  id: number
  created_at: string
  actor_type: Actor
  actor_id: number | null
  actor_name: string | null
  actor_email: string | null
  action: string
  module: string
  module_label: string
  description: string
  subject_type: string | null
  subject_id: number | null
  subject_label: string | null
  changes: Change[]
  ip: string | null
  user_agent: string | null
}

type Props = {
  logs: { data: Row[]; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }
  filters: { q: string; actor: Actor | 'all'; module: string; from: string | null; to: string | null; subject: string | null; user: number | null }
  counts: Record<Actor | 'all', number>
  actors: Option[]
  modules: Option[]
  retentionDays: number
}

const URL = '/admin/log-aktivitas'
const ALL = 'all'

const ACTOR_STYLES: Record<Actor, string> = {
  admin: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400',
  user: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-400',
  guest: 'border-border bg-muted text-muted-foreground',
  system: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-400',
}

const EVENT_LABELS = { created: 'Dibuat', updated: 'Diubah', deleted: 'Dihapus' } as const

const formatTime = (iso: string) =>
  new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

const show = (v: string | null) => (v === null || v === '' ? '-' : v)

export default function Activity({ logs, filters, counts, actors, modules, retentionDays }: Props) {
  const [q, setQ] = useState(filters.q)
  const [detail, setDetail] = useState<Row | null>(null)
  const actorLabels = Object.fromEntries(actors.map((a) => [a.value, a.label])) as Record<Actor, string>

  const visit = (patch: Partial<Props['filters']>) => {
    const next = { ...filters, q, ...patch }
    const params = Object.fromEntries(
      Object.entries({ q: next.q, actor: next.actor, module: next.module, from: next.from, to: next.to, subjek: next.subject, user: next.user }).filter(
        ([, v]) => v !== null && v !== '' && v !== ALL,
      ),
    )
    router.get(URL, params, { preserveState: true, replace: true })
  }

  const firstRun = useRef(true)
  useEffect(() => {
    if (firstRun.current) {
      firstRun.current = false
      return
    }
    const timer = setTimeout(() => visit({ q }), 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q])

  const tabs = [{ value: ALL, label: 'Semua' }, ...actors]
  const scoped = filters.subject || filters.user

  return (
    <>
      <Head title='Log Aktivitas' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Log Aktivitas</h1>
          <p className='text-sm text-muted-foreground'>
            Siapa melakukan apa di admin dan toko, termasuk login dan proses otomatis. Disimpan {retentionDays} hari lalu dihapus otomatis;{' '}
            <Link href='/admin/pengaturan#masa-simpan-log' className='text-sky-600 hover:underline dark:text-sky-400'>ubah masa simpan</Link>.
          </p>
        </div>

        {scoped && (
          <div className='mb-4 flex flex-wrap items-center gap-2 rounded-md border bg-muted/40 px-3 py-2 text-sm'>
            <span>
              Hanya menampilkan riwayat {filters.user ? `user ID ${filters.user}` : `${filters.subject?.split(':')[0]} #${filters.subject?.split(':')[1]}`}.
            </span>
            <Button variant='ghost' size='sm' className='h-7 gap-1 px-2' onClick={() => visit({ subject: null, user: null })}>
              <X className='size-3.5' /> Tampilkan semua
            </Button>
          </div>
        )}

        <div className='mb-4 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between'>
          <div className='flex flex-col gap-2 sm:flex-row sm:flex-wrap'>
            <div className='relative w-full sm:w-72'>
              <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
              <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder='Cari aktivitas, nama, email, invoice, IP' aria-label='Cari aktivitas' className='ps-9' />
            </div>
            <Select value={filters.module} onValueChange={(v) => visit({ module: v })}>
              <SelectTrigger className='w-full sm:w-44' aria-label='Filter modul'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Semua modul</SelectItem>
                {modules.map((m) => (
                  <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <div className='flex items-center gap-2'>
              <Input type='date' value={filters.from ?? ''} max={filters.to ?? undefined} onChange={(e) => visit({ from: e.target.value || null })} aria-label='Dari tanggal' className='w-full sm:w-40' />
              <span className='text-sm text-muted-foreground'>s.d.</span>
              <Input type='date' value={filters.to ?? ''} min={filters.from ?? undefined} onChange={(e) => visit({ to: e.target.value || null })} aria-label='Sampai tanggal' className='w-full sm:w-40' />
            </div>
          </div>
          <Tabs value={filters.actor} onValueChange={(v) => visit({ actor: v as Actor | 'all' })} className='shrink-0'>
            <TabsList className='h-auto flex-wrap justify-start'>
              {tabs.map((t) => (
                <TabsTrigger key={t.value} value={t.value} className='gap-1.5'>
                  {t.label}
                  <span className='rounded-full bg-muted px-1.5 text-xs tabular-nums text-muted-foreground'>{formatNumber(counts[t.value as Actor | 'all'] ?? 0)}</span>
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>
        </div>

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className='w-40'>Waktu</TableHead>
                <TableHead>Pelaku</TableHead>
                <TableHead>Aktivitas</TableHead>
                <TableHead className='hidden lg:table-cell'>Modul</TableHead>
                <TableHead className='hidden xl:table-cell'>IP</TableHead>
                <TableHead className='w-12'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {logs.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.actor !== ALL || filters.module !== ALL || filters.from || filters.to || scoped
                      ? 'Tidak ada aktivitas yang cocok.'
                      : 'Belum ada aktivitas yang tercatat.'}
                  </TableCell>
                </TableRow>
              )}
              {logs.data.map((log) => (
                <TableRow key={log.id}>
                  <TableCell className='align-top text-sm whitespace-nowrap tabular-nums'>{formatTime(log.created_at)}</TableCell>
                  <TableCell className='max-w-56 align-top'>
                    <ActorBadge type={log.actor_type} label={actorLabels[log.actor_type]} />
                    {(log.actor_name || log.actor_email) && (
                      <p className='mt-1 truncate text-sm font-medium'>{log.actor_name ?? log.actor_email}</p>
                    )}
                    {log.actor_name && log.actor_email && <p className='truncate text-xs text-muted-foreground'>{log.actor_email}</p>}
                  </TableCell>
                  <TableCell className='max-w-[32rem] align-top'>
                    <button type='button' className='block max-w-full text-start hover:underline' onClick={() => setDetail(log)} title='Lihat detail'>
                      <span className='line-clamp-2 break-words'>{log.description}</span>
                    </button>
                    {changeCount(log) > 0 && <p className='mt-0.5 text-xs text-muted-foreground'>{changeCount(log)} perubahan data</p>}
                  </TableCell>
                  <TableCell className='hidden align-top lg:table-cell'>
                    <Badge variant='outline' className='font-normal'>{log.module_label}</Badge>
                  </TableCell>
                  <TableCell className='hidden align-top text-sm text-muted-foreground xl:table-cell'>{log.ip ?? '-'}</TableCell>
                  <TableCell className='align-top'>
                    <Button type='button' variant='outline' size='icon' className='size-8' aria-label='Lihat detail aktivitas' title='Lihat' onClick={() => setDetail(log)}>
                      <Eye />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        <div className='mt-4 flex flex-wrap items-center justify-between gap-2'>
          <p className='text-sm text-muted-foreground'>
            {logs.total > 0 ? `Menampilkan ${logs.from}-${logs.to} dari ${formatNumber(logs.total)} aktivitas` : 'Tidak ada aktivitas'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!logs.prev_page_url} onClick={() => logs.prev_page_url && router.get(logs.prev_page_url, {}, { preserveScroll: true })}>
              Sebelumnya
            </Button>
            <Button variant='outline' size='sm' disabled={!logs.next_page_url} onClick={() => logs.next_page_url && router.get(logs.next_page_url, {}, { preserveScroll: true })}>
              Berikutnya
            </Button>
          </div>
        </div>
      </Main>

      <DetailDialog log={detail} actorLabel={detail ? actorLabels[detail.actor_type] : ''} onClose={() => setDetail(null)} />
    </>
  )
}

const changeCount = (log: Row) => log.changes.reduce((n, c) => n + ('more' in c ? c.more : 1), 0)

function ActorBadge({ type, label }: { type: Actor; label: string }) {
  return (
    <Badge variant='outline' className={cn('font-medium', ACTOR_STYLES[type])}>
      {label}
    </Badge>
  )
}

function DetailDialog({ log, actorLabel, onClose }: { log: Row | null; actorLabel: string; onClose: () => void }) {
  return (
    <Dialog open={log !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-3xl'>
        {log && (
          <>
            <DialogHeader className='border-b px-6 py-4 text-start'>
              <div className='flex flex-wrap items-center gap-2 pe-6'>
                <ActorBadge type={log.actor_type} label={actorLabel} />
                <Badge variant='outline' className='font-normal'>{log.module_label}</Badge>
              </div>
              <DialogTitle className='pt-1 text-base leading-snug'>{log.description}</DialogTitle>
              <DialogDescription>{new Date(log.created_at).toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'medium' })}</DialogDescription>
            </DialogHeader>

            <div className='min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5 text-sm'>
              <dl className='grid gap-x-4 gap-y-1.5 sm:grid-cols-[8rem_1fr]'>
                <dt className='text-muted-foreground'>Pelaku</dt>
                <dd className='min-w-0 break-words'>
                  {log.actor_name ?? log.actor_email ?? actorLabel}
                  {log.actor_name && log.actor_email && <span className='text-muted-foreground'> · {log.actor_email}</span>}
                  {log.actor_id && (
                    <Link href={`${URL}?user=${log.actor_id}`} className='ms-2 text-sky-600 hover:underline dark:text-sky-400' onClick={onClose}>
                      Riwayat pelaku
                    </Link>
                  )}
                </dd>
                {log.subject_label && (
                  <>
                    <dt className='text-muted-foreground'>Subjek</dt>
                    <dd className='min-w-0 break-words'>
                      {log.subject_label}
                      {log.subject_type && log.subject_id && (
                        <Link href={`${URL}?subjek=${log.subject_type}:${log.subject_id}`} className='ms-2 text-sky-600 hover:underline dark:text-sky-400' onClick={onClose}>
                          Riwayat subjek
                        </Link>
                      )}
                    </dd>
                  </>
                )}
                {log.ip && (
                  <>
                    <dt className='text-muted-foreground'>IP</dt>
                    <dd>{log.ip}</dd>
                  </>
                )}
                {log.user_agent && (
                  <>
                    <dt className='text-muted-foreground'>Perangkat</dt>
                    <dd className='min-w-0 break-words text-muted-foreground'>{log.user_agent}</dd>
                  </>
                )}
                <dt className='text-muted-foreground'>Kode aksi</dt>
                <dd className='text-muted-foreground'>{log.action}</dd>
              </dl>

              <section className='space-y-2'>
                <h3 className='text-xs font-semibold tracking-wide text-muted-foreground uppercase'>Perubahan data</h3>
                {log.changes.length === 0 ? (
                  <p className='text-muted-foreground'>Tidak ada perubahan data yang tercatat untuk aktivitas ini.</p>
                ) : (
                  log.changes.map((c, i) =>
                    'more' in c ? (
                      <p key={i} className='text-muted-foreground'>Dan {c.more} perubahan lain (tidak ditampilkan agar catatan tidak terlalu besar).</p>
                    ) : (
                      <div key={i} className='overflow-hidden rounded-md border'>
                        <div className='flex flex-wrap items-center gap-2 border-b bg-muted/40 px-3 py-2'>
                          <Badge variant='outline' className={cn('font-normal', c.event === 'deleted' && 'text-destructive', c.event === 'created' && 'text-emerald-600 dark:text-emerald-400')}>
                            {EVENT_LABELS[c.event]}
                          </Badge>
                          <span className='font-medium'>{c.model}</span>
                          <span className='min-w-0 truncate text-muted-foreground'>{c.label}</span>
                        </div>
                        {c.rows.length > 0 && (
                          <table className='w-full table-fixed text-xs'>
                            <thead>
                              <tr className='text-muted-foreground'>
                                <th className='w-1/4 px-3 py-1.5 text-start font-medium'>Kolom</th>
                                {c.event === 'updated' && <th className='px-3 py-1.5 text-start font-medium'>Sebelum</th>}
                                <th className='px-3 py-1.5 text-start font-medium'>{c.event === 'updated' ? 'Sesudah' : 'Nilai'}</th>
                              </tr>
                            </thead>
                            <tbody>
                              {c.rows.map(([field, before, after], r) => (
                                <tr key={r} className='border-t align-top'>
                                  <td className='px-3 py-1.5 break-words text-muted-foreground'>{field}</td>
                                  {c.event === 'updated' && <td className='px-3 py-1.5 break-words text-red-700 dark:text-red-400'>{show(before)}</td>}
                                  <td className={cn('px-3 py-1.5 break-words', c.event === 'updated' && 'text-emerald-700 dark:text-emerald-400')}>{show(after)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        )}
                      </div>
                    ),
                  )
                )}
              </section>
            </div>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
