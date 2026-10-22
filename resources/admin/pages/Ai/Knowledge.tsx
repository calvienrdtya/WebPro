import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Eye, FileText, Image as ImageIcon, Loader2, Pencil, Plus, Power, PowerOff, RotateCcw, Search, Trash2 } from 'lucide-react'
import { formatNumber } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { BulkActionsBar } from '@/components/bulk-actions-bar'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { cn } from '@/lib/utils'
import { KnowledgeDialog } from '@/features/ai/knowledge-dialog'
import { type KnowledgeEntry, type KnowledgeLimits, type KnowledgeStatus } from '@/features/ai/knowledge-types'
import { MARKDOWN_CLASS, renderMarkdown } from '@/features/ai/markdown'

type Paginated<T> = { data: T[]; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }
type Tab = 'semua' | 'aktif' | 'review' | 'processing' | 'failed' | 'nonaktif'
type Section = { key: string; label: string; enabled: boolean; tokens: number }

type Props = {
  entries: Paginated<KnowledgeEntry>
  filters: { q: string; tab: Tab }
  counts: Record<Tab, number>
  stats: { active: number; review: number; processing: number; failed: number }
  sections: Section[]
  size: { knowledge: number; system: number; overhead: number; total: number; limit: number }
  queueStalled: boolean
  products: { id: number; name: string }[]
  limits: KnowledgeLimits
}

const URL = '/admin/ai/pengetahuan'

const TABS: { value: Tab; label: string }[] = [
  { value: 'semua', label: 'Semua' },
  { value: 'aktif', label: 'Aktif' },
  { value: 'review', label: 'Perlu ditinjau' },
  { value: 'processing', label: 'Diproses' },
  { value: 'failed', label: 'Gagal' },
  { value: 'nonaktif', label: 'Nonaktif' },
]

const STATUS_STYLE: Record<KnowledgeStatus, string> = {
  processing: 'border-sky-500/40 text-sky-600 dark:text-sky-400',
  review: 'border-amber-500/40 text-amber-600 dark:text-amber-400',
  ready: 'border-emerald-500/40 text-emerald-600 dark:text-emerald-400',
  failed: 'border-destructive/40 text-destructive',
}

export default function Knowledge({ entries, filters, counts, stats, sections, size, queueStalled, products, limits }: Props) {
  const [q, setQ] = useState(filters.q)
  const [dialogId, setDialogId] = useState<number | 'new' | null>(null)
  const [deleting, setDeleting] = useState<KnowledgeEntry | null>(null)
  const [selected, setSelected] = useState<number[]>([])
  const [confirmBulkDelete, setConfirmBulkDelete] = useState(false)
  const [sectionView, setSectionView] = useState<{ label: string; text: string } | null>(null)
  const [busySection, setBusySection] = useState<string | null>(null)

  // Dialog memakai data terbaru supaya hasil AI muncul sendiri setelah halaman diperbarui.
  const dialogTarget = dialogId === 'new' ? 'new' : (entries.data.find((e) => e.id === dialogId) ?? null)

  useEffect(() => {
    const visible = new Set(entries.data.map((e) => e.id))
    setSelected((prev) => {
      const kept = prev.filter((id) => visible.has(id))
      return kept.length === prev.length ? prev : kept
    })
  }, [entries.data])

  // Selama ada entri yang diproses, muat ulang data tiap 10 detik sampai hasil AI masuk.
  useEffect(() => {
    if (stats.processing === 0) return
    const timer = setInterval(() => router.reload({ only: ['entries', 'stats', 'counts', 'size', 'queueStalled'] }), 10000)
    return () => clearInterval(timer)
  }, [stats.processing])

  const firstRun = useRef(true)
  const visit = (patch: Partial<Props['filters']>) => {
    const next = { ...filters, q, ...patch }
    const params = Object.fromEntries(Object.entries(next).filter(([k, v]) => v && !(k === 'tab' && v === 'semua')))
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

  const pageIds = entries.data.map((e) => e.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id))
  const toggleAll = (checked: boolean) => setSelected(checked ? pageIds : [])
  const toggleOne = (id: number, checked: boolean) => setSelected((prev) => (checked ? [...prev, id] : prev.filter((x) => x !== id)))

  const bulkActive = (active: boolean) =>
    router.patch(`${URL}/massal/aktif`, { ids: selected, active }, { preserveScroll: true, onSuccess: () => setSelected([]) })

  const showSection = async (s: Section) => {
    const res = await fetch(`${URL}/bagian/${s.key}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    if (res.ok) setSectionView({ label: s.label, text: (await res.json()).text })
  }

  const toggleSection = (s: Section, enabled: boolean) => {
    setBusySection(s.key)
    router.patch(`${URL}/bagian/${s.key}`, { enabled }, { preserveScroll: true, onFinish: () => setBusySection(null) })
  }

  const percent = Math.min(100, Math.round((size.total / size.limit) * 100))
  const over = size.total > size.limit

  return (
    <>
      <Head title='Pengetahuan AI' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>Pengetahuan AI</h1>
            <p className='text-sm text-muted-foreground'>
              Semua yang boleh dijawab Tanya AI: data toko otomatis dan entri dari Anda. Entri yang dirapikan AI wajib ditinjau dulu sebelum dipakai.
            </p>
          </div>
          <Button onClick={() => setDialogId('new')} className='shrink-0'>
            <Plus className='size-4' /> Tambah pengetahuan
          </Button>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
          <StatCard label='Entri aktif' value={formatNumber(stats.active)} />
          <StatCard label='Perlu ditinjau' value={formatNumber(stats.review)} highlight={stats.review > 0} />
          <StatCard label='Diproses / gagal' value={`${formatNumber(stats.processing)} / ${formatNumber(stats.failed)}`} />
          <div className='rounded-lg border bg-card px-4 py-3'>
            <p className='text-xs text-muted-foreground'>Perkiraan token per pesan</p>
            <p className={cn('mt-1 text-xl font-semibold tabular-nums', over && 'text-destructive')}>
              {formatNumber(size.total)} <span className='text-sm font-normal text-muted-foreground'>/ {formatNumber(size.limit)}</span>
            </p>
            <div className='mt-2 h-1.5 overflow-hidden rounded-full bg-muted'>
              <div className={cn('h-full rounded-full', over ? 'bg-destructive' : percent > 75 ? 'bg-amber-500' : 'bg-emerald-500')} style={{ width: `${percent}%` }} />
            </div>
            <p className='mt-1 text-[11px] text-muted-foreground'>
              Pengetahuan {formatNumber(size.knowledge)}, instruksi {formatNumber(size.system - size.knowledge)}, beban penyedia {formatNumber(size.overhead)}
            </p>
          </div>
        </div>

        {over && (
          <p className='mb-4 rounded-lg border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive'>
            Total token melewati batas aman. Nonaktifkan entri atau bagian yang jarang dibutuhkan, atau naikkan batas di menu Koneksi &gt; Pengaturan lanjutan.
          </p>
        )}
        {queueStalled && (
          <p className='mb-4 rounded-lg border border-amber-500/40 bg-amber-500/5 px-3 py-2 text-sm text-amber-700 dark:text-amber-400'>
            Ada entri yang menunggu diproses lebih dari 2 menit. Pastikan cron <span>schedule:run</span> berjalan di server (di komputer lokal: <span>php artisan queue:work</span>).
          </p>
        )}

        <section className='mb-6 rounded-lg border p-4'>
          <h2 className='font-semibold'>Data toko otomatis</h2>
          <p className='mb-3 text-sm text-muted-foreground'>Diambil langsung dari database dan selalu terbaru. Data internal (HPP, akun, pelanggan) tidak pernah ikut.</p>
          <div className='grid gap-2 sm:grid-cols-2 lg:grid-cols-3'>
            {sections.map((s) => (
              <div key={s.key} className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
                <div className='min-w-0'>
                  <p className='truncate text-sm font-medium'>{s.label}</p>
                  <p className='text-xs text-muted-foreground'>sekitar {formatNumber(s.tokens)} token</p>
                </div>
                <div className='flex shrink-0 items-center gap-2'>
                  <Button type='button' variant='ghost' size='icon' className='size-8' title='Lihat isi' aria-label={`Lihat isi ${s.label}`} onClick={() => showSection(s)}>
                    <Eye />
                  </Button>
                  <Switch checked={s.enabled} disabled={busySection !== null} aria-label={`Pakai ${s.label}`} onCheckedChange={(v) => toggleSection(s, v)} />
                </div>
              </div>
            ))}
          </div>
        </section>

        <div className='mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between'>
          <div className='relative w-full lg:w-72'>
            <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder='Cari judul atau isi' aria-label='Cari pengetahuan' className='ps-9' />
          </div>
          <Tabs value={filters.tab} onValueChange={(v) => visit({ tab: v as Tab })} className='shrink-0'>
            <TabsList className='h-auto flex-wrap justify-start'>
              {TABS.map((t) => (
                <TabsTrigger key={t.value} value={t.value} className='gap-1.5'>
                  {t.label}
                  <span className='rounded-full bg-muted px-1.5 text-xs tabular-nums text-muted-foreground'>{counts[t.value]}</span>
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
                  <Checkbox checked={allSelected} onCheckedChange={(v) => toggleAll(v === true)} aria-label='Pilih semua entri di halaman ini' disabled={pageIds.length === 0} />
                </TableHead>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Judul</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className='hidden text-end md:table-cell'>Token</TableHead>
                <TableHead className='w-16'>Aktif</TableHead>
                <TableHead className='w-32'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {entries.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={7} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.tab !== 'semua' ? 'Tidak ada entri yang cocok.' : 'Belum ada entri pengetahuan.'}
                  </TableCell>
                </TableRow>
              )}
              {entries.data.map((entry, index) => (
                <TableRow key={entry.id} data-state={selected.includes(entry.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox checked={selected.includes(entry.id)} onCheckedChange={(v) => toggleOne(entry.id, v === true)} aria-label={`Pilih ${entry.title}`} />
                  </TableCell>
                  <TableCell className='text-muted-foreground'>{(entries.from ?? 1) + index}</TableCell>
                  <TableCell className='max-w-96'>
                    <button type='button' className='block max-w-full truncate text-start font-medium hover:underline' onClick={() => setDialogId(entry.id)}>
                      {entry.title}
                    </button>
                    <div className='mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground'>
                      {entry.is_builtin && <Badge variant='secondary' className='text-[10px] font-normal'>Bawaan</Badge>}
                      {entry.products.map((p) => (
                        <Badge key={p.id} variant='outline' className='text-[10px] font-normal'>{p.name}</Badge>
                      ))}
                      {entry.attachments.some((a) => a.kind === 'image') && (
                        <span className='inline-flex items-center gap-0.5'><ImageIcon className='size-3' /> {entry.attachments.filter((a) => a.kind === 'image').length}</span>
                      )}
                      {entry.attachments.some((a) => a.kind === 'pdf') && <span className='inline-flex items-center gap-0.5'><FileText className='size-3' /> PDF</span>}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant='outline' className={cn('gap-1', STATUS_STYLE[entry.status])}>
                      {entry.status === 'processing' && <Loader2 className='size-3 animate-spin' />}
                      {entry.status_label}
                    </Badge>
                    {entry.error && <p className='mt-0.5 max-w-60 truncate text-xs text-destructive' title={entry.error}>{entry.error}</p>}
                  </TableCell>
                  <TableCell className='hidden text-end tabular-nums md:table-cell'>{entry.body ? formatNumber(entry.tokens) : '-'}</TableCell>
                  <TableCell>
                    <Switch
                      checked={entry.status === 'ready' && entry.is_active}
                      disabled={entry.status !== 'ready'}
                      aria-label={`Aktifkan ${entry.title}`}
                      title={entry.status !== 'ready' ? 'Setujui dulu agar bisa diaktifkan' : undefined}
                      onCheckedChange={() => router.patch(`${URL}/${entry.id}/aktif`, {}, { preserveScroll: true })}
                    />
                  </TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button type='button' variant='outline' size='icon' className='size-8' title={entry.status === 'review' ? 'Tinjau' : 'Ubah'} aria-label={`Ubah ${entry.title}`} onClick={() => setDialogId(entry.id)}>
                        {entry.status === 'review' ? <Eye /> : <Pencil />}
                      </Button>
                      {entry.use_ai && !entry.is_builtin && entry.status !== 'processing' && (
                        <Button type='button' variant='outline' size='icon' className='size-8' title='Proses ulang dengan AI' aria-label={`Proses ulang ${entry.title}`} onClick={() => router.post(`${URL}/${entry.id}/proses`, {}, { preserveScroll: true })}>
                          <RotateCcw />
                        </Button>
                      )}
                      {!entry.is_builtin && (
                        <Button type='button' variant='outline' size='icon' className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive' title='Hapus' aria-label={`Hapus ${entry.title}`} onClick={() => setDeleting(entry)}>
                          <Trash2 />
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
          <p className='text-sm text-muted-foreground'>{entries.total > 0 ? `Menampilkan ${entries.from}-${entries.to} dari ${entries.total} entri` : 'Tidak ada entri'}</p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!entries.prev_page_url} onClick={() => entries.prev_page_url && router.get(entries.prev_page_url, {}, { preserveScroll: true })}>Sebelumnya</Button>
            <Button variant='outline' size='sm' disabled={!entries.next_page_url} onClick={() => entries.next_page_url && router.get(entries.next_page_url, {}, { preserveScroll: true })}>Berikutnya</Button>
          </div>
        </div>
      </Main>

      <KnowledgeDialog target={dialogTarget} onClose={() => setDialogId(null)} products={products} limits={limits} />

      <Dialog open={sectionView !== null} onOpenChange={(o) => !o && setSectionView(null)}>
        <DialogContent className='sm:max-w-3xl'>
          <DialogHeader>
            <DialogTitle>{sectionView?.label}</DialogTitle>
            <DialogDescription>Teks persis yang dibaca AI untuk bagian ini.</DialogDescription>
          </DialogHeader>
          <div className={cn('max-h-[65vh] overflow-y-auto rounded-md border p-3', MARKDOWN_CLASS)} dangerouslySetInnerHTML={{ __html: renderMarkdown(sectionView?.text ?? '') }} />
        </DialogContent>
      </Dialog>

      <BulkActionsBar count={selected.length} entityName='entri' onClear={() => setSelected([])}>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button type='button' variant='outline' size='icon' className='size-8' onClick={() => bulkActive(true)} aria-label='Aktifkan entri terpilih'><Power /></Button>
          </TooltipTrigger>
          <TooltipContent><p>Aktifkan (hanya yang sudah disetujui)</p></TooltipContent>
        </Tooltip>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button type='button' variant='outline' size='icon' className='size-8' onClick={() => bulkActive(false)} aria-label='Nonaktifkan entri terpilih'><PowerOff /></Button>
          </TooltipTrigger>
          <TooltipContent><p>Nonaktifkan</p></TooltipContent>
        </Tooltip>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button type='button' variant='outline' size='icon' className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive' onClick={() => setConfirmBulkDelete(true)} aria-label='Hapus entri terpilih'><Trash2 /></Button>
          </TooltipTrigger>
          <TooltipContent><p>Hapus (entri bawaan dilewati)</p></TooltipContent>
        </Tooltip>
      </BulkActionsBar>

      <ConfirmDialog
        open={confirmBulkDelete}
        onOpenChange={(o) => !o && setConfirmBulkDelete(false)}
        title={`Hapus ${selected.length} entri?`}
        desc='Entri terpilih tidak lagi dipakai AI dan hilang dari daftar. Entri bawaan dilewati.'
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => router.delete(`${URL}/massal`, { data: { ids: selected }, preserveScroll: true, onSuccess: () => setSelected([]), onFinish: () => setConfirmBulkDelete(false) })}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(o) => !o && setDeleting(null)}
        title='Hapus entri?'
        desc={`"${deleting?.title ?? ''}" tidak lagi dipakai AI dan hilang dari daftar.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => deleting && router.delete(`${URL}/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
      />
    </>
  )
}

function StatCard({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  return (
    <div className='rounded-lg border bg-card px-4 py-3'>
      <p className='text-xs text-muted-foreground'>{label}</p>
      <p className={cn('mt-1 text-xl font-semibold tabular-nums', highlight && 'text-amber-600 dark:text-amber-400')}>{value}</p>
    </div>
  )
}
