import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Eye, EyeOff, Search, ShieldCheck, Star, Trash2 } from 'lucide-react'
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

type ReviewStatus = 'pending' | 'approved' | 'hidden'

type ReviewRow = {
  id: number
  reviewer_name: string
  product: { id: number | null; name: string }
  rating: number
  body: string
  status: ReviewStatus
  verified: boolean
  created_at: string
}

type Paginated<T> = {
  data: T[]
  total: number
  from: number | null
  to: number | null
  prev_page_url: string | null
  next_page_url: string | null
}

type Props = {
  reviews: Paginated<ReviewRow>
  filters: { q: string; status: string; product: string }
  counts: { all: number; pending: number; approved: number; hidden: number }
  stats: { average: number; approved: number }
  products: { id: number; name: string }[]
}

const URL = '/admin/ulasan'
const ALL = 'all'

const statusBadge: Record<ReviewStatus, { label: string; className: string }> = {
  pending: { label: 'Menunggu', className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400' },
  approved: { label: 'Tampil', className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' },
  hidden: { label: 'Disembunyikan', className: 'bg-muted text-muted-foreground' },
}

const formatDate = (iso: string) =>
  new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })

export default function Index({ reviews, filters, counts, stats, products }: Props) {
  const [q, setQ] = useState(filters.q)
  const [deleting, setDeleting] = useState<ReviewRow | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)

  const [selected, setSelected] = useState<number[]>([])
  const [confirmBulkDelete, setConfirmBulkDelete] = useState(false)
  const [bulkBusy, setBulkBusy] = useState(false)

  // Hanya pertahankan pilihan yang masih terlihat (pindah halaman, filter, atau data berubah).
  useEffect(() => {
    const visible = new Set(reviews.data.map((r) => r.id))
    setSelected((prev) => {
      const kept = prev.filter((id) => visible.has(id))
      return kept.length === prev.length ? prev : kept
    })
  }, [reviews.data])

  const pageIds = reviews.data.map((r) => r.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id))
  const someSelected = selected.length > 0 && !allSelected
  const toggleAll = (checked: boolean) => setSelected(checked ? pageIds : [])
  const toggleOne = (id: number, checked: boolean) =>
    setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)))

  const selectedRows = reviews.data.filter((r) => selected.includes(r.id))
  const canShow = selectedRows.some((r) => r.status !== 'approved')
  const canHide = selectedRows.some((r) => r.status !== 'hidden')

  const setStatus = (review: ReviewRow, status: 'approved' | 'hidden') => {
    setBusyId(review.id)
    router.patch(`${URL}/${review.id}/status`, { status }, {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setBusyId(null),
    })
  }

  const bulkStatus = (status: 'approved' | 'hidden') => {
    setBulkBusy(true)
    router.patch(`${URL}/massal/status`, { ids: selected, status }, {
      preserveScroll: true,
      onSuccess: () => setSelected([]),
      onFinish: () => setBulkBusy(false),
    })
  }

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

  const firstRun = useRef(true)

  const visit = (patch: Partial<Props['filters']>) => {
    const next = { ...filters, q, ...patch }
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v && v !== ALL))
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
    { value: 'pending', label: 'Menunggu', count: counts.pending },
    { value: 'approved', label: 'Tampil', count: counts.approved },
    { value: 'hidden', label: 'Disembunyikan', count: counts.hidden },
  ]

  return (
    <>
      <Head title='Ulasan' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Ulasan</h1>
          <p className='text-sm text-muted-foreground'>
            Moderasi ulasan pembeli. Hanya ulasan berstatus Tampil yang muncul di halaman produk dan dihitung ke rating.
          </p>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
          <StatCard label='Total ulasan' value={formatNumber(counts.all)} />
          <StatCard label='Menunggu persetujuan' value={formatNumber(counts.pending)} />
          <StatCard label='Tampil di toko' value={formatNumber(stats.approved)} />
          <StatCard label='Rata-rata rating' value={stats.approved ? String(stats.average).replace('.', ',') : '-'} />
        </div>

        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
          <div className='flex flex-col gap-2 sm:flex-row'>
            <div className='relative w-full sm:w-72'>
              <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
              <Input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder='Cari nama, ulasan, atau produk'
                aria-label='Cari ulasan'
                className='ps-9'
              />
            </div>
            <Select value={filters.product} onValueChange={(v) => visit({ product: v })}>
              <SelectTrigger className='w-full sm:w-52' aria-label='Filter produk'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Semua produk</SelectItem>
                {products.map((p) => (
                  <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                ))}
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
                    aria-label='Pilih semua ulasan di halaman ini'
                    disabled={pageIds.length === 0}
                  />
                </TableHead>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Ulasan</TableHead>
                <TableHead className='hidden md:table-cell'>Produk</TableHead>
                <TableHead>Rating</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='hidden lg:table-cell'>Tanggal</TableHead>
                <TableHead className='w-28'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {reviews.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={8} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.status !== ALL || filters.product !== ALL
                      ? 'Tidak ada ulasan yang cocok.'
                      : 'Belum ada ulasan. Ulasan muncul di sini setelah pembeli menulisnya.'}
                  </TableCell>
                </TableRow>
              )}
              {reviews.data.map((review, index) => (
                <TableRow key={review.id} data-state={selected.includes(review.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={selected.includes(review.id)}
                      onCheckedChange={(v) => toggleOne(review.id, v === true)}
                      aria-label={`Pilih ulasan dari ${review.reviewer_name}`}
                    />
                  </TableCell>
                  <TableCell className='text-muted-foreground'>{(reviews.from ?? 1) + index}</TableCell>
                  <TableCell className='max-w-96'>
                    <div className='flex items-center gap-1.5'>
                      <span className='truncate font-medium'>{review.reviewer_name}</span>
                      {review.verified && (
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <ShieldCheck className='size-3.5 shrink-0 text-emerald-600' aria-label='Pembeli terverifikasi' />
                          </TooltipTrigger>
                          <TooltipContent><p>Terkait pesanan</p></TooltipContent>
                        </Tooltip>
                      )}
                    </div>
                    <p className='line-clamp-2 text-xs text-muted-foreground' title={review.body}>{review.body}</p>
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap md:table-cell'>{review.product.name}</TableCell>
                  <TableCell>
                    <span className='inline-flex items-center gap-1 font-medium tabular-nums'>
                      {review.rating}
                      <Star className='size-3.5 fill-amber-400 text-amber-400' aria-hidden='true' />
                    </span>
                  </TableCell>
                  <TableCell>
                    <Badge variant='secondary' className={statusBadge[review.status].className}>
                      {statusBadge[review.status].label}
                    </Badge>
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap text-sm lg:table-cell'>{formatDate(review.created_at)}</TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      {review.status !== 'approved' && (
                        <Button
                          type='button'
                          variant='outline'
                          size='icon'
                          className='size-8'
                          disabled={busyId === review.id}
                          aria-label={`Tampilkan ulasan dari ${review.reviewer_name}`}
                          title='Tampilkan di toko'
                          onClick={() => setStatus(review, 'approved')}
                        >
                          <Eye />
                        </Button>
                      )}
                      {review.status !== 'hidden' && (
                        <Button
                          type='button'
                          variant='outline'
                          size='icon'
                          className='size-8'
                          disabled={busyId === review.id}
                          aria-label={`Sembunyikan ulasan dari ${review.reviewer_name}`}
                          title='Sembunyikan dari toko'
                          onClick={() => setStatus(review, 'hidden')}
                        >
                          <EyeOff />
                        </Button>
                      )}
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                        aria-label={`Hapus ulasan dari ${review.reviewer_name}`}
                        title='Hapus'
                        onClick={() => setDeleting(review)}
                      >
                        <Trash2 />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        <div className='mt-4 flex flex-wrap items-center justify-between gap-2'>
          <p className='text-sm text-muted-foreground'>
            {reviews.total > 0 ? `Menampilkan ${reviews.from}-${reviews.to} dari ${reviews.total} ulasan` : 'Tidak ada ulasan'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!reviews.prev_page_url} onClick={() => reviews.prev_page_url && router.get(reviews.prev_page_url, {}, { preserveScroll: true })}>
              Sebelumnya
            </Button>
            <Button variant='outline' size='sm' disabled={!reviews.next_page_url} onClick={() => reviews.next_page_url && router.get(reviews.next_page_url, {}, { preserveScroll: true })}>
              Berikutnya
            </Button>
          </div>
        </div>
      </Main>

      <BulkActionsBar count={selected.length} entityName='ulasan' onClear={() => setSelected([])}>
        {canShow && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type='button' variant='outline' size='sm' className='h-8 gap-1.5' disabled={bulkBusy} onClick={() => bulkStatus('approved')} aria-label='Tampilkan ulasan terpilih'>
                <Eye className='size-4' /> Tampilkan
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>Tampilkan ulasan terpilih di toko</p></TooltipContent>
          </Tooltip>
        )}
        {canHide && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type='button' variant='outline' size='sm' className='h-8 gap-1.5' disabled={bulkBusy} onClick={() => bulkStatus('hidden')} aria-label='Sembunyikan ulasan terpilih'>
                <EyeOff className='size-4' /> Sembunyikan
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>Sembunyikan ulasan terpilih dari toko</p></TooltipContent>
          </Tooltip>
        )}
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type='button'
              variant='outline'
              size='icon'
              className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
              disabled={bulkBusy}
              onClick={() => setConfirmBulkDelete(true)}
              aria-label='Hapus ulasan terpilih'
            >
              <Trash2 />
            </Button>
          </TooltipTrigger>
          <TooltipContent><p>Hapus ulasan terpilih</p></TooltipContent>
        </Tooltip>
      </BulkActionsBar>

      <ConfirmDialog
        open={confirmBulkDelete}
        onOpenChange={(open) => !open && setConfirmBulkDelete(false)}
        title={`Hapus ${selected.length} ulasan?`}
        desc='Ulasan terpilih dihapus permanen dan tidak lagi dihitung ke rating. Kalau hanya ingin menyembunyikan, gunakan Sembunyikan.'
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={bulkDelete}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus ulasan?'
        desc={`Ulasan dari ${deleting?.reviewer_name ?? ''} dihapus permanen dan tidak lagi dihitung ke rating. Kalau hanya ingin menyembunyikan, gunakan Sembunyikan.`}
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
