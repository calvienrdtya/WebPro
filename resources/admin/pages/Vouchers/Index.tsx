import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Pencil, Plus, Power, PowerOff, Search, Trash2 } from 'lucide-react'
import { formatNumber, formatRupiah } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
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
import { formatDate } from '@/features/accounts/dates'
import { VoucherFormDialog } from '@/features/vouchers/voucher-form'
import { type ProductOption, type VoucherRow, type VoucherStatus } from '@/features/vouchers/types'

type Paginated<T> = {
  data: T[]
  total: number
  from: number | null
  to: number | null
  prev_page_url: string | null
  next_page_url: string | null
}

type Props = {
  vouchers: Paginated<VoucherRow>
  filters: { q: string; status: string }
  counts: Record<'all' | VoucherStatus, number>
  stats: { total: number; running: number; claims: number; used: number }
  products: ProductOption[]
}

const URL = '/admin/voucher'
const ALL = 'all'
const VISIBLE_PRODUCTS = 2

const statusBadge: Record<VoucherStatus, { label: string; className: string }> = {
  running: { label: 'Berjalan', className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' },
  scheduled: { label: 'Terjadwal', className: 'bg-blue-500/15 text-blue-700 dark:text-blue-400' },
  ended: { label: 'Berakhir', className: 'bg-muted text-muted-foreground' },
  inactive: { label: 'Nonaktif', className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400' },
}

const discountLabel = (v: VoucherRow) =>
  v.discount_type === 'percent' ? `${v.discount_value}%` : formatRupiah(v.discount_value)

const periodLabel = (v: VoucherRow) => {
  if (!v.starts_on && !v.ends_on) return 'Tanpa batas waktu'
  if (v.starts_on && v.ends_on) return `${formatDate(v.starts_on)} - ${formatDate(v.ends_on)}`
  return v.ends_on ? `Hingga ${formatDate(v.ends_on)}` : `Mulai ${formatDate(v.starts_on!)}`
}

export default function Index({ vouchers, filters, counts, stats, products }: Props) {
  const [q, setQ] = useState(filters.q)
  // null = tertutup; { voucher: null } = tambah baru; { voucher } = ubah.
  const [form, setForm] = useState<{ voucher: VoucherRow | null } | null>(null)
  const [deleting, setDeleting] = useState<VoucherRow | null>(null)
  const [toggling, setToggling] = useState<number | null>(null)

  const [selected, setSelected] = useState<number[]>([])
  const [confirmBulkDelete, setConfirmBulkDelete] = useState(false)
  const [bulkBusy, setBulkBusy] = useState(false)

  // Hanya pertahankan pilihan yang masih terlihat (pindah halaman, filter, atau data berubah).
  useEffect(() => {
    const visible = new Set(vouchers.data.map((v) => v.id))
    setSelected((prev) => {
      const kept = prev.filter((id) => visible.has(id))
      return kept.length === prev.length ? prev : kept
    })
  }, [vouchers.data])

  const pageIds = vouchers.data.map((v) => v.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id))
  const someSelected = selected.length > 0 && !allSelected
  const toggleAll = (checked: boolean) => setSelected(checked ? pageIds : [])
  const toggleOne = (id: number, checked: boolean) =>
    setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)))

  const selectedRows = vouchers.data.filter((v) => selected.includes(v.id))
  const canActivate = selectedRows.some((v) => !v.is_active)
  const canDeactivate = selectedRows.some((v) => v.is_active)

  const bulkActive = (active: boolean) => {
    setBulkBusy(true)
    router.patch(`${URL}/massal/aktif`, { ids: selected, active }, {
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

  const tabs: { value: string; label: string; count: number }[] = [
    { value: ALL, label: 'Semua', count: counts.all },
    { value: 'running', label: 'Berjalan', count: counts.running },
    { value: 'scheduled', label: 'Terjadwal', count: counts.scheduled },
    { value: 'ended', label: 'Berakhir', count: counts.ended },
    { value: 'inactive', label: 'Nonaktif', count: counts.inactive },
  ]

  return (
    <>
      <Head title='Voucher' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-center justify-between gap-3'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>Voucher</h1>
            <p className='text-sm text-muted-foreground'>
              Potongan harga produk berkode yang bisa diklaim pelanggan di halaman voucher.
            </p>
          </div>
          <Button onClick={() => setForm({ voucher: null })}>
            <Plus /> Tambah Voucher
          </Button>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
          <StatCard label='Total voucher' value={formatNumber(stats.total)} />
          <StatCard label='Sedang berjalan' value={formatNumber(stats.running)} />
          <StatCard label='Total klaim' value={formatNumber(stats.claims)} />
          <StatCard label='Total terpakai' value={formatNumber(stats.used)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
          <div className='relative w-full sm:w-72'>
            <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder='Cari kode atau judul'
              aria-label='Cari voucher'
              className='ps-9'
            />
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
                    aria-label='Pilih semua voucher di halaman ini'
                    disabled={pageIds.length === 0}
                  />
                </TableHead>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Voucher</TableHead>
                <TableHead>Potongan</TableHead>
                <TableHead className='hidden lg:table-cell'>Berlaku</TableHead>
                <TableHead className='hidden md:table-cell'>Pemakaian</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Aktif</TableHead>
                <TableHead className='w-24'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {vouchers.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={9} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.status !== ALL
                      ? 'Tidak ada voucher yang cocok.'
                      : 'Belum ada voucher. Klik "Tambah Voucher" untuk memulai.'}
                  </TableCell>
                </TableRow>
              )}
              {vouchers.data.map((voucher, index) => (
                <TableRow key={voucher.id} data-state={selected.includes(voucher.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={selected.includes(voucher.id)}
                      onCheckedChange={(v) => toggleOne(voucher.id, v === true)}
                      aria-label={`Pilih voucher ${voucher.code}`}
                    />
                  </TableCell>
                  <TableCell className='text-muted-foreground'>{(vouchers.from ?? 1) + index}</TableCell>
                  <TableCell className='max-w-64'>
                    <button
                      type='button'
                      className='block max-w-full truncate text-start font-medium hover:underline'
                      title='Ubah voucher'
                      onClick={() => setForm({ voucher })}
                    >
                      {voucher.code}
                    </button>
                    <p className='truncate text-xs text-muted-foreground'>{voucher.title}</p>
                    <div className='mt-1 flex flex-wrap gap-1'>
                      <Badge variant='outline' className='text-[10px] font-normal'>
                        {voucher.requires_claim ? 'Perlu klaim' : 'Kode langsung'}
                      </Badge>
                      {!voucher.is_listed && (
                        <Badge variant='outline' className='text-[10px] font-normal'>Tidak ditampilkan</Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className='whitespace-nowrap'>
                    <p className='font-medium tabular-nums'>{discountLabel(voucher)}</p>
                    <p className='text-xs text-muted-foreground'>
                      {voucher.min_purchase ? `Min. ${formatRupiah(voucher.min_purchase)}` : 'Tanpa minimal'}
                      {voucher.max_discount ? ` | Maks. ${formatRupiah(voucher.max_discount)}` : ''}
                    </p>
                    <ProductScope voucher={voucher} />
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap text-sm lg:table-cell'>{periodLabel(voucher)}</TableCell>
                  <TableCell className='hidden whitespace-nowrap text-sm tabular-nums md:table-cell'>
                    <p>
                      {formatNumber(voucher.used_count)}
                      {voucher.quota !== null ? ` / ${formatNumber(voucher.quota)}` : ''} terpakai
                    </p>
                    <p className='text-xs text-muted-foreground'>{formatNumber(voucher.claims_count)} diklaim</p>
                  </TableCell>
                  <TableCell>
                    <Badge variant='secondary' className={statusBadge[voucher.status].className}>
                      {statusBadge[voucher.status].label}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Switch
                      checked={voucher.is_active}
                      disabled={toggling === voucher.id}
                      aria-label={`${voucher.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${voucher.code}`}
                      onCheckedChange={() => {
                        setToggling(voucher.id)
                        router.patch(`${URL}/${voucher.id}/aktif`, {}, {
                          preserveScroll: true,
                          preserveState: true,
                          onFinish: () => setToggling(null),
                        })
                      }}
                    />
                  </TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8'
                        aria-label={`Ubah voucher ${voucher.code}`}
                        title='Ubah'
                        onClick={() => setForm({ voucher })}
                      >
                        <Pencil />
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                        aria-label={`Hapus voucher ${voucher.code}`}
                        title='Hapus'
                        onClick={() => setDeleting(voucher)}
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
            {vouchers.total > 0
              ? `Menampilkan ${vouchers.from}-${vouchers.to} dari ${vouchers.total} voucher`
              : 'Tidak ada voucher'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!vouchers.prev_page_url} onClick={() => vouchers.prev_page_url && router.get(vouchers.prev_page_url, {}, { preserveScroll: true })}>
              Sebelumnya
            </Button>
            <Button variant='outline' size='sm' disabled={!vouchers.next_page_url} onClick={() => vouchers.next_page_url && router.get(vouchers.next_page_url, {}, { preserveScroll: true })}>
              Berikutnya
            </Button>
          </div>
        </div>
      </Main>

      <VoucherFormDialog
        open={form !== null}
        onOpenChange={(open) => !open && setForm(null)}
        products={products}
        voucher={form?.voucher}
      />

      <BulkActionsBar count={selected.length} entityName='voucher' onClear={() => setSelected([])}>
        {canActivate && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type='button' variant='outline' size='sm' className='h-8 gap-1.5' disabled={bulkBusy} onClick={() => bulkActive(true)} aria-label='Aktifkan voucher terpilih'>
                <Power className='size-4' /> Aktifkan
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>Aktifkan voucher terpilih</p></TooltipContent>
          </Tooltip>
        )}
        {canDeactivate && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type='button' variant='outline' size='sm' className='h-8 gap-1.5' disabled={bulkBusy} onClick={() => bulkActive(false)} aria-label='Nonaktifkan voucher terpilih'>
                <PowerOff className='size-4' /> Nonaktifkan
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>Nonaktifkan voucher terpilih</p></TooltipContent>
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
              aria-label='Hapus voucher terpilih'
            >
              <Trash2 />
            </Button>
          </TooltipTrigger>
          <TooltipContent><p>Hapus voucher terpilih</p></TooltipContent>
        </Tooltip>
      </BulkActionsBar>

      <ConfirmDialog
        open={confirmBulkDelete}
        onOpenChange={(open) => !open && setConfirmBulkDelete(false)}
        title={`Hapus ${selected.length} voucher?`}
        desc='Voucher terpilih dihapus permanen beserta klaim pelanggan. Kalau hanya ingin menghentikan sementara, gunakan Nonaktifkan.'
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={bulkDelete}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus voucher?'
        desc={`Voucher ${deleting?.code ?? ''} dihapus permanen beserta ${deleting?.claims_count ?? 0} klaim pelanggan. Kalau hanya ingin menghentikan sementara, gunakan Nonaktifkan.`}
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

function ProductScope({ voucher }: { voucher: VoucherRow }) {
  if (voucher.applies_to_all) return <p className='text-xs text-muted-foreground'>Semua produk</p>
  const names = voucher.products.map((p) => p.name)
  const rest = names.length - VISIBLE_PRODUCTS
  return (
    <p className='max-w-48 truncate text-xs text-muted-foreground' title={names.join(', ')}>
      {names.slice(0, VISIBLE_PRODUCTS).join(', ')}
      {rest > 0 ? ` +${rest} lainnya` : ''}
    </p>
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
