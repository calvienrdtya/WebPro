import { useEffect, useRef, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { toast } from 'sonner'
import { copyText } from '@/lib/clipboard'
import { diffDays, formatDate, formatShortDate, localToday } from '@/features/accounts/dates'
import { formatNumber, formatRupiah } from '@/lib/format'
import { Check, ChevronUp, Copy, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { BulkActionsBar } from '@/components/bulk-actions-bar'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { AccountFormDialog } from '@/features/accounts/account-form'
import { type AccountRow, type StatusOption } from '@/features/accounts/types'
import { type Option } from '@/features/products/types'

type Paginated<T> = {
  data: T[]
  total: number
  from: number | null
  to: number | null
  prev_page_url: string | null
  next_page_url: string | null
}

type Filters = { q: string; status: string }

type Props = {
  product: { id: number; name: string; slug: string } | null
  accounts: Paginated<AccountRow>
  filters: Filters
  counts: Record<string, number>
  statuses: StatusOption[]
  products: Option[]
  defaults: { active_days: number }
  stats: {
    stock_count: number
    total_count: number
    stock_cost: number
    average_cost: number
    sold_cost: number
    missing_cost: number
  }
}

const ALL = 'all'
const TAB_ORDER = ['available', 'sold', 'problem', 'expired']

const statusStyle: Record<string, string> = {
  available: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
  sold: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
  problem: 'bg-destructive/15 text-destructive',
  expired: 'bg-muted text-muted-foreground',
}

/** Sisa hari sampai kedaluwarsa (0 bila hari ini atau sudah lewat). */
const daysLeft = (expires: string, today: string) => Math.max(0, diffDays(today, expires))

// Hijau 18 hari ke atas, kuning 11-17, merah 10 ke bawah.
const leftColor = (left: number) =>
  left >= 18
    ? 'text-green-600 dark:text-green-400'
    : left >= 11
      ? 'text-yellow-600 dark:text-yellow-400'
      : 'text-red-600 dark:text-red-400'

const accountText = (a: AccountRow) =>
  [
    `Akun ${a.product_name}`,
    '',
    `Email : ${a.email || '-'}`,
    `Password : ${a.password || '-'}`,
    `Profil : ${a.profile || '-'}`,
    `Pin : ${a.pin || '-'}`,
  ].join('\n')

export default function Index({ product, accounts, filters, counts, statuses, products, stats, defaults }: Props) {
  const base = product ? `/admin/akun/${product.slug}` : '/admin/akun'
  const [q, setQ] = useState(filters.q)
  const [deleting, setDeleting] = useState<AccountRow | null>(null)
  // null = tertutup; { account: null } = tambah baru; { account } = ubah.
  const [form, setForm] = useState<{ account: AccountRow | null } | null>(null)
  const [copied, setCopied] = useState<number | null>(null)

  // Pilihan massal: id akun yang tercentang di halaman ini.
  const [selected, setSelected] = useState<number[]>([])
  const [confirmBulkDelete, setConfirmBulkDelete] = useState(false)
  const [soldDialog, setSoldDialog] = useState(false)
  const [buyer, setBuyer] = useState('')
  const [bulkBusy, setBulkBusy] = useState(false)

  // Hanya pertahankan pilihan yang masih terlihat (pindah halaman, filter, atau data berubah).
  useEffect(() => {
    const visible = new Set(accounts.data.map((a) => a.id))
    setSelected((prev) => {
      const kept = prev.filter((id) => visible.has(id))
      return kept.length === prev.length ? prev : kept
    })
  }, [accounts.data])

  const pageIds = accounts.data.map((a) => a.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id))
  const someSelected = selected.length > 0 && !allSelected
  const toggleAll = (checked: boolean) => setSelected(checked ? pageIds : [])
  const toggleOne = (id: number, checked: boolean) =>
    setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)))

  const selectedAccounts = accounts.data.filter((a) => selected.includes(a.id))

  const bulkCopy = async () => {
    const text = selectedAccounts.map(accountText).join('\n\n')
    if (await copyText(text)) toast.success(`${selectedAccounts.length} akun disalin`)
    else toast.error('Gagal menyalin. Salin satu per satu dari tabel.')
  }

  const bulkStatus = (status: string, soldTo?: string) => {
    setBulkBusy(true)
    router.patch(
      '/admin/akun/massal/status',
      { ids: selected, status, sold_to: soldTo || null },
      {
        preserveScroll: true,
        onSuccess: () => {
          setSelected([])
          setSoldDialog(false)
          setBuyer('')
        },
        onFinish: () => setBulkBusy(false),
      }
    )
  }

  const bulkDelete = () => {
    setBulkBusy(true)
    router.delete('/admin/akun/massal', {
      data: { ids: selected },
      preserveScroll: true,
      onSuccess: () => setSelected([]),
      onFinish: () => {
        setBulkBusy(false)
        setConfirmBulkDelete(false)
      },
    })
  }

  const copyAccount = async (account: AccountRow) => {
    if (await copyText(accountText(account))) {
      setCopied(account.id)
      toast.success(`Akun ${account.product_name} disalin`)
      setTimeout(() => setCopied((id) => (id === account.id ? null : id)), 2000)
    } else {
      toast.error('Gagal menyalin. Salin manual dari form ubah.')
    }
  }
  const firstRun = useRef(true)

  const visit = (patch: Partial<Filters>) => {
    const next = { ...filters, q, ...patch }
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v))
    router.get(base, params, { preserveState: true, replace: true })
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

  const title = product ? `Stok ${product.name}` : 'Semua Stok Akun'
  const hasFilters = Boolean(filters.q)
  const showBuyer = filters.status === 'sold' || filters.status === ALL
  const tabs = [
    ...TAB_ORDER.map((v) => statuses.find((s) => s.value === v)).filter((s): s is Option => Boolean(s)),
    { value: ALL, label: 'Semua' },
  ]
  const countOf = (v: string) =>
    v === ALL ? Object.values(counts).reduce((a, b) => a + b, 0) : (counts[v] ?? 0)

  const labelOf = (value: string) => statuses.find((s) => s.value === value)?.label ?? value
  const today = localToday()

  return (
    <>
      <Head title={title} />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-center justify-between gap-3'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>{title}</h1>
            <p className='text-sm text-muted-foreground'>
              Kelola stok, lihat akun yang sudah terjual dan pembelinya.
            </p>
          </div>
          <Button onClick={() => setForm({ account: null })}>
            <Plus /> Tambah Akun
          </Button>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
          <StatCard
            label='Jumlah stok akun'
            value={`${formatNumber(stats.stock_count)} akun`}
            note={`dari ${formatNumber(stats.total_count)} akun (semua status)`}
            muted
          />
          <StatCard
            label='Modal di stok tersedia'
            value={formatRupiah(stats.stock_cost)}
            note={stats.missing_cost > 0 ? `${stats.missing_cost} akun tersedia belum diisi HPP` : undefined}
          />
          <StatCard label='Rata-rata HPP per akun' value={formatRupiah(stats.average_cost)} />
          <StatCard label='HPP akun terjual' value={formatRupiah(stats.sold_cost)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
          <div className='relative w-full sm:w-72'>
            <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder='Cari email, profil, atau catatan'
              aria-label='Cari akun'
              className='ps-9'
            />
          </div>
          <Tabs value={filters.status} onValueChange={(v) => visit({ status: v })} className='shrink-0'>
            <TabsList className='h-auto flex-wrap justify-start'>
              {tabs.map((t) => (
                <TabsTrigger key={t.value} value={t.value} className='gap-1.5'>
                  {t.label}
                  <span className='rounded-full bg-muted px-1.5 text-xs tabular-nums text-muted-foreground'>
                    {countOf(t.value)}
                  </span>
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
                    aria-label='Pilih semua akun di halaman ini'
                    disabled={pageIds.length === 0}
                  />
                </TableHead>
                <TableHead className='w-12'>No</TableHead>
                {!product && <TableHead>Produk</TableHead>}
                <TableHead>Email / Link</TableHead>
                <TableHead>Password</TableHead>
                <TableHead className='hidden sm:table-cell'>Profil</TableHead>
                <TableHead className='hidden sm:table-cell'>PIN</TableHead>
                <TableHead className='hidden lg:table-cell'>HPP</TableHead>
                <TableHead className='hidden lg:table-cell'>Masa aktif</TableHead>
                {showBuyer && <TableHead>Terjual ke</TableHead>}
                <TableHead className='hidden md:table-cell'>Kedaluwarsa</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='w-24'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {accounts.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={(product ? 11 : 12) + (showBuyer ? 1 : 0)} className='h-32 text-center text-muted-foreground'>
                    {hasFilters
                      ? 'Tidak ada akun yang cocok dengan pencarian.'
                      : filters.status === ALL
                        ? 'Belum ada akun. Klik "Tambah Akun" untuk memulai.'
                        : `Tidak ada akun ${labelOf(filters.status).toLowerCase()}.`}
                  </TableCell>
                </TableRow>
              )}
              {accounts.data.map((account, index) => (
                <TableRow key={account.id} data-state={selected.includes(account.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={selected.includes(account.id)}
                      onCheckedChange={(v) => toggleOne(account.id, v === true)}
                      aria-label={`Pilih akun ${account.email ?? account.id}`}
                    />
                  </TableCell>
                  <TableCell className='text-muted-foreground'>{(accounts.from ?? 1) + index}</TableCell>
                  {!product && (
                    <TableCell>
                      <Link href={`/admin/akun/${account.product_slug}`} className='font-medium hover:underline'>
                        {account.product_name}
                      </Link>
                    </TableCell>
                  )}
                  <TableCell className='max-w-56'>
                    <div className='flex items-center gap-1'>
                      <Button
                        type='button'
                        variant='ghost'
                        size='icon'
                        className='size-7 shrink-0 text-muted-foreground'
                        aria-label={`Salin akun ${account.email ?? account.id}`}
                        title='Salin email, password, profil, dan PIN'
                        onClick={() => copyAccount(account)}
                      >
                        {copied === account.id ? <Check className='text-emerald-500' /> : <Copy />}
                      </Button>
                      <button
                        type='button'
                        onClick={() => setForm({ account })}
                        className='truncate text-start hover:underline'
                        title='Ubah akun'
                      >
                        {account.email ?? '-'}
                      </button>
                    </div>
                    {account.note && (
                      <p className='truncate ps-8 text-xs text-muted-foreground'>{account.note}</p>
                    )}
                  </TableCell>
                  <TableCell className='max-w-40 select-all truncate text-sm'>
                    {account.password || '-'}
                  </TableCell>
                  <TableCell className='hidden sm:table-cell'>{account.profile || '-'}</TableCell>
                  <TableCell className='hidden select-all text-sm sm:table-cell'>{account.pin || '-'}</TableCell>
                  <TableCell className='hidden whitespace-nowrap tabular-nums lg:table-cell'>
                    {account.cogs === null ? '-' : formatRupiah(account.cogs)}
                  </TableCell>
                  {(() => {
                    const left = account.expires_at ? daysLeft(account.expires_at, today) : null
                    return (
                      <TableCell
                        className={`hidden whitespace-nowrap font-semibold tabular-nums lg:table-cell ${left === null ? '' : leftColor(left)}`}
                        title={left === null ? undefined : `Sisa ${left} hari dari masa aktif ${account.active_days} hari`}
                      >
                        {left === null ? '-' : `${left}d`}
                      </TableCell>
                    )
                  })()}
                  {showBuyer && (
                    <TableCell>
                      {account.sold_to || account.sold_at ? (
                        <>
                          <p className='max-w-36 truncate' title={account.sold_to ?? undefined}>{account.sold_to || '-'}</p>
                          {account.sold_at && (
                            <p className='text-xs text-muted-foreground'>{formatDate(account.sold_at)}</p>
                          )}
                        </>
                      ) : (
                        '-'
                      )}
                    </TableCell>
                  )}
                  <TableCell className='hidden md:table-cell'>
                    {account.expires_at ? (
                      <>
                        <p className={account.expires_at < today ? 'text-destructive' : undefined}>
                          {formatDate(account.expires_at)}
                        </p>
                        <p className='whitespace-nowrap text-xs text-muted-foreground'>
                          {account.started_at && `Mulai ${formatShortDate(account.started_at)} · `}
                          {account.active_days} hari
                        </p>
                      </>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>
                    <Badge variant='secondary' className={statusStyle[account.status]}>
                      {labelOf(account.status)}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8'
                        aria-label={`Ubah akun ${account.email ?? account.id}`}
                        title='Ubah'
                        onClick={() => setForm({ account })}
                      >
                        <Pencil />
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                        aria-label={`Hapus akun ${account.email ?? account.id}`}
                        title='Hapus'
                        onClick={() => setDeleting(account)}
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
            {accounts.total > 0
              ? `Menampilkan ${accounts.from}-${accounts.to} dari ${accounts.total} akun`
              : 'Tidak ada akun'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' asChild={Boolean(accounts.prev_page_url)} disabled={!accounts.prev_page_url}>
              {accounts.prev_page_url ? <Link href={accounts.prev_page_url} preserveScroll>Sebelumnya</Link> : <span>Sebelumnya</span>}
            </Button>
            <Button variant='outline' size='sm' asChild={Boolean(accounts.next_page_url)} disabled={!accounts.next_page_url}>
              {accounts.next_page_url ? <Link href={accounts.next_page_url} preserveScroll>Berikutnya</Link> : <span>Berikutnya</span>}
            </Button>
          </div>
        </div>
      </Main>

      <AccountFormDialog
        open={form !== null}
        onOpenChange={(open) => !open && setForm(null)}
        options={{ products, statuses }}
        account={form?.account}
        defaultProductId={product?.id}
        defaultActiveDays={defaults.active_days}
      />

      <BulkActionsBar count={selected.length} entityName='akun' onClear={() => setSelected([])}>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button type='button' variant='outline' size='icon' className='size-8' onClick={bulkCopy} aria-label='Salin akun terpilih'>
              <Copy />
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>Salin akun terpilih</p>
          </TooltipContent>
        </Tooltip>

        <DropdownMenu>
          <Tooltip>
            <TooltipTrigger asChild>
              <DropdownMenuTrigger asChild>
                <Button type='button' variant='outline' size='sm' className='h-8 gap-1.5' disabled={bulkBusy} aria-label='Ubah status akun terpilih'>
                  Ubah status <ChevronUp className='size-3.5' />
                </Button>
              </DropdownMenuTrigger>
            </TooltipTrigger>
            <TooltipContent>
              <p>Ubah status akun terpilih</p>
            </TooltipContent>
          </Tooltip>
          <DropdownMenuContent side='top' align='center' sideOffset={8}>
            {statuses.map((s) => (
              <DropdownMenuItem
                key={s.value}
                onClick={() => (s.value === 'sold' ? setSoldDialog(true) : bulkStatus(s.value))}
              >
                {s.label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type='button'
              variant='outline'
              size='icon'
              className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
              onClick={() => setConfirmBulkDelete(true)}
              aria-label='Hapus akun terpilih'
            >
              <Trash2 />
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>Hapus akun terpilih</p>
          </TooltipContent>
        </Tooltip>
      </BulkActionsBar>

      <Dialog open={soldDialog} onOpenChange={(open) => !open && setSoldDialog(false)}>
        <DialogContent>
          <form
            className='space-y-4'
            onSubmit={(e) => {
              e.preventDefault()
              bulkStatus('sold', buyer.trim())
            }}
          >
            <DialogHeader>
              <DialogTitle>Tandai {selected.length} akun terjual</DialogTitle>
              <DialogDescription>
                Isi nama atau kontak pembeli untuk semua akun terpilih (boleh dikosongkan; akun yang sudah punya pembeli tidak dikosongkan).
              </DialogDescription>
            </DialogHeader>
            <Input
              value={buyer}
              onChange={(e) => setBuyer(e.target.value)}
              placeholder='Nama / nomor WhatsApp pembeli'
              aria-label='Terjual ke'
              autoFocus
            />
            <DialogFooter>
              <Button type='button' variant='outline' onClick={() => setSoldDialog(false)}>Batal</Button>
              <Button type='submit' disabled={bulkBusy}>{bulkBusy ? 'Menyimpan...' : 'Tandai terjual'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={confirmBulkDelete}
        onOpenChange={setConfirmBulkDelete}
        title={`Hapus ${selected.length} akun?`}
        desc='Akun terpilih akan dihapus dari stok. Tindakan ini tidak bisa dibatalkan dari panel.'
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={bulkDelete}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus akun?'
        desc={`Akun "${deleting?.email ?? deleting?.id ?? ''}" akan dihapus dari stok.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => {
          if (!deleting) return
          router.delete(`/admin/akun/${deleting.id}`, { onFinish: () => setDeleting(null) })
        }}
      />
    </>
  )
}

function StatCard({
  label,
  value,
  note,
  muted,
}: {
  label: string
  value: string
  note?: string
  /** Catatan informasi biasa, bukan peringatan. */
  muted?: boolean
}) {
  return (
    <div className='rounded-lg border bg-card px-4 py-3'>
      <p className='text-xs text-muted-foreground'>{label}</p>
      <p className='mt-1 text-xl font-semibold tabular-nums'>{value}</p>
      {note && (
        <p className={`mt-1 text-xs ${muted ? 'text-muted-foreground' : 'text-amber-600 dark:text-amber-500'}`}>{note}</p>
      )}
    </div>
  )
}
