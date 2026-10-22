import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Check, Copy, PackageCheck, Search } from 'lucide-react'
import { toast } from 'sonner'
import { copyText } from '@/lib/clipboard'
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
import { formatDateTime, formatDay } from '@/features/orders/types'

type Item = {
  id: number
  in_workshop: boolean
  account: {
    email: string | null
    password: string | null
    profile: string | null
    pin: string | null
    status: string
    status_label: string
    expires_at: string | null
    product_name: string
  } | null
  reason: string | null
  note: string | null
  order_code: string
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
  items: Paginated<Item>
  filters: { q: string; status: string }
  counts: { all: number; workshop: number; out: number }
}

const URL = '/admin/bengkel'

const tabs = [
  { value: 'all', label: 'Semua', key: 'all' },
  { value: 'workshop', label: 'Di bengkel', key: 'workshop' },
  { value: 'out', label: 'Sudah keluar', key: 'out' },
] as const

const accountText = (i: Item) =>
  [
    `Akun ${i.account?.product_name ?? ''}`,
    '',
    `Email : ${i.account?.email || '-'}`,
    `Password : ${i.account?.password || '-'}`,
    `Profil : ${i.account?.profile || '-'}`,
    `Pin : ${i.account?.pin || '-'}`,
  ].join('\n')

export default function Index({ items, filters, counts }: Props) {
  const [q, setQ] = useState(filters.q)
  const [restocking, setRestocking] = useState<Item | null>(null)
  const [copied, setCopied] = useState<number | null>(null)
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

  const copy = async (item: Item) => {
    if (await copyText(accountText(item))) {
      setCopied(item.id)
      toast.success('Akun disalin')
      setTimeout(() => setCopied((c) => (c === item.id ? null : c)), 1500)
    }
  }

  return (
    <>
      <Head title='Bengkel' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Bengkel</h1>
          <p className='text-sm text-muted-foreground'>
            Akun rusak yang sudah diganti lewat klaim garansi. Perbaiki akunnya, lalu kembalikan ke stok bila masih layak jual.
          </p>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-3'>
          <StatCard label='Di bengkel' value={formatNumber(counts.workshop)} />
          <StatCard label='Sudah keluar' value={formatNumber(counts.out)} />
          <StatCard label='Total pernah masuk' value={formatNumber(counts.all)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
          <div className='relative w-full sm:w-72'>
            <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder='Cari email, profil, atau kode pesanan' aria-label='Cari akun bengkel' className='ps-9' />
          </div>
          <Tabs value={filters.status} onValueChange={(v) => visit({ status: v })} className='shrink-0'>
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
                <TableHead>Akun</TableHead>
                <TableHead className='hidden md:table-cell'>Alasan</TableHead>
                <TableHead className='hidden lg:table-cell'>Pesanan</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='w-28'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.status !== 'all' ? 'Tidak ada akun yang cocok.' : 'Bengkel kosong. Akun muncul di sini setelah diganti lewat klaim garansi.'}
                  </TableCell>
                </TableRow>
              )}
              {items.data.map((item, index) => (
                <TableRow key={item.id}>
                  <TableCell className='text-muted-foreground'>{(items.from ?? 1) + index}</TableCell>
                  <TableCell className='max-w-72'>
                    <p className='truncate text-[13px]' title={item.account?.email ?? ''}>{item.account?.email ?? '(akun dihapus)'}</p>
                    <p className='text-xs text-muted-foreground'>
                      {item.account?.product_name}{item.account?.profile ? ` | ${item.account.profile}` : ''}
                      {item.account?.expires_at ? ` | mati ${formatDay(item.account.expires_at)}` : ''}
                    </p>
                  </TableCell>
                  <TableCell className='hidden max-w-60 md:table-cell'>
                    <p>{item.reason ?? '-'}</p>
                    {item.note && <p className='line-clamp-2 text-xs text-muted-foreground' title={item.note}>{item.note}</p>}
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap lg:table-cell'>
                    <p>{item.order_code}</p>
                    <p className='text-xs text-muted-foreground'>{item.replaced_at ? formatDateTime(item.replaced_at) : '-'}</p>
                  </TableCell>
                  <TableCell>
                    <Badge variant='secondary' className={item.in_workshop ? 'bg-destructive/15 text-destructive' : 'bg-muted text-muted-foreground'}>
                      {item.in_workshop ? 'Di bengkel' : (item.account?.status_label ?? 'Keluar')}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      {item.account && (
                        <Button type='button' variant='outline' size='icon' className='size-8' title='Salin akun' aria-label={`Salin akun ${item.account.email ?? ''}`} onClick={() => copy(item)}>
                          {copied === item.id ? <Check className='text-emerald-500' /> : <Copy />}
                        </Button>
                      )}
                      {item.in_workshop && (
                        <Button type='button' variant='outline' size='icon' className='size-8 text-emerald-600 hover:bg-emerald-500/10 hover:text-emerald-600' title='Kembalikan ke stok' aria-label={`Kembalikan akun ${item.account?.email ?? ''} ke stok`} onClick={() => setRestocking(item)}>
                          <PackageCheck />
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
            {items.total > 0 ? `Menampilkan ${items.from}-${items.to} dari ${items.total} akun` : 'Tidak ada akun'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!items.prev_page_url} onClick={() => items.prev_page_url && router.get(items.prev_page_url, {}, { preserveScroll: true })}>Sebelumnya</Button>
            <Button variant='outline' size='sm' disabled={!items.next_page_url} onClick={() => items.next_page_url && router.get(items.next_page_url, {}, { preserveScroll: true })}>Berikutnya</Button>
          </div>
        </div>
      </Main>

      <ConfirmDialog
        open={Boolean(restocking)}
        onOpenChange={(open) => !open && setRestocking(null)}
        title='Kembalikan ke stok?'
        desc={`Akun ${restocking?.account?.email ?? ''} kembali berstatus Tersedia dan bisa dijual lagi. Pastikan sudah diperbaiki.`}
        confirmText='Kembalikan ke stok'
        cancelBtnText='Batal'
        handleConfirm={() => {
          if (!restocking) return
          router.patch(`${URL}/${restocking.id}/stok`, {}, { preserveScroll: true, onFinish: () => setRestocking(null) })
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
