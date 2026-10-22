import { useEffect, useRef, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { ArrowLeftRight, ArrowRight, Ban, ExternalLink, Search } from 'lucide-react'
import { formatNumber } from '@/lib/format'
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
import { ReplaceAccountDialog } from '@/features/orders/replace-account-dialog'
import { formatDateTime } from '@/features/orders/types'

type ClaimStatus = 'open' | 'done' | 'cancelled'

type ClaimRow = {
  id: number
  status: ClaimStatus
  source: 'report' | 'order'
  reason: string | null
  note: string | null
  outside_warranty: boolean
  mode: 'stock' | 'replacement'
  order: { id: number; code: string; customer_name: string; customer_email: string; product_name: string; warranty_name: string | null }
  report: { id: number; subject: string } | null
  old_account: { email: string | null; profile: string | null } | null
  new_account: { email: string | null; profile: string | null } | null
  handled_by: string | null
  created_at: string
  replaced_at: string | null
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
  claims: Paginated<ClaimRow>
  filters: { q: string; status: string }
  counts: { all: number; open: number; done: number; cancelled: number }
  stats: { total: number; waiting: number; this_month: number }
}

const URL = '/admin/klaim-garansi'

const tabs = [
  { value: 'all', label: 'Semua', key: 'all' },
  { value: 'open', label: 'Menunggu penggantian', key: 'open' },
  { value: 'done', label: 'Selesai', key: 'done' },
  { value: 'cancelled', label: 'Dibatalkan', key: 'cancelled' },
] as const

const statusBadge: Record<ClaimStatus, { label: string; className: string }> = {
  open: { label: 'Menunggu', className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400' },
  done: { label: 'Selesai', className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' },
  cancelled: { label: 'Dibatalkan', className: 'bg-muted text-muted-foreground' },
}

const accountLabel = (a: { email: string | null; profile: string | null } | null) =>
  a ? `${a.email ?? '-'}${a.profile ? ` | ${a.profile}` : ''}` : '-'

export default function Index({ claims, filters, counts, stats }: Props) {
  const [q, setQ] = useState(filters.q)
  const [replacing, setReplacing] = useState<number | null>(null)
  const [cancelling, setCancelling] = useState<ClaimRow | null>(null)
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

  return (
    <>
      <Head title='Klaim Garansi' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Klaim Garansi</h1>
          <p className='text-sm text-muted-foreground'>
            Catatan pelanggan yang mengklaim garansi. Klaim dari Laporan menunggu penggantian akun di sini; penggantian langsung dari halaman Pesanan juga tercatat.
          </p>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-3'>
          <StatCard label='Menunggu penggantian' value={formatNumber(stats.waiting)} />
          <StatCard label='Total klaim selesai' value={formatNumber(stats.total)} />
          <StatCard label='Klaim bulan ini' value={formatNumber(stats.this_month)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between'>
          <div className='relative w-full lg:w-72'>
            <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder='Cari kode, pelanggan, atau produk' aria-label='Cari klaim' className='ps-9' />
          </div>
          <Tabs value={filters.status} onValueChange={(v) => visit({ status: v })} className='min-w-0'>
            <TabsList className='h-auto flex-wrap justify-start'>
              {tabs.map((t) => (
                <TabsTrigger key={t.value} value={t.value} className='gap-1.5'>
                  {t.label}
                  <span className='rounded-full bg-muted px-1.5 text-xs tabular-nums text-muted-foreground'>{counts[t.key]}</span>
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>
        </div>

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Klaim</TableHead>
                <TableHead className='hidden md:table-cell'>Akun</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='hidden lg:table-cell'>Tanggal</TableHead>
                <TableHead className='w-32'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {claims.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.status !== 'all' ? 'Tidak ada klaim yang cocok.' : 'Belum ada klaim garansi.'}
                  </TableCell>
                </TableRow>
              )}
              {claims.data.map((c, index) => (
                <TableRow key={c.id}>
                  <TableCell className='text-muted-foreground'>{(claims.from ?? 1) + index}</TableCell>
                  <TableCell className='max-w-72'>
                    <p className='font-medium'>{c.order.code}</p>
                    <p className='truncate text-xs text-muted-foreground' title={c.order.customer_email}>{c.order.customer_name} | {c.order.product_name}</p>
                    <p className='truncate text-xs text-muted-foreground'>
                      {c.reason ?? '-'}{c.status === 'done' ? (c.mode === 'replacement' ? ' | replacement' : ' | dari stok') : ''} | {c.source === 'report' ? `dari Laporan${c.report ? `: ${c.report.subject}` : ''}` : 'dari Pesanan'}
                    </p>
                  </TableCell>
                  <TableCell className='hidden max-w-72 md:table-cell'>
                    {c.status === 'done' ? (
                      <div className='space-y-0.5 text-xs'>
                        <p className='truncate text-muted-foreground line-through' title={accountLabel(c.old_account)}>{accountLabel(c.old_account)}</p>
                        <p className='flex items-center gap-1 truncate' title={accountLabel(c.new_account)}>
                          <ArrowRight className='size-3 shrink-0 text-amber-500' /> {accountLabel(c.new_account)}
                        </p>
                      </div>
                    ) : (
                      <span className='text-xs text-muted-foreground'>-</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <div className='flex flex-col items-start gap-1'>
                      <Badge variant='secondary' className={statusBadge[c.status].className}>{statusBadge[c.status].label}</Badge>
                      {c.outside_warranty && <span className='text-[11px] font-medium text-amber-600'>di luar garansi</span>}
                    </div>
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap text-sm lg:table-cell'>{formatDateTime(c.replaced_at ?? c.created_at)}</TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button asChild variant='outline' size='icon' className='size-8' title='Lihat pesanan' aria-label={`Lihat pesanan ${c.order.code}`}>
                        <Link href={`/admin/pesanan?status=all&q=${c.order.code}`}><ExternalLink /></Link>
                      </Button>
                      {c.status === 'open' && (
                        <>
                          <Button type='button' variant='outline' size='icon' className='size-8 text-emerald-600 hover:bg-emerald-500/10 hover:text-emerald-600' title='Ganti akun' aria-label={`Ganti akun pesanan ${c.order.code}`} onClick={() => setReplacing(c.order.id)}>
                            <ArrowLeftRight />
                          </Button>
                          <Button type='button' variant='outline' size='icon' className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive' title='Batalkan klaim' aria-label={`Batalkan klaim pesanan ${c.order.code}`} onClick={() => setCancelling(c)}>
                            <Ban />
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
            {claims.total > 0 ? `Menampilkan ${claims.from}-${claims.to} dari ${claims.total} klaim` : 'Tidak ada klaim'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!claims.prev_page_url} onClick={() => claims.prev_page_url && router.get(claims.prev_page_url, {}, { preserveScroll: true })}>Sebelumnya</Button>
            <Button variant='outline' size='sm' disabled={!claims.next_page_url} onClick={() => claims.next_page_url && router.get(claims.next_page_url, {}, { preserveScroll: true })}>Berikutnya</Button>
          </div>
        </div>
      </Main>

      <ReplaceAccountDialog orderId={replacing} onClose={() => setReplacing(null)} />

      <ConfirmDialog
        open={Boolean(cancelling)}
        onOpenChange={(open) => !open && setCancelling(null)}
        title='Batalkan klaim?'
        desc={`Klaim untuk pesanan ${cancelling?.order.code ?? ''} dibatalkan tanpa mengganti akun. Laporan asalnya tetap menunggu.`}
        confirmText='Batalkan klaim'
        cancelBtnText='Kembali'
        destructive
        handleConfirm={() => {
          if (!cancelling) return
          router.patch(`${URL}/${cancelling.id}/batal`, {}, { preserveScroll: true, onFinish: () => setCancelling(null) })
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
