import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { ArrowDown, ArrowUp, Pencil, Plus, Power, PowerOff, Search, Trash2 } from 'lucide-react'
import { formatNumber } from '@/lib/format'
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
import { FaqFormDialog } from '@/features/faqs/faq-form'
import { type FaqRow, type Option } from '@/features/faqs/types'

type Paginated<T> = {
  data: T[]
  total: number
  from: number | null
  to: number | null
  prev_page_url: string | null
  next_page_url: string | null
}

type Props = {
  faqs: Paginated<FaqRow>
  filters: { q: string; category: string }
  counts: Record<string, number>
  stats: { total: number; active: number; home: number; product: number }
  categories: Option[]
}

const URL = '/admin/faq'
const ALL = 'all'

export default function Index({ faqs, filters, counts, stats, categories }: Props) {
  const [q, setQ] = useState(filters.q)
  // null = tertutup; { faq: null } = tambah baru; { faq } = ubah.
  const [form, setForm] = useState<{ faq: FaqRow | null } | null>(null)
  const [deleting, setDeleting] = useState<FaqRow | null>(null)
  const [busyId, setBusyId] = useState<number | null>(null)

  const [selected, setSelected] = useState<number[]>([])
  const [confirmBulkDelete, setConfirmBulkDelete] = useState(false)
  const [bulkBusy, setBulkBusy] = useState(false)

  // Hanya pertahankan pilihan yang masih terlihat (pindah halaman, filter, atau data berubah).
  useEffect(() => {
    const visible = new Set(faqs.data.map((f) => f.id))
    setSelected((prev) => {
      const kept = prev.filter((id) => visible.has(id))
      return kept.length === prev.length ? prev : kept
    })
  }, [faqs.data])

  const pageIds = faqs.data.map((f) => f.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id))
  const someSelected = selected.length > 0 && !allSelected
  const toggleAll = (checked: boolean) => setSelected(checked ? pageIds : [])
  const toggleOne = (id: number, checked: boolean) =>
    setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)))

  const selectedRows = faqs.data.filter((f) => selected.includes(f.id))
  const canActivate = selectedRows.some((f) => !f.is_active)
  const canDeactivate = selectedRows.some((f) => f.is_active)

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

  const move = (faq: FaqRow, direction: 'up' | 'down') => {
    setBusyId(faq.id)
    router.patch(`${URL}/${faq.id}/urut`, { direction }, {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setBusyId(null),
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

  const tabs = [{ value: ALL, label: 'Semua' }, ...categories.map((c) => ({ value: c.value, label: c.label }))]

  return (
    <>
      <Head title='FAQ' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-center justify-between gap-3'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>FAQ</h1>
            <p className='text-sm text-muted-foreground'>
              Pertanyaan umum di toko. Urutan di sini sama dengan urutan tampil.
            </p>
          </div>
          <Button onClick={() => setForm({ faq: null })}>
            <Plus /> Tambah FAQ
          </Button>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
          <StatCard label='Total FAQ' value={formatNumber(stats.total)} />
          <StatCard label='Aktif' value={formatNumber(stats.active)} />
          <StatCard label='Tampil di beranda' value={formatNumber(stats.home)} />
          <StatCard label='Tampil di halaman produk' value={formatNumber(stats.product)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'>
          <div className='relative w-full sm:w-72'>
            <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder='Cari pertanyaan atau jawaban'
              aria-label='Cari FAQ'
              className='ps-9'
            />
          </div>
          <Tabs value={filters.category} onValueChange={(v) => visit({ category: v })} className='shrink-0'>
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

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className='w-10'>
                  <Checkbox
                    checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                    onCheckedChange={(v) => toggleAll(v === true)}
                    aria-label='Pilih semua FAQ di halaman ini'
                    disabled={pageIds.length === 0}
                  />
                </TableHead>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Pertanyaan</TableHead>
                <TableHead className='hidden sm:table-cell'>Kategori</TableHead>
                <TableHead className='hidden md:table-cell'>Tampil di</TableHead>
                <TableHead>Aktif</TableHead>
                <TableHead className='w-44'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {faqs.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={7} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.category !== ALL
                      ? 'Tidak ada FAQ yang cocok.'
                      : 'Belum ada FAQ. Klik "Tambah FAQ" untuk memulai.'}
                  </TableCell>
                </TableRow>
              )}
              {faqs.data.map((faq, index) => (
                <TableRow key={faq.id} data-state={selected.includes(faq.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={selected.includes(faq.id)}
                      onCheckedChange={(v) => toggleOne(faq.id, v === true)}
                      aria-label={`Pilih FAQ ${faq.question}`}
                    />
                  </TableCell>
                  <TableCell className='text-muted-foreground'>{(faqs.from ?? 1) + index}</TableCell>
                  <TableCell className='max-w-96'>
                    <button
                      type='button'
                      className='block max-w-full truncate text-start font-medium hover:underline'
                      title='Ubah FAQ'
                      onClick={() => setForm({ faq })}
                    >
                      {faq.question}
                    </button>
                    <p className='truncate text-xs text-muted-foreground'>{faq.answer}</p>
                  </TableCell>
                  <TableCell className='hidden sm:table-cell'>
                    <Badge variant='outline' className='font-normal'>{faq.category_label}</Badge>
                  </TableCell>
                  <TableCell className='hidden md:table-cell'>
                    <div className='flex flex-wrap gap-1'>
                      <Badge variant='secondary' className='font-normal'>FAQ</Badge>
                      {faq.show_on_home && <Badge variant='secondary' className='font-normal'>Beranda</Badge>}
                      {faq.show_on_product && <Badge variant='secondary' className='font-normal'>Produk</Badge>}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Switch
                      checked={faq.is_active}
                      disabled={busyId === faq.id}
                      aria-label={`${faq.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${faq.question}`}
                      onCheckedChange={() => {
                        setBusyId(faq.id)
                        router.patch(`${URL}/${faq.id}/aktif`, {}, {
                          preserveScroll: true,
                          preserveState: true,
                          onFinish: () => setBusyId(null),
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
                        disabled={busyId === faq.id}
                        aria-label={`Naikkan urutan ${faq.question}`}
                        title='Naikkan'
                        onClick={() => move(faq, 'up')}
                      >
                        <ArrowUp />
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8'
                        disabled={busyId === faq.id}
                        aria-label={`Turunkan urutan ${faq.question}`}
                        title='Turunkan'
                        onClick={() => move(faq, 'down')}
                      >
                        <ArrowDown />
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8'
                        aria-label={`Ubah FAQ ${faq.question}`}
                        title='Ubah'
                        onClick={() => setForm({ faq })}
                      >
                        <Pencil />
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                        aria-label={`Hapus FAQ ${faq.question}`}
                        title='Hapus'
                        onClick={() => setDeleting(faq)}
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
            {faqs.total > 0 ? `Menampilkan ${faqs.from}-${faqs.to} dari ${faqs.total} FAQ` : 'Tidak ada FAQ'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!faqs.prev_page_url} onClick={() => faqs.prev_page_url && router.get(faqs.prev_page_url, {}, { preserveScroll: true })}>
              Sebelumnya
            </Button>
            <Button variant='outline' size='sm' disabled={!faqs.next_page_url} onClick={() => faqs.next_page_url && router.get(faqs.next_page_url, {}, { preserveScroll: true })}>
              Berikutnya
            </Button>
          </div>
        </div>
      </Main>

      <FaqFormDialog
        open={form !== null}
        onOpenChange={(open) => !open && setForm(null)}
        categories={categories}
        faq={form?.faq}
        defaultCategory={filters.category !== ALL ? filters.category : undefined}
      />

      <BulkActionsBar count={selected.length} entityName='FAQ' onClear={() => setSelected([])}>
        {canActivate && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type='button' variant='outline' size='sm' className='h-8 gap-1.5' disabled={bulkBusy} onClick={() => bulkActive(true)} aria-label='Aktifkan FAQ terpilih'>
                <Power className='size-4' /> Aktifkan
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>Aktifkan FAQ terpilih</p></TooltipContent>
          </Tooltip>
        )}
        {canDeactivate && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button type='button' variant='outline' size='sm' className='h-8 gap-1.5' disabled={bulkBusy} onClick={() => bulkActive(false)} aria-label='Nonaktifkan FAQ terpilih'>
                <PowerOff className='size-4' /> Nonaktifkan
              </Button>
            </TooltipTrigger>
            <TooltipContent><p>Nonaktifkan FAQ terpilih</p></TooltipContent>
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
              aria-label='Hapus FAQ terpilih'
            >
              <Trash2 />
            </Button>
          </TooltipTrigger>
          <TooltipContent><p>Hapus FAQ terpilih</p></TooltipContent>
        </Tooltip>
      </BulkActionsBar>

      <ConfirmDialog
        open={confirmBulkDelete}
        onOpenChange={(open) => !open && setConfirmBulkDelete(false)}
        title={`Hapus ${selected.length} FAQ?`}
        desc='FAQ terpilih dihapus permanen dan hilang dari toko. Kalau hanya ingin menyembunyikan, gunakan Nonaktifkan.'
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={bulkDelete}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus FAQ?'
        desc={`"${deleting?.question ?? ''}" dihapus permanen dan hilang dari toko. Kalau hanya ingin menyembunyikan, gunakan Nonaktifkan.`}
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
