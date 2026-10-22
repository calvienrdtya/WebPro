import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Check, Eye, RotateCcw, Search, X } from 'lucide-react'
import { formatNumber, formatRupiah } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { OrderDetailDialog, RejectOrderDialog, StatusBadge } from '@/features/orders/order-dialogs'
import { formatDateTime, type OrderRow } from '@/features/orders/types'

type Paginated<T> = {
  data: T[]
  total: number
  from: number | null
  to: number | null
  prev_page_url: string | null
  next_page_url: string | null
}

type Props = {
  orders: Paginated<OrderRow>
  filters: { q: string; status: string }
  counts: Record<string, number>
  stats: { to_verify: number; unpaid: number; today: number; overdue: number; month_revenue: number }
}

const URL = '/admin/pesanan'
const REFRESH_MS = 30_000

const tabs = [
  { value: 'all', label: 'Semua' },
  { value: 'queue', label: 'Perlu diverifikasi' },
  { value: 'unpaid', label: 'Menunggu bayar' },
  { value: 'paid', label: 'Lunas' },
  { value: 'failed', label: 'Gagal' },
]

export default function Index({ orders, filters, counts, stats }: Props) {
  const [q, setQ] = useState(filters.q)
  const [detailId, setDetailId] = useState<number | null>(null)
  const [confirming, setConfirming] = useState<OrderRow | null>(null)
  const [rejecting, setRejecting] = useState<OrderRow | null>(null)
  const [restoring, setRestoring] = useState<OrderRow | null>(null)
  const [busy, setBusy] = useState(false)

  // Detail dibaca dari data terbaru agar ikut menyegarkan saat daftar dimuat ulang.
  const detail = orders.data.find((o) => o.id === detailId) ?? null

  // Antrean diperbarui otomatis tiap 30 detik selama tab terlihat.
  useEffect(() => {
    const timer = setInterval(() => {
      if (document.visibilityState === 'visible') {
        router.reload({ only: ['orders', 'counts', 'stats'] })
      }
    }, REFRESH_MS)
    return () => clearInterval(timer)
  }, [])

  const firstRun = useRef(true)

  const visit = (patch: Partial<Props['filters']>) => {
    const next = { ...filters, q, ...patch }
    const params = Object.fromEntries(Object.entries(next).filter(([k, v]) => v && !(k === 'status' && v === 'all')))
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

  const act = (method: 'patch', path: string, done: () => void) => {
    setBusy(true)
    router[method](path, {}, { preserveScroll: true, onFinish: () => { setBusy(false); done() } })
  }

  const canDecide = (o: OrderRow) => o.status === 'verifying' || o.status === 'pending'

  return (
    <>
      <Head title='Pesanan' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Pesanan</h1>
          <p className='text-sm text-muted-foreground'>
            Cocokkan pembayaran QRIS dengan mutasi, lalu konfirmasi agar akun terkirim. Daftar diperbarui otomatis tiap 30 detik.
          </p>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5'>
          <StatCard label='Perlu diverifikasi' value={formatNumber(stats.to_verify)} />
          <StatCard label='Menunggu bayar' value={formatNumber(stats.unpaid)} />
          <StatCard label='Terkonfirmasi hari ini' value={formatNumber(stats.today)} />
          <button
            type='button'
            onClick={() => visit({ status: 'overdue' })}
            title='Tampilkan pesanan dengan kiriman bulanan yang tertunda'
            className={`rounded-lg border px-4 py-3 text-start transition hover:border-amber-500/60 ${stats.overdue > 0 ? 'border-amber-500/50 bg-amber-500/5' : 'bg-card'}`}
          >
            <p className='text-xs text-muted-foreground'>Kiriman tertunda</p>
            <p className={`mt-1 text-xl font-semibold tabular-nums ${stats.overdue > 0 ? 'text-amber-600' : ''}`}>{formatNumber(stats.overdue)}</p>
          </button>
          <StatCard label='Pendapatan bulan ini' value={formatRupiah(stats.month_revenue)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between'>
          <div className='relative w-full lg:w-72'>
            <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder='Cari kode, nama, email, atau produk'
              aria-label='Cari pesanan'
              className='ps-9'
            />
          </div>
          <Tabs value={filters.status} onValueChange={(v) => visit({ status: v })} className='min-w-0'>
            <TabsList className='h-auto flex-wrap justify-start'>
              {tabs.map((t) => (
                <TabsTrigger key={t.value} value={t.value} className='gap-1.5'>
                  {t.label}
                  <span className='rounded-full bg-muted px-1.5 text-xs tabular-nums text-muted-foreground'>{counts[t.value] ?? 0}</span>
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>
        </div>

        {filters.status === 'overdue' && (
          <div className='mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm'>
            <span>Menampilkan pesanan dengan kiriman bulanan yang sudah jatuh tempo tetapi belum terkirim (biasanya stok kurang). Tambah stok, lalu kirim dari detail pesanan atau tunggu pengecekan otomatis tiap jam.</span>
            <Button type='button' variant='outline' size='sm' onClick={() => visit({ status: 'all' })}>Lihat semua</Button>
          </div>
        )}

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Invoice</TableHead>
                <TableHead className='hidden md:table-cell'>Email</TableHead>
                <TableHead className='hidden xl:table-cell'>WhatsApp</TableHead>
                <TableHead>Produk</TableHead>
                <TableHead>Bulan</TableHead>
                <TableHead>Total</TableHead>
                <TableHead className='hidden lg:table-cell'>Waktu order</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='w-36'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {orders.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={10} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.status !== 'all'
                      ? 'Tidak ada pesanan yang cocok.'
                      : 'Belum ada pesanan.'}
                  </TableCell>
                </TableRow>
              )}
              {orders.data.map((o, index) => (
                <TableRow key={o.id}>
                  <TableCell className='text-muted-foreground'>{(orders.from ?? 1) + index}</TableCell>
                  <TableCell className='whitespace-nowrap'>
                    <button
                      type='button'
                      onClick={() => setDetailId(o.id)}
                      title='Buka detail pesanan'
                      className='text-[13px] text-blue-600 underline-offset-2 transition hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 dark:text-sky-400'
                    >
                      {o.code}
                    </button>
                  </TableCell>
                  <TableCell className='hidden max-w-56 md:table-cell'>
                    <p className='truncate text-sm' title={o.customer_email}>{o.customer_email}</p>
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap xl:table-cell'>
                    <a href={waLink(o.customer_phone)} target='_blank' rel='noreferrer' className='text-sm hover:underline' title='Buka WhatsApp'>
                      {o.customer_phone}
                    </a>
                  </TableCell>
                  <TableCell className='max-w-44'>
                    <p className='truncate' title={o.product_name}>{o.product_name}</p>
                  </TableCell>
                  <TableCell><MonthsBadge months={o.months} /></TableCell>
                  <TableCell className='whitespace-nowrap font-medium tabular-nums'>{formatRupiah(o.total)}</TableCell>
                  <TableCell className='hidden whitespace-nowrap text-sm lg:table-cell'>{formatDateTime(o.created_at)}</TableCell>
                  <TableCell><StatusBadge order={o} /></TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button type='button' variant='outline' size='icon' className='size-8' title='Detail' aria-label={`Detail pesanan ${o.code}`} onClick={() => setDetailId(o.id)}>
                        <Eye />
                      </Button>
                      {canDecide(o) && (
                        <>
                          <Button
                            type='button'
                            variant='outline'
                            size='icon'
                            className='size-8 text-emerald-600 hover:bg-emerald-500/10 hover:text-emerald-600'
                            title='Konfirmasi pembayaran'
                            aria-label={`Konfirmasi pesanan ${o.code}`}
                            onClick={() => setConfirming(o)}
                          >
                            <Check />
                          </Button>
                          <Button
                            type='button'
                            variant='outline'
                            size='icon'
                            className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                            title='Tolak'
                            aria-label={`Tolak pesanan ${o.code}`}
                            onClick={() => setRejecting(o)}
                          >
                            <X />
                          </Button>
                        </>
                      )}
                      {o.status === 'expired' && (
                        <Button type='button' variant='outline' size='icon' className='size-8' title='Pulihkan pesanan' aria-label={`Pulihkan pesanan ${o.code}`} onClick={() => setRestoring(o)}>
                          <RotateCcw />
                        </Button>
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
            {orders.total > 0 ? `Menampilkan ${orders.from}-${orders.to} dari ${orders.total} pesanan` : 'Tidak ada pesanan'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!orders.prev_page_url} onClick={() => orders.prev_page_url && router.get(orders.prev_page_url, {}, { preserveScroll: true })}>
              Sebelumnya
            </Button>
            <Button variant='outline' size='sm' disabled={!orders.next_page_url} onClick={() => orders.next_page_url && router.get(orders.next_page_url, {}, { preserveScroll: true })}>
              Berikutnya
            </Button>
          </div>
        </div>
      </Main>

      <OrderDetailDialog order={detail} onClose={() => setDetailId(null)} />
      <RejectOrderDialog order={rejecting} onClose={() => setRejecting(null)} />

      <ConfirmDialog
        open={Boolean(confirming)}
        onOpenChange={(open) => !open && setConfirming(null)}
        title={`Konfirmasi ${confirming?.code ?? ''}?`}
        desc={`Pastikan pembayaran ${confirming ? formatRupiah(confirming.total) : ''} dari ${confirming?.customer_name ?? ''} sudah masuk. Akun diambil otomatis dari stok dan langsung dikirim ke ${confirming?.customer_email ?? ''}. Bila stok habis, pembayaran tetap tercatat dan akun dikirim begitu stok ditambah.`}
        confirmText='Konfirmasi dan kirim akun'
        cancelBtnText='Batal'
        isLoading={busy}
        handleConfirm={() => confirming && act('patch', `${URL}/${confirming.id}/konfirmasi`, () => setConfirming(null))}
      />

      <ConfirmDialog
        open={Boolean(restoring)}
        onOpenChange={(open) => !open && setRestoring(null)}
        title={`Pulihkan ${restoring?.code ?? ''}?`}
        desc='Satu akun direservasi lagi (selama stok ada) dan pesanan masuk antrean verifikasi. Gunakan hanya bila pelanggan terbukti sudah membayar.'
        confirmText='Pulihkan'
        cancelBtnText='Batal'
        isLoading={busy}
        handleConfirm={() => restoring && act('patch', `${URL}/${restoring.id}/pulihkan`, () => setRestoring(null))}
      />
    </>
  )
}

/** Nomor lokal (08...) diubah ke format internasional untuk tautan wa.me. */
const waLink = (phone: string) => {
  const digits = phone.replace(/\D/g, '')
  return `https://wa.me/${digits.startsWith('0') ? `62${digits.slice(1)}` : digits}`
}

// Warna badge per lama langganan; lebih dari 3 bulan memakai warna terakhir.
const monthsTone: Record<number, string> = {
  1: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
  2: 'bg-violet-500/15 text-violet-700 dark:text-violet-400',
  3: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
}

function MonthsBadge({ months }: { months: number }) {
  return (
    <Badge variant='secondary' className={`whitespace-nowrap ${monthsTone[months] ?? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'}`}>
      {months} bulan
    </Badge>
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
