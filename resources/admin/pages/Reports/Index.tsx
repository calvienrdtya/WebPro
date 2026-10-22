import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { CheckCheck, CircleCheck, Eye, Paperclip, RotateCcw, Search, Trash2 } from 'lucide-react'
import { formatNumber } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { BulkActionsBar } from '@/components/bulk-actions-bar'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { ReportDetailDialog, ResolveDialog, StatusBadge } from '@/features/reports/report-dialogs'
import { formatDateTime, formatMinutes, type Option, type ReportRow } from '@/features/reports/types'

type Paginated<T> = {
  data: T[]
  total: number
  from: number | null
  to: number | null
  prev_page_url: string | null
  next_page_url: string | null
}

type Props = {
  reports: Paginated<ReportRow>
  filters: { q: string; status: string; category: string; sort: 'asc' | 'desc' }
  counts: { all: number; open: number; resolved: number }
  stats: { waiting: number; resolved_this_month: number; total: number; avg_resolution_minutes: number | null }
  categories: Option[]
  resolutions: Option[]
}

const URL = '/admin/laporan'
const ALL = 'all'

export default function Index({ reports, filters, counts, stats, categories, resolutions }: Props) {
  const [q, setQ] = useState(filters.q)
  const [detailId, setDetailId] = useState<number | null>(null)
  const [resolving, setResolving] = useState<{ report: ReportRow } | { ids: number[] } | null>(null)
  const [deleting, setDeleting] = useState<ReportRow | null>(null)

  const [selected, setSelected] = useState<number[]>([])
  const [confirmBulkDelete, setConfirmBulkDelete] = useState(false)
  const [bulkBusy, setBulkBusy] = useState(false)

  // Detail dicari dari data terbaru agar ikut segar setelah aksi.
  const detail = reports.data.find((r) => r.id === detailId) ?? null

  useEffect(() => {
    const visible = new Set(reports.data.map((r) => r.id))
    setSelected((prev) => {
      const kept = prev.filter((id) => visible.has(id))
      return kept.length === prev.length ? prev : kept
    })
  }, [reports.data])

  const pageIds = reports.data.map((r) => r.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id))
  const someSelected = selected.length > 0 && !allSelected
  const toggleAll = (checked: boolean) => setSelected(checked ? pageIds : [])
  const toggleOne = (id: number, checked: boolean) =>
    setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)))

  const selectedRows = reports.data.filter((r) => selected.includes(r.id))
  const openIds = selectedRows.filter((r) => r.status === 'open').map((r) => r.id)
  const onlyResolved = selectedRows.length > 0 && openIds.length === 0

  const bulkDelete = () => {
    setBulkBusy(true)
    router.delete(`${URL}/massal`, {
      data: { ids: selected },
      preserveScroll: true,
      onSuccess: () => setSelected([]),
      onFinish: () => {
        setBulkBusy(false)
        setConfirmBulkDelete(false)
      },
    })
  }

  const reopen = (report: ReportRow) =>
    router.patch(`${URL}/${report.id}/buka`, {}, { preserveScroll: true })

  const firstRun = useRef(true)

  const visit = (patch: Partial<Props['filters']>) => {
    const next = { ...filters, q, ...patch }
    const params = Object.fromEntries(Object.entries(next).filter(([k, v]) => v && v !== ALL && !(k === 'sort' && v === 'asc')))
    router.get(URL, params, { preserveState: true, replace: true })
  }

  useEffect(() => {
    if (firstRun.current) {
      firstRun.current = false
      return
    }
    const timer = setTimeout(() => visit({ q }), 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q])

  const tabs = [
    { value: ALL, label: 'Semua', count: counts.all },
    { value: 'open', label: 'Menunggu', count: counts.open },
    { value: 'resolved', label: 'Selesai', count: counts.resolved },
  ]

  return (
    <>
      <Head title='Laporan' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Laporan</h1>
          <p className='text-sm text-muted-foreground'>
            Laporan masalah dari pelanggan. Tandai selesai setelah ditangani, termasuk laporan ganda.
          </p>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
          <StatCard label='Menunggu' value={formatNumber(stats.waiting)} />
          <StatCard label='Selesai bulan ini' value={formatNumber(stats.resolved_this_month)} />
          <StatCard label='Total laporan' value={formatNumber(stats.total)} />
          <StatCard label='Rata-rata penyelesaian' value={formatMinutes(stats.avg_resolution_minutes)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
          <div className='flex flex-col gap-2 sm:flex-row'>
            <div className='relative w-full sm:w-72'>
              <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
              <Input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder='Cari invoice, subjek, nama, atau email'
                aria-label='Cari laporan'
                className='ps-9'
              />
            </div>
            <Select value={filters.category} onValueChange={(v) => visit({ category: v })}>
              <SelectTrigger className='w-full sm:w-52' aria-label='Filter kategori'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Semua kategori</SelectItem>
                {categories.map((c) => (
                  <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={filters.sort} onValueChange={(v) => visit({ sort: v as 'asc' | 'desc' })}>
              <SelectTrigger className='w-full sm:w-44' aria-label='Urutkan laporan'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value='asc'>Terlama dulu</SelectItem>
                <SelectItem value='desc'>Terbaru dulu</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Tabs value={filters.status} onValueChange={(v) => visit({ status: v })} className='shrink-0'>
            <TabsList className='h-auto flex-wrap justify-start'>
              {tabs.map((t) => (
                <TabsTrigger key={t.value} value={t.value} className='gap-1.5'>
                  {t.label}
                  <span className='rounded-full bg-muted px-1.5 text-xs tabular-nums text-muted-foreground'>{t.count}</span>
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>
        </div>

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className='w-10'>
                  <Checkbox
                    checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                    onCheckedChange={(v) => toggleAll(v === true)}
                    aria-label='Pilih semua laporan di halaman ini'
                    disabled={pageIds.length === 0}
                  />
                </TableHead>
                <TableHead>Invoice</TableHead>
                <TableHead>Pelanggan</TableHead>
                <TableHead>Laporan</TableHead>
                <TableHead>Kategori</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='hidden md:table-cell'>Dilaporkan</TableHead>
                <TableHead className='w-32'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {reports.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={8} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.status !== ALL || filters.category !== ALL
                      ? 'Tidak ada laporan yang cocok.'
                      : 'Belum ada laporan dari pelanggan.'}
                  </TableCell>
                </TableRow>
              )}
              {reports.data.map((report) => (
                <TableRow key={report.id} data-state={selected.includes(report.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={selected.includes(report.id)}
                      onCheckedChange={(v) => toggleOne(report.id, v === true)}
                      aria-label={`Pilih laporan ${report.subject}`}
                    />
                  </TableCell>
                  <TableCell className='whitespace-nowrap'>
                    {report.order_code ? (
                      <button
                        type='button'
                        className='font-medium text-sky-600 hover:underline dark:text-sky-400'
                        title='Lihat laporan'
                        onClick={() => setDetailId(report.id)}
                      >
                        {report.order_code}
                      </button>
                    ) : (
                      <span className='text-muted-foreground'>-</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <p className='max-w-44 truncate font-medium'>{report.user.name}</p>
                    <p className='max-w-44 truncate text-xs text-muted-foreground'>{report.user.email}</p>
                  </TableCell>
                  <TableCell className='max-w-80'>
                    <button
                      type='button'
                      className='block max-w-full truncate text-start font-medium hover:underline'
                      title='Lihat laporan'
                      onClick={() => setDetailId(report.id)}
                    >
                      {report.subject}
                    </button>
                    {report.attachments.length > 0 && (
                      <span className='mt-0.5 inline-flex items-center gap-0.5 text-xs text-muted-foreground'>
                        <Paperclip className='size-3' /> {report.attachments.length} lampiran
                      </span>
                    )}
                  </TableCell>
                  <TableCell className='whitespace-nowrap'>
                    <Badge variant='outline' className='font-normal'>{report.category_label}</Badge>
                  </TableCell>
                  <TableCell>
                    <StatusBadge status={report.status} />
                    {report.resolution_label && <p className='mt-0.5 text-xs text-muted-foreground'>{report.resolution_label}</p>}
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap text-sm md:table-cell'>{formatDateTime(report.created_at)}</TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8'
                        aria-label={`Lihat laporan ${report.subject}`}
                        title='Lihat'
                        onClick={() => setDetailId(report.id)}
                      >
                        <Eye />
                      </Button>
                      {report.status === 'open' ? (
                        <Button
                          type='button'
                          variant='outline'
                          size='icon'
                          className='size-8'
                          aria-label={`Selesaikan laporan ${report.subject}`}
                          title='Selesaikan'
                          onClick={() => setResolving({ report })}
                        >
                          <CircleCheck />
                        </Button>
                      ) : (
                        <>
                          <Button
                            type='button'
                            variant='outline'
                            size='icon'
                            className='size-8'
                            aria-label={`Buka kembali laporan ${report.subject}`}
                            title='Buka kembali'
                            onClick={() => reopen(report)}
                          >
                            <RotateCcw />
                          </Button>
                          <Button
                            type='button'
                            variant='outline'
                            size='icon'
                            className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                            aria-label={`Hapus laporan ${report.subject}`}
                            title='Hapus'
                            onClick={() => setDeleting(report)}
                          >
                            <Trash2 />
                          </Button>
                        </>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        <div className='mt-4 flex flex-wrap items-center justify-between gap-2'>
          <p className='text-sm text-muted-foreground'>
            {reports.total > 0
              ? `Menampilkan ${reports.from}-${reports.to} dari ${reports.total} laporan`
              : 'Tidak ada laporan'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!reports.prev_page_url} onClick={() => reports.prev_page_url && router.get(reports.prev_page_url, {}, { preserveScroll: true })}>
              Sebelumnya
            </Button>
            <Button variant='outline' size='sm' disabled={!reports.next_page_url} onClick={() => reports.next_page_url && router.get(reports.next_page_url, {}, { preserveScroll: true })}>
              Berikutnya
            </Button>
          </div>
        </div>
      </Main>

      <ReportDetailDialog
        report={detail}
        onClose={() => setDetailId(null)}
        onResolve={(report) => {
          setDetailId(null)
          setResolving({ report })
        }}
        onReopen={reopen}
      />

      <ResolveDialog
        target={resolving}
        resolutions={resolutions}
        onClose={() => setResolving(null)}
        onDone={() => setSelected([])}
      />

      <BulkActionsBar count={selected.length} entityName='laporan' onClear={() => setSelected([])}>
        {openIds.length > 0 && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type='button'
                variant='outline'
                size='sm'
                className='h-8 gap-1.5'
                disabled={bulkBusy}
                onClick={() => setResolving({ ids: openIds })}
                aria-label='Selesaikan laporan terpilih'
              >
                <CheckCheck className='size-4' /> Selesaikan
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>Selesaikan laporan yang masih menunggu</p></TooltipContent>
          </Tooltip>
        )}
        {onlyResolved && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type='button'
                variant='outline'
                size='icon'
                className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                disabled={bulkBusy}
                onClick={() => setConfirmBulkDelete(true)}
                aria-label='Hapus laporan terpilih'
              >
                <Trash2 />
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>Hapus laporan terpilih</p></TooltipContent>
          </Tooltip>
        )}
      </BulkActionsBar>

      <ConfirmDialog
        open={confirmBulkDelete}
        onOpenChange={(open) => !open && setConfirmBulkDelete(false)}
        title={`Hapus ${selected.length} laporan?`}
        desc='Laporan terpilih dihapus permanen beserta lampirannya. Tindakan ini tidak bisa dibatalkan.'
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={bulkDelete}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus laporan?'
        desc={`Laporan "${deleting?.subject ?? ''}" dihapus permanen beserta lampirannya. Tindakan ini tidak bisa dibatalkan.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => {
          if (!deleting) return
          router.delete(`${URL}/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })
        }}
      />
    </>
  )
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <div className='rounded-lg border bg-card px-4 py-3'>
      <p className='text-xs text-muted-foreground'>{label}</p>
      <p className='mt-1 text-xl font-semibold tabular-nums'>{value}</p>
    </div>
  )
}
