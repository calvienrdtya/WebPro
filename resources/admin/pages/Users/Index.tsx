import { useEffect, useRef, useState } from 'react'
import { Head, router, Link } from '@inertiajs/react'
import { Ban, History, Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react'
import { formatNumber, formatRupiah } from '@/lib/format'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
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
import { formatDate } from '@/features/accounts/dates'
import { UserFormDialog } from '@/features/users/user-form'
import { type UserRow } from '@/features/users/types'

type Paginated<T> = {
  data: T[]
  total: number
  from: number | null
  to: number | null
  prev_page_url: string | null
  next_page_url: string | null
}

type Filters = { q: string; status: string; method: string }

type Props = {
  users: Paginated<UserRow>
  filters: Filters
  counts: { all: number; active: number; blocked: number }
  stats: { total: number; new_30_days: number; google: number; email: number }
}

const URL = '/admin/user'
const ALL = 'all'

const methodLabel: Record<string, string> = { google: 'Google', email: 'Email' }

const initials = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('')

export default function Index({ users, filters, counts, stats }: Props) {
  const [q, setQ] = useState(filters.q)
  // null = tertutup; { user: null } = tambah baru; { user } = ubah.
  const [form, setForm] = useState<{ user: UserRow | null } | null>(null)
  const [blocking, setBlocking] = useState<UserRow | null>(null)
  const [deleting, setDeleting] = useState<UserRow | null>(null)

  // Pilihan massal: id user yang tercentang di halaman ini.
  const [selected, setSelected] = useState<number[]>([])
  const [confirmBulk, setConfirmBulk] = useState<'block' | 'delete' | null>(null)
  const [bulkBusy, setBulkBusy] = useState(false)

  // Hanya pertahankan pilihan yang masih terlihat (pindah halaman, filter, atau data berubah).
  useEffect(() => {
    const visible = new Set(users.data.map((c) => c.id))
    setSelected((prev) => {
      const kept = prev.filter((id) => visible.has(id))
      return kept.length === prev.length ? prev : kept
    })
  }, [users.data])

  const pageIds = users.data.map((c) => c.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id))
  const someSelected = selected.length > 0 && !allSelected
  const toggleAll = (checked: boolean) => setSelected(checked ? pageIds : [])
  const toggleOne = (id: number, checked: boolean) =>
    setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)))

  const selectedRows = users.data.filter((c) => selected.includes(c.id))
  const canBlock = selectedRows.some((c) => !c.is_blocked)
  const canUnblock = selectedRows.some((c) => c.is_blocked)

  const bulkBlock = (blocked: boolean) => {
    setBulkBusy(true)
    router.patch(`${URL}/massal/blokir`, { ids: selected, blocked }, {
      preserveScroll: true,
      onSuccess: () => setSelected([]),
      onFinish: () => {
        setBulkBusy(false)
        setConfirmBulk(null)
      },
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
        setConfirmBulk(null)
      },
    })
  }
  const firstRun = useRef(true)

  const visit = (patch: Partial<Filters>) => {
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
    { value: 'active', label: 'Aktif', count: counts.active },
    { value: 'blocked', label: 'Diblokir', count: counts.blocked },
  ]
  const hasFilters = Boolean(filters.q || filters.method !== ALL)

  return (
    <>
      <Head title='User' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-center justify-between gap-3'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>User</h1>
            <p className='text-sm text-muted-foreground'>
              Pengguna yang mendaftar di toko. User yang diblokir tidak bisa login.
            </p>
          </div>
          <Button onClick={() => setForm({ user: null })}>
            <Plus /> Tambah User
          </Button>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
          <StatCard label='Total user' value={formatNumber(stats.total)} />
          <StatCard label='Baru 30 hari terakhir' value={formatNumber(stats.new_30_days)} />
          <StatCard label='Mendaftar dengan Google' value={formatNumber(stats.google)} />
          <StatCard label='Mendaftar dengan email' value={formatNumber(stats.email)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
          <div className='flex flex-col gap-2 sm:flex-row'>
            <div className='relative w-full sm:w-72'>
              <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
              <Input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder='Cari nama, email, atau telepon'
                aria-label='Cari user'
                className='ps-9'
              />
            </div>
            <Select value={filters.method} onValueChange={(v) => visit({ method: v })}>
              <SelectTrigger className='w-full sm:w-44' aria-label='Filter metode daftar'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Semua metode</SelectItem>
                <SelectItem value='google'>Google</SelectItem>
                <SelectItem value='email'>Email dan sandi</SelectItem>
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
                    aria-label='Pilih semua user di halaman ini'
                    disabled={pageIds.length === 0}
                  />
                </TableHead>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>User</TableHead>
                <TableHead className='hidden md:table-cell'>Telepon</TableHead>
                <TableHead className='hidden sm:table-cell'>Metode daftar</TableHead>
                <TableHead className='hidden text-end md:table-cell'>Pesanan</TableHead>
                <TableHead className='hidden text-end md:table-cell'>Total belanja</TableHead>
                <TableHead className='hidden lg:table-cell'>Terdaftar</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='w-32'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {users.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={10} className='h-32 text-center text-muted-foreground'>
                    {hasFilters || filters.status !== ALL
                      ? 'Tidak ada user yang cocok.'
                      : 'Belum ada user. User muncul di sini setelah mendaftar.'}
                  </TableCell>
                </TableRow>
              )}
              {users.data.map((user, index) => (
                <TableRow key={user.id} data-state={selected.includes(user.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={selected.includes(user.id)}
                      onCheckedChange={(v) => toggleOne(user.id, v === true)}
                      aria-label={`Pilih user ${user.name}`}
                    />
                  </TableCell>
                  <TableCell className='text-muted-foreground'>{(users.from ?? 1) + index}</TableCell>
                  <TableCell>
                    <div className='flex items-center gap-3'>
                      <Avatar className='size-9'>
                        {user.avatar && <AvatarImage src={user.avatar} alt='' referrerPolicy='no-referrer' />}
                        <AvatarFallback className='text-xs'>{initials(user.name)}</AvatarFallback>
                      </Avatar>
                      <div className='min-w-0'>
                        <button
                          type='button'
                          className='block max-w-56 truncate text-start font-medium hover:underline'
                          title='Ubah user'
                          onClick={() => setForm({ user })}
                        >
                          {user.name}
                        </button>
                        <p className='max-w-56 truncate text-xs text-muted-foreground'>{user.email}</p>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap tabular-nums md:table-cell'>{user.phone || '-'}</TableCell>
                  <TableCell className='hidden sm:table-cell'>
                    {user.methods.length === 0 ? (
                      <span className='text-sm text-muted-foreground'>Belum ada</span>
                    ) : (
                      <div className='flex flex-wrap gap-1'>
                        {user.methods.map((m) => (
                          <Badge key={m} variant='secondary'>{methodLabel[m]}</Badge>
                        ))}
                      </div>
                    )}
                  </TableCell>
                  <TableCell className='hidden text-end tabular-nums md:table-cell'>{formatNumber(user.paid_orders)}</TableCell>
                  <TableCell className='hidden whitespace-nowrap text-end tabular-nums md:table-cell'>{user.total_spent > 0 ? formatRupiah(user.total_spent) : '-'}</TableCell>
                  <TableCell className='hidden whitespace-nowrap lg:table-cell'>{formatDate(user.created_at)}</TableCell>
                  <TableCell>
                    {user.is_blocked ? (
                      <Badge variant='secondary' className='bg-destructive/15 text-destructive'>Diblokir</Badge>
                    ) : (
                      <Badge variant='secondary' className='bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'>Aktif</Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button variant='outline' size='icon' className='size-8' asChild>
                        <Link href={`/admin/log-aktivitas?user=${user.id}`} aria-label={`Riwayat aktivitas ${user.name}`} title='Riwayat aktivitas'>
                          <History />
                        </Link>
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8'
                        aria-label={`Ubah user ${user.name}`}
                        title='Ubah'
                        onClick={() => setForm({ user })}
                      >
                        <Pencil />
                      </Button>
                      {user.is_blocked ? (
                        <Button
                          type='button'
                          variant='outline'
                          size='icon'
                          className='size-8'
                          aria-label={`Buka blokir ${user.name}`}
                          title='Buka blokir'
                          onClick={() => router.patch(`${URL}/${user.id}/blokir`, {}, { preserveScroll: true })}
                        >
                          <RotateCcw />
                        </Button>
                      ) : (
                        <Button
                          type='button'
                          variant='outline'
                          size='icon'
                          className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                          aria-label={`Blokir ${user.name}`}
                          title='Blokir'
                          onClick={() => setBlocking(user)}
                        >
                          <Ban />
                        </Button>
                      )}
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                        aria-label={`Hapus user ${user.name}`}
                        title='Hapus'
                        onClick={() => setDeleting(user)}
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
            {users.total > 0
              ? `Menampilkan ${users.from}-${users.to} dari ${users.total} user`
              : 'Tidak ada user'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!users.prev_page_url} onClick={() => users.prev_page_url && router.get(users.prev_page_url, {}, { preserveScroll: true })}>
              Sebelumnya
            </Button>
            <Button variant='outline' size='sm' disabled={!users.next_page_url} onClick={() => users.next_page_url && router.get(users.next_page_url, {}, { preserveScroll: true })}>
              Berikutnya
            </Button>
          </div>
        </div>
      </Main>

      <UserFormDialog
        open={form !== null}
        onOpenChange={(open) => !open && setForm(null)}
        user={form?.user}
      />

      <BulkActionsBar count={selected.length} entityName='user' onClear={() => setSelected([])}>
        {canBlock && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type='button'
                variant='outline'
                size='sm'
                className='h-8 gap-1.5'
                disabled={bulkBusy}
                onClick={() => setConfirmBulk('block')}
                aria-label='Blokir user terpilih'
              >
                <Ban className='size-4' /> Blokir
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              <p>Blokir user terpilih</p>
            </TooltipContent>
          </Tooltip>
        )}
        {canUnblock && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type='button'
                variant='outline'
                size='sm'
                className='h-8 gap-1.5'
                disabled={bulkBusy}
                onClick={() => bulkBlock(false)}
                aria-label='Buka blokir user terpilih'
              >
                <RotateCcw className='size-4' /> Buka blokir
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              <p>Buka blokir user terpilih</p>
            </TooltipContent>
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
              onClick={() => setConfirmBulk('delete')}
              aria-label='Hapus user terpilih'
            >
              <Trash2 />
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>Hapus user terpilih</p>
          </TooltipContent>
        </Tooltip>
      </BulkActionsBar>

      <ConfirmDialog
        open={confirmBulk === 'block'}
        onOpenChange={(open) => !open && setConfirmBulk(null)}
        title={`Blokir ${selectedRows.filter((c) => !c.is_blocked).length} user?`}
        desc='User terpilih tidak akan bisa login lagi dan sesi mereka yang sedang aktif langsung berakhir. Riwayat pesanan tetap tersimpan, dan blokir bisa dibuka kapan saja.'
        confirmText='Blokir'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => bulkBlock(true)}
      />

      <ConfirmDialog
        open={confirmBulk === 'delete'}
        onOpenChange={(open) => !open && setConfirmBulk(null)}
        title={`Hapus ${selected.length} user?`}
        desc='User terpilih akan dihapus permanen beserta akses loginnya. Tindakan ini tidak bisa dibatalkan. Kalau hanya ingin menghentikan akses sementara, gunakan Blokir.'
        confirmText='Hapus permanen'
        cancelBtnText='Batal'
        destructive
        handleConfirm={bulkDelete}
      />

      <ConfirmDialog
        open={Boolean(blocking)}
        onOpenChange={(open) => !open && setBlocking(null)}
        title='Blokir user?'
        desc={`"${blocking?.name ?? ''}" tidak akan bisa login lagi dan sesinya yang sedang aktif langsung berakhir. Riwayat pesanannya tetap tersimpan, dan blokir bisa dibuka kapan saja.`}
        confirmText='Blokir'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => {
          if (!blocking) return
          router.patch(`${URL}/${blocking.id}/blokir`, {}, { preserveScroll: true, onFinish: () => setBlocking(null) })
        }}
      />
      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus user?'
        desc={`"${deleting?.name ?? ''}" (${deleting?.email ?? ''}) akan dihapus permanen beserta akses loginnya. Tindakan ini tidak bisa dibatalkan. Kalau hanya ingin menghentikan akses sementara, gunakan Blokir.`}
        confirmText='Hapus permanen'
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
