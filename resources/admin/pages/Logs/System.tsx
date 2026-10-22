import { useEffect, useRef, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { Download, Eye, Search, Trash2 } from 'lucide-react'
import { formatNumber } from '@/lib/format'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { ConfirmDialog } from '@/components/confirm-dialog'
import {
  fileLabel,
  formatBytes,
  formatLogTime,
  LEVEL_LABELS,
  LevelBadge,
  LogDetailDialog,
  type LevelGroup,
  type LogEntry,
  type LogFile,
} from '@/features/logs/system-log'

type Props = {
  files: LogFile[]
  file: string | null
  filters: { level: LevelGroup | 'all'; q: string }
  entries: LogEntry[]
  total: number
  counts: Record<LevelGroup, number>
  partial: boolean
  page: number
  perPage: number
  retentionDays: number
  maxReadMb: number
  logLevel: string
}

const URL = '/admin/log-sistem'
const ALL = 'all'

export default function System({ files, file, filters, entries, total, counts, partial, page, perPage, retentionDays, maxReadMb, logLevel }: Props) {
  const [q, setQ] = useState(filters.q)
  const [detail, setDetail] = useState<LogEntry | null>(null)
  const [deleting, setDeleting] = useState(false)
  const current = files.find((f) => f.name === file) ?? null

  const visit = (patch: Partial<{ file: string; level: string; q: string; page: number }>) => {
    const next = { file: file ?? '', level: filters.level, q, page: 1, ...patch }
    const params = Object.fromEntries(
      Object.entries(next).filter(([k, v]) => v && v !== ALL && !(k === 'page' && v === 1) && !(k === 'file' && v === files[0]?.name)),
    )
    router.get(URL, params, { preserveState: true, preserveScroll: 'page' in patch, replace: true })
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

  const all = Object.values(counts).reduce((a, b) => a + b, 0)
  const tabs: { value: LevelGroup | 'all'; label: string; count: number }[] = [
    { value: ALL, label: 'Semua', count: all },
    ...(Object.keys(LEVEL_LABELS) as LevelGroup[]).map((g) => ({ value: g, label: LEVEL_LABELS[g], count: counts[g] })),
  ]
  const from = total ? (page - 1) * perPage + 1 : 0
  const to = Math.min(page * perPage, total)

  return (
    <>
      <Head title='Log Sistem' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Log Sistem</h1>
          <p className='text-sm text-muted-foreground'>
            Catatan error dan peringatan teknis website, satu file per hari. Disimpan {retentionDays} hari lalu dihapus otomatis;{' '}
            <Link href='/admin/pengaturan#masa-simpan-log' className='text-sky-600 hover:underline dark:text-sky-400'>ubah masa simpan</Link>.
            {' '}Level yang dicatat: {logLevel} ke atas.
          </p>
        </div>

        {files.length === 0 ? (
          <div className='rounded-lg border border-dashed px-6 py-16 text-center'>
            <p className='font-medium'>Belum ada file log</p>
            <p className='mt-1 text-sm text-muted-foreground'>Belum ada error atau peringatan yang tercatat.</p>
          </div>
        ) : (
          <>
            <div className='mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between'>
              <div className='flex flex-col gap-2 sm:flex-row'>
                <Select value={file ?? undefined} onValueChange={(v) => visit({ file: v })}>
                  <SelectTrigger className='w-full sm:w-72' aria-label='Pilih file log'>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {files.map((f) => (
                      <SelectItem key={f.name} value={f.name}>
                        {fileLabel(f)} <span className='text-muted-foreground'>· {formatBytes(f.size)}</span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <div className='relative w-full sm:w-72'>
                  <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
                  <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder='Cari pesan, kelas, file, atau URL' aria-label='Cari log' className='ps-9' />
                </div>
              </div>
              <div className='flex gap-2'>
                <Button variant='outline' size='sm' asChild>
                  <a href={`${URL}/${encodeURIComponent(file ?? '')}/unduh`}>
                    <Download /> Unduh
                  </a>
                </Button>
                <Button variant='outline' size='sm' className='text-destructive hover:bg-destructive/10 hover:text-destructive' onClick={() => setDeleting(true)}>
                  <Trash2 /> Hapus file
                </Button>
              </div>
            </div>

            <Tabs value={filters.level} onValueChange={(v) => visit({ level: v })} className='mb-4'>
              <TabsList className='h-auto flex-wrap justify-start'>
                {tabs.map((t) => (
                  <TabsTrigger key={t.value} value={t.value} className='gap-1.5'>
                    {t.label}
                    <span className='rounded-full bg-muted px-1.5 text-xs tabular-nums text-muted-foreground'>{formatNumber(t.count)}</span>
                  </TabsTrigger>
                ))}
              </TabsList>
            </Tabs>

            {partial && current && (
              <p className='mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300'>
                File ini {formatBytes(current.size)}, jadi hanya {maxReadMb} MB terakhir yang ditampilkan. Unduh file untuk melihat semuanya.
              </p>
            )}

            <div className='rounded-lg border'>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className='w-36'>Waktu</TableHead>
                    <TableHead className='w-28'>Level</TableHead>
                    <TableHead>Pesan</TableHead>
                    <TableHead className='hidden lg:table-cell'>Halaman</TableHead>
                    <TableHead className='w-12'><span className='sr-only'>Aksi</span></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {entries.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5} className='h-32 text-center text-muted-foreground'>
                        {filters.q || filters.level !== ALL ? 'Tidak ada entri yang cocok.' : 'File ini kosong.'}
                      </TableCell>
                    </TableRow>
                  )}
                  {entries.map((e) => (
                    <TableRow key={e.offset}>
                      <TableCell className='align-top text-sm whitespace-nowrap tabular-nums'>{formatLogTime(e.datetime)}</TableCell>
                      <TableCell className='align-top'>
                        <LevelBadge group={e.group} level={e.level} />
                      </TableCell>
                      <TableCell className='max-w-[36rem] align-top'>
                        <button type='button' className='block max-w-full text-start hover:underline' onClick={() => setDetail(e)} title='Lihat detail'>
                          <span className='line-clamp-2 break-words'>{e.message || '(tanpa pesan)'}</span>
                        </button>
                        {(e.exception || e.location) && (
                          <p className='mt-0.5 truncate text-xs text-muted-foreground'>
                            {[e.exception, e.location].filter(Boolean).join(' · ')}
                          </p>
                        )}
                      </TableCell>
                      <TableCell className='hidden max-w-60 align-top text-sm lg:table-cell'>
                        {e.url ? (
                          <p className='truncate' title={e.url}>
                            {e.method && <span className='me-1 text-xs font-medium text-muted-foreground'>{e.method}</span>}
                            {e.url}
                          </p>
                        ) : (
                          <span className='text-muted-foreground'>-</span>
                        )}
                        {e.user_id && <p className='text-xs text-muted-foreground'>User ID {e.user_id}</p>}
                      </TableCell>
                      <TableCell className='align-top'>
                        <Button type='button' variant='outline' size='icon' className='size-8' aria-label='Lihat detail log' title='Lihat' onClick={() => setDetail(e)}>
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
                {total > 0 ? `Menampilkan ${from}-${to} dari ${formatNumber(total)} entri, terbaru dulu` : 'Tidak ada entri'}
              </p>
              <div className='flex gap-2'>
                <Button variant='outline' size='sm' disabled={page <= 1} onClick={() => visit({ page: page - 1 })}>
                  Sebelumnya
                </Button>
                <Button variant='outline' size='sm' disabled={to >= total} onClick={() => visit({ page: page + 1 })}>
                  Berikutnya
                </Button>
              </div>
            </div>
          </>
        )}
      </Main>

      <LogDetailDialog file={file} entry={detail} onClose={() => setDetail(null)} />

      <ConfirmDialog
        open={deleting}
        onOpenChange={setDeleting}
        title='Hapus file log?'
        desc={`File ${file ?? ''}${current ? ` (${formatBytes(current.size)})` : ''} dihapus permanen dari server. Tindakan ini tidak bisa dibatalkan.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => file && router.delete(`${URL}/${encodeURIComponent(file)}`, { onFinish: () => setDeleting(false) })}
      />
    </>
  )
}
