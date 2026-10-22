import { useEffect, useRef, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Eye, ImageIcon, Loader2, MessageCircle, Search, Trash2, TriangleAlert } from 'lucide-react'
import { formatNumber } from '@/lib/format'
import { renderReply } from '../../../js/chat-format.js'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { BulkActionsBar } from '@/components/bulk-actions-bar'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { formatDateTime } from '@/features/orders/types'

type Paginated<T> = { data: T[]; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }

type Row = {
  id: number
  user: { id: number; name: string; email: string } | null
  anonymized: boolean
  ip: string | null
  preview: string
  user_messages: number
  fallback_count: number
  wa_count: number
  image_count: number
  created_at: string
  last_message_at: string | null
}

type Message = {
  id: number
  role: 'user' | 'assistant'
  content: string | null
  has_image: boolean
  image_url: string | null
  status: string | null
  error: string | null
  model: string | null
  prompt_tokens: number | null
  completion_tokens: number | null
  ms: number | null
  show_wa: boolean
  created_at: string
}

type Detail = { id: number; user: { name: string; email: string } | null; anonymized: boolean; ip: string | null; created_at: string; messages: Message[] }

type Props = {
  conversations: Paginated<Row>
  filters: { q: string; tab: string; who: string }
  counts: Record<'all' | 'fallback' | 'wa' | 'image', number>
  stats: { conversations_today: number; questions_today: number; fallback_today: number; tokens_today: number }
}

const URL = '/admin/ai/percakapan'

export default function Conversations({ conversations, filters, counts, stats }: Props) {
  const [q, setQ] = useState(filters.q)
  const [detailId, setDetailId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState<Row | null>(null)
  const [selected, setSelected] = useState<number[]>([])
  const [confirmBulk, setConfirmBulk] = useState(false)

  const visit = (patch: Partial<Props['filters']>) => {
    const next = { ...filters, q, ...patch }
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v && v !== 'all'))
    router.get(URL, params, { preserveState: true, replace: true, onSuccess: () => setSelected([]) })
  }

  const firstRun = useRef(true)
  useEffect(() => {
    if (firstRun.current) {
      firstRun.current = false
      return
    }
    const timer = setTimeout(() => visit({ q }), 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q])

  const pageIds = conversations.data.map((c) => c.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.includes(id))

  const tabs = [
    { value: 'all', label: 'Semua', count: counts.all },
    { value: 'fallback', label: 'Jawaban cadangan', count: counts.fallback },
    { value: 'wa', label: 'Diarahkan ke WhatsApp', count: counts.wa },
    { value: 'image', label: 'Ada gambar', count: counts.image },
  ]

  return (
    <>
      <Head title='Percakapan AI' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Percakapan</h1>
          <p className='text-sm text-muted-foreground'>
            Log chat Tanya AI di toko. Pakai tab Jawaban cadangan dan Diarahkan ke WhatsApp untuk menemukan pertanyaan yang belum bisa dijawab AI, lalu tambahkan jawabannya di menu Pengetahuan.
          </p>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
          <StatCard label='Percakapan hari ini' value={formatNumber(stats.conversations_today)} />
          <StatCard label='Pertanyaan hari ini' value={formatNumber(stats.questions_today)} />
          <StatCard label='Jawaban cadangan hari ini' value={formatNumber(stats.fallback_today)} warn={stats.fallback_today > 0} />
          <StatCard label='Token hari ini' value={formatNumber(stats.tokens_today)} />
        </div>

        <div className='mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between'>
          <div className='flex flex-col gap-2 sm:flex-row'>
            <div className='relative w-full sm:w-72'>
              <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
              <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder='Cari isi chat, nama, email, atau IP' aria-label='Cari percakapan' className='ps-9' />
            </div>
            <Select value={filters.who} onValueChange={(v) => visit({ who: v })}>
              <SelectTrigger className='w-full sm:w-44' aria-label='Filter penanya'><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value='all'>Semua penanya</SelectItem>
                <SelectItem value='user'>User login</SelectItem>
                <SelectItem value='guest'>Tamu</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Tabs value={filters.tab} onValueChange={(v) => visit({ tab: v })} className='shrink-0'>
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
                    checked={allSelected}
                    onCheckedChange={(v) => setSelected(v === true ? pageIds : [])}
                    aria-label='Pilih semua percakapan di halaman ini'
                    disabled={pageIds.length === 0}
                  />
                </TableHead>
                <TableHead>Penanya</TableHead>
                <TableHead>Pertanyaan pertama</TableHead>
                <TableHead>Pertanyaan</TableHead>
                <TableHead>Penanda</TableHead>
                <TableHead className='hidden md:table-cell'>Terakhir</TableHead>
                <TableHead className='w-24'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {conversations.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={7} className='h-32 text-center text-muted-foreground'>
                    {filters.q || filters.tab !== 'all' || filters.who !== 'all' ? 'Tidak ada percakapan yang cocok.' : 'Belum ada percakapan.'}
                  </TableCell>
                </TableRow>
              )}
              {conversations.data.map((c) => (
                <TableRow key={c.id} data-state={selected.includes(c.id) ? 'selected' : undefined}>
                  <TableCell>
                    <Checkbox
                      checked={selected.includes(c.id)}
                      onCheckedChange={(v) => setSelected((prev) => (v === true ? [...prev, c.id] : prev.filter((x) => x !== c.id)))}
                      aria-label={`Pilih percakapan ${c.id}`}
                    />
                  </TableCell>
                  <TableCell><Asker row={c} /></TableCell>
                  <TableCell className='max-w-80'>
                    <button type='button' className='block max-w-full truncate text-start hover:underline' title='Lihat percakapan' onClick={() => setDetailId(c.id)}>
                      {c.preview}
                    </button>
                  </TableCell>
                  <TableCell className='tabular-nums'>{c.user_messages}</TableCell>
                  <TableCell>
                    <div className='flex flex-wrap gap-1'>
                      {c.fallback_count > 0 && <Badge variant='secondary' className='bg-amber-500/15 text-amber-700 dark:text-amber-400'>Cadangan {c.fallback_count}</Badge>}
                      {c.wa_count > 0 && <Badge variant='secondary' className='bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'>WhatsApp {c.wa_count}</Badge>}
                      {c.image_count > 0 && <Badge variant='secondary'>Gambar {c.image_count}</Badge>}
                      {!c.fallback_count && !c.wa_count && !c.image_count && <span className='text-muted-foreground'>-</span>}
                    </div>
                  </TableCell>
                  <TableCell className='hidden whitespace-nowrap text-sm md:table-cell'>{c.last_message_at ? formatDateTime(c.last_message_at) : '-'}</TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button type='button' variant='outline' size='icon' className='size-8' aria-label={`Lihat percakapan ${c.id}`} title='Lihat' onClick={() => setDetailId(c.id)}>
                        <Eye />
                      </Button>
                      <Button type='button' variant='outline' size='icon' className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive' aria-label={`Hapus percakapan ${c.id}`} title='Hapus' onClick={() => setDeleting(c)}>
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
            {conversations.total > 0 ? `Menampilkan ${conversations.from}-${conversations.to} dari ${conversations.total} percakapan` : 'Tidak ada percakapan'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' disabled={!conversations.prev_page_url} onClick={() => conversations.prev_page_url && router.get(conversations.prev_page_url, {}, { preserveScroll: true })}>
              Sebelumnya
            </Button>
            <Button variant='outline' size='sm' disabled={!conversations.next_page_url} onClick={() => conversations.next_page_url && router.get(conversations.next_page_url, {}, { preserveScroll: true })}>
              Berikutnya
            </Button>
          </div>
        </div>
      </Main>

      <DetailDialog id={detailId} onClose={() => setDetailId(null)} onDelete={(id) => { setDetailId(null); setDeleting(conversations.data.find((c) => c.id === id) ?? null) }} />

      <BulkActionsBar count={selected.length} entityName='percakapan' onClear={() => setSelected([])}>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button type='button' variant='outline' size='icon' className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive' onClick={() => setConfirmBulk(true)} aria-label='Hapus percakapan terpilih'>
              <Trash2 />
            </Button>
          </TooltipTrigger>
          <TooltipContent><p>Hapus percakapan terpilih</p></TooltipContent>
        </Tooltip>
      </BulkActionsBar>

      <ConfirmDialog
        open={confirmBulk}
        onOpenChange={(open) => !open && setConfirmBulk(false)}
        title={`Hapus ${selected.length} percakapan?`}
        desc='Percakapan terpilih tidak tampil lagi di daftar ini. Batas harian pelanggan tetap dihitung.'
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => router.delete(`${URL}/massal`, { data: { ids: selected }, preserveScroll: true, onSuccess: () => setSelected([]), onFinish: () => setConfirmBulk(false) })}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus percakapan?'
        desc='Percakapan ini tidak tampil lagi di daftar. Batas harian pelanggan tetap dihitung.'
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => deleting && router.delete(`${URL}/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
      />
    </>
  )
}

function Asker({ row }: { row: Pick<Row, 'user' | 'anonymized' | 'ip'> }) {
  if (row.user) {
    return (
      <>
        <p className='max-w-44 truncate font-medium'>{row.user.name}</p>
        <p className='max-w-44 truncate text-xs text-muted-foreground'>{row.user.email}</p>
      </>
    )
  }
  return (
    <>
      <p className='font-medium'>{row.anonymized ? 'Akun terhapus' : 'Tamu'}</p>
      <p className='text-xs text-muted-foreground'>{row.anonymized ? 'Dianonimkan' : `IP ${row.ip ?? '-'}`}</p>
    </>
  )
}

/** Isi percakapan seperti tampilan widget, dengan info teknis per jawaban (model, token, waktu). */
function DetailDialog({ id, onClose, onDelete }: { id: number | null; onClose: () => void; onDelete: (id: number) => void }) {
  const [detail, setDetail] = useState<Detail | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    setDetail(null)
    setFailed(false)
    if (id === null) return
    fetch(`${URL}/${id}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then(setDetail)
      .catch(() => setFailed(true))
  }, [id])

  return (
    <Dialog open={id !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-2xl'>
        <DialogHeader>
          <DialogTitle>Percakapan #{id}</DialogTitle>
          <DialogDescription>
            {detail ? (
              <>
                {detail.user ? `${detail.user.name} (${detail.user.email})` : detail.anonymized ? 'Akun terhapus (dianonimkan)' : `Tamu, IP ${detail.ip ?? '-'}`} | mulai {formatDateTime(detail.created_at)}
              </>
            ) : (
              'Memuat...'
            )}
          </DialogDescription>
        </DialogHeader>

        {failed && <p className='text-sm text-destructive'>Gagal memuat percakapan.</p>}
        {!detail && !failed && <Loader2 className='mx-auto size-5 animate-spin text-muted-foreground' />}

        {detail && (
          <ul className='space-y-3'>
            {detail.messages.map((m) => (
              <li key={m.id} className={m.role === 'user' ? 'flex flex-col items-end' : 'flex flex-col items-start'}>
                <div className={`max-w-[85%] rounded-xl px-3 py-2 text-sm ${m.role === 'user' ? 'bg-amber-500 text-[#0A0F1F]' : m.status === 'fallback' ? 'border border-amber-500/40 bg-amber-500/10' : 'bg-muted'}`}>
                  {m.has_image && (m.image_url ? (
                    <a href={m.image_url} target='_blank' rel='noreferrer' title='Buka gambar'>
                      <img src={m.image_url} alt='Gambar dari pelanggan' className='mb-1.5 max-h-48 rounded-md' />
                    </a>
                  ) : (
                    <p className='mb-1 flex items-center gap-1 text-xs opacity-75'><ImageIcon className='size-3.5' /> Gambar sudah dihapus otomatis</p>
                  ))}
                  {m.role === 'user' ? (
                    <p className='whitespace-pre-wrap break-words'>{m.content}</p>
                  ) : m.status === 'fallback' ? (
                    <p className='flex gap-1.5 text-amber-700 dark:text-amber-400'>
                      <TriangleAlert className='mt-0.5 size-4 shrink-0' />
                      <span>AI gagal menjawab, widget memakai jawaban kata kunci.{m.error ? ` ${m.error}` : ''}</span>
                    </p>
                  ) : (
                    <div className='chat-reply space-y-1 break-words [&_a]:underline [&_ol]:list-decimal [&_ol]:ps-5 [&_ul]:list-disc [&_ul]:ps-5' dangerouslySetInnerHTML={{ __html: renderReply(m.content ?? '') }} />
                  )}
                </div>
                <p className='mt-1 flex flex-wrap items-center gap-x-2 text-[11px] text-muted-foreground'>
                  {formatDateTime(m.created_at)}
                  {m.role === 'assistant' && m.model && <span>{m.model}</span>}
                  {m.ms != null && <span>{(m.ms / 1000).toFixed(1)} detik</span>}
                  {m.prompt_tokens != null && <span>token {m.prompt_tokens}/{m.completion_tokens ?? '?'}</span>}
                  {m.show_wa && <span className='inline-flex items-center gap-0.5 text-emerald-600'><MessageCircle className='size-3' /> tombol WhatsApp</span>}
                </p>
              </li>
            ))}
          </ul>
        )}

        <DialogFooter>
          <Button type='button' variant='outline' onClick={onClose}>Tutup</Button>
          {id !== null && (
            <Button type='button' variant='destructive' onClick={() => onDelete(id)}>
              <Trash2 className='size-4' /> Hapus
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function StatCard({ label, value, warn }: { label: string; value: string; warn?: boolean }) {
  return (
    <div className='rounded-lg border bg-card px-4 py-3'>
      <p className='text-xs text-muted-foreground'>{label}</p>
      <p className={`mt-1 text-xl font-semibold tabular-nums ${warn ? 'text-amber-600' : ''}`}>{value}</p>
    </div>
  )
}
