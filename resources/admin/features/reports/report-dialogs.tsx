import { type FormEvent, useState } from 'react'
import { Link, router, useForm } from '@inertiajs/react'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import { Check, ExternalLink, Flag, ShieldCheck, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogOverlay,
  DialogPortal,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { OrderOverview, OrderPayment, Panel, hairline, labelCls, muted, navy } from '@/features/orders/order-summary'
import { FieldError } from '@/features/products/field-error'
import { formatDateTime, type Option, type ReportRow, type ReportStatus } from './types'

export function StatusBadge({ status }: { status: ReportStatus }) {
  return status === 'open' ? (
    <Badge variant='secondary' className='bg-amber-500/15 text-amber-700 dark:text-amber-400'>Menunggu</Badge>
  ) : (
    <Badge variant='secondary' className='bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'>Selesai</Badge>
  )
}

type DetailProps = {
  report: ReportRow | null
  onClose: () => void
  onResolve: (report: ReportRow) => void
  onReopen: (report: ReportRow) => void
}

/** Status laporan dengan gaya pil yang sama seperti status di modal pesanan. */
function ReportStatusPill({ status }: { status: ReportStatus }) {
  const cls =
    status === 'open'
      ? 'border-[#F59E0B]/30 bg-[#F59E0B]/15 text-[#B45309] dark:text-[#FBBF24]'
      : 'border-[#10B981]/30 bg-[#10B981]/15 text-[#059669] dark:text-[#34D399]'

  return (
    <span className={`inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-[12.5px] font-semibold ${cls}`}>
      {status === 'resolved' && <Check className='size-3.5' strokeWidth={3} />}
      {status === 'open' ? 'Menunggu' : 'Selesai'}
    </span>
  )
}

/**
 * Detail laporan dengan tampilan yang sama seperti modal Detail Pesanan: isi laporan di atas, lalu data pesanan terkait
 * (pelanggan, produk, varian, Garansi Customer, Profil Order, Akun Premium, Pembayaran) memakai komponen yang sama.
 */
export function ReportDetailDialog({ report, onClose, onResolve, onReopen }: DetailProps) {
  return (
    <Dialog open={report !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogPortal>
        <DialogOverlay className='bg-[#0A0F1F]/60 backdrop-blur-[4px]' />
        <DialogPrimitive.Content
          aria-modal='true'
          className='fixed top-1/2 left-1/2 z-50 flex max-h-[90vh] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-[18px] border-0 bg-white shadow-[0_20px_60px_rgba(0,0,0,0.15)] duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 sm:max-w-[760px] dark:bg-[#0F1629] dark:ring-1 dark:ring-white/10'
        >
          {report && (
            <>
              <div className={`flex items-center gap-3 border-b px-6 py-4 sm:px-7 ${hairline}`}>
                <span className='flex size-9 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B]'>
                  <Flag className='size-[18px]' />
                </span>
                <DialogPrimitive.Title className={`flex-1 text-lg font-bold sm:text-xl ${navy}`}>Detail Laporan</DialogPrimitive.Title>
                <DialogPrimitive.Description className='sr-only'>Laporan {report.subject} dari {report.user.name}</DialogPrimitive.Description>
                <DialogPrimitive.Close
                  aria-label='Tutup modal'
                  className='flex size-9 shrink-0 items-center justify-center rounded-full bg-[#F3F4F6] text-[#4B5563] transition duration-200 hover:rotate-90 hover:bg-[#E5E7EB] focus-visible:ring-2 focus-visible:ring-[#F59E0B] focus-visible:outline-none dark:bg-white/10 dark:text-[#D1D5DB] dark:hover:bg-white/20'
                >
                  <X className='size-4' />
                </DialogPrimitive.Close>
              </div>

              <div className='space-y-5 overflow-y-auto px-6 py-5 sm:px-7 sm:py-6'>
                <Panel index={0} className='bg-white shadow-[0_2px_8px_rgba(0,0,0,0.04)] dark:bg-white/[0.03]'>
                  <div className='flex items-start justify-between gap-3'>
                    <div className='min-w-0'>
                      <h3 className={`text-[15px] font-bold break-words ${navy}`}>{report.subject}</h3>
                      <p className={`mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] ${muted}`}>
                        <Badge variant='outline' className='font-normal'>{report.category_label}</Badge>
                        {formatDateTime(report.created_at)}
                      </p>
                    </div>
                    <ReportStatusPill status={report.status} />
                  </div>
                  <p className={`mt-3 whitespace-pre-wrap break-words text-[13.5px] ${navy}`}>{report.body}</p>

                  {report.attachments.length > 0 && (
                    <div className='mt-4'>
                      <p className={labelCls}>Bukti ({report.attachments.length})</p>
                      <div className='mt-2 flex flex-wrap gap-2'>
                        {report.attachments.map((a) => (
                          <a key={a.id} href={a.url} target='_blank' rel='noreferrer' title={a.name}>
                            <img src={a.url} alt={a.name} className={`size-20 rounded-lg border object-cover transition hover:border-[#F59E0B] ${hairline}`} />
                          </a>
                        ))}
                      </div>
                    </div>
                  )}

                  {report.status === 'resolved' && (
                    <div className='mt-4 rounded-xl border border-[#10B981]/30 bg-[#10B981]/5 px-3.5 py-3 text-[13.5px]'>
                      <p className={labelCls}>Diselesaikan</p>
                      <p className={`mt-1 font-semibold ${navy}`}>
                        {report.resolution_label}
                        {report.duplicate_of_id ? ` (laporan #${report.duplicate_of_id})` : ''}
                      </p>
                      {report.resolution_note && <p className={`mt-1 whitespace-pre-wrap break-words ${navy}`}>{report.resolution_note}</p>}
                      <p className={`mt-1 text-xs ${muted}`}>
                        {report.resolved_by ? `${report.resolved_by} | ` : ''}
                        {report.resolved_at ? formatDateTime(report.resolved_at) : ''}
                      </p>
                    </div>
                  )}
                </Panel>

                {report.order ? (
                  <>
                    <OrderOverview order={report.order} />
                    <OrderPayment order={report.order} index={4} />
                  </>
                ) : (
                  <>
                    <div className='min-w-0'>
                      <p className={labelCls}>Pelanggan</p>
                      <p className={`mt-1.5 text-[15px] font-semibold ${navy}`}>{report.user.name}</p>
                      <p className={`mt-0.5 break-all text-[13.5px] ${muted}`}>{report.user.email}</p>
                      {report.user.phone && <p className={`text-[13.5px] ${muted}`}>{report.user.phone}</p>}
                    </div>
                    <p className={`rounded-xl border border-dashed px-3.5 py-3 text-[13.5px] ${hairline} ${muted}`}>
                      Laporan ini tidak terkait pesanan, jadi data produk, garansi, akun, dan pembayaran tidak ada.
                    </p>
                  </>
                )}

                <Panel index={5} className='bg-[#F9FAFB] text-sm dark:bg-white/[0.03]'>
                  <ClaimSection report={report} />
                </Panel>

                <Panel index={6} className='bg-[#F9FAFB] text-sm dark:bg-white/[0.03]'>
                  <h3 className={`text-[15px] font-bold ${navy}`}>LAPORAN LAIN DARI PELANGGAN INI ({report.others.length})</h3>
                  {report.others.length === 0 ? (
                    <p className={`mt-2 ${muted}`}>Tidak ada.</p>
                  ) : (
                    <ul className='mt-3 space-y-2.5'>
                      {report.others.map((o) => (
                        <li key={o.id} className='flex items-center justify-between gap-3 rounded-xl border border-[#F3F4F6] bg-white p-3.5 dark:border-white/10 dark:bg-white/5'>
                          <div className='min-w-0'>
                            <p className={`truncate text-[13.5px] font-semibold ${navy}`}>
                              <span className={`font-normal ${muted}`}>#{o.id}</span> {o.subject}
                            </p>
                            <p className={`mt-0.5 text-xs ${muted}`}>{o.category_label} | {formatDateTime(o.created_at)}</p>
                          </div>
                          <StatusBadge status={o.status} />
                        </li>
                      ))}
                    </ul>
                  )}
                </Panel>
              </div>

              <div className='flex flex-col-reverse gap-3 border-t border-[#F3F4F6] px-6 py-4 sm:flex-row sm:justify-end sm:px-7 dark:border-white/10'>
                <DialogPrimitive.Close className='w-full rounded-xl bg-[#F3F4F6] px-5 py-2.5 text-sm font-semibold text-[#374151] transition hover:bg-[#E5E7EB] focus-visible:ring-2 focus-visible:ring-[#F59E0B] focus-visible:outline-none sm:w-auto dark:bg-white/10 dark:text-[#E5E7EB] dark:hover:bg-white/20'>
                  Tutup
                </DialogPrimitive.Close>
                {report.order && (
                  <a
                    href={`/admin/pesanan?q=${encodeURIComponent(report.order.code)}`}
                    className='inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] px-5 py-2.5 text-sm font-semibold text-[#374151] transition hover:border-[#F59E0B] sm:w-auto dark:border-white/15 dark:text-[#E5E7EB]'
                  >
                    <ExternalLink className='size-4' /> Buka di Pesanan
                  </a>
                )}
                {report.status === 'open' ? (
                  <button
                    type='button'
                    onClick={() => onResolve(report)}
                    className='inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-5 py-2.5 text-sm font-semibold text-[#0A0F1F] shadow-[0_4px_12px_rgba(245,158,11,0.3)] transition hover:shadow-[0_6px_16px_rgba(245,158,11,0.4)] hover:brightness-105 active:scale-[0.99] sm:w-auto'
                  >
                    <Check className='size-4' /> Selesaikan
                  </button>
                ) : (
                  <button
                    type='button'
                    onClick={() => onReopen(report)}
                    className='inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] px-5 py-2.5 text-sm font-semibold text-[#374151] transition hover:border-[#F59E0B] sm:w-auto dark:border-white/15 dark:text-[#E5E7EB]'
                  >
                    Buka kembali
                  </button>
                )}
              </div>
            </>
          )}
        </DialogPrimitive.Content>
      </DialogPortal>
    </Dialog>
  )
}

/** Pesanan aktif pelapor dan tombol memindahkan laporan "Akun bermasalah" ke Klaim Garansi. */
function ClaimSection({ report }: { report: ReportRow }) {
  const eligible = report.orders.filter((o) => o.warranty_open)
  const [orderId, setOrderId] = useState<string>('')
  const [busy, setBusy] = useState(false)
  const chosen = orderId || String(eligible[0]?.id ?? '')
  const canMove = report.status === 'open' && report.category === 'account'

  const move = () => {
    setBusy(true)
    router.post(`/admin/laporan/${report.id}/klaim`, { order_id: Number(chosen) }, {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setBusy(false),
    })
  }

  return (
    <section>
      <h3 className={`mb-3 text-[15px] font-bold ${navy}`}>PESANAN LUNAS PELANGGAN ({report.orders.length})</h3>
      {report.orders.length === 0 ? (
        <p className='text-muted-foreground'>Belum ada pesanan lunas.</p>
      ) : (
        <ul className='divide-y rounded-xl border bg-white dark:bg-white/5'>
          {report.orders.map((o) => (
            <li key={o.id} className='flex items-center justify-between gap-3 px-3 py-2'>
              <div className='min-w-0'>
                <p className='truncate font-medium'>{o.code}</p>
                <p className='truncate text-xs text-muted-foreground'>
                  {o.product_name}{o.variant_title ? ` | ${o.variant_title}` : ''} | {o.warranty_name ?? 'Tanpa garansi'}
                </p>
              </div>
              <Badge variant='secondary' className={o.warranty_open ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'}>
                {o.warranty_open ? 'Garansi aktif' : o.warranty_name ? 'Garansi habis' : 'Tanpa garansi'}
              </Badge>
            </li>
          ))}
        </ul>
      )}

      {canMove && report.claim?.status === 'open' && (
        <p className='mt-2 flex items-center gap-1.5 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-700 dark:text-amber-400'>
          <ShieldCheck className='size-4 shrink-0' />
          Sudah dipindahkan ke Klaim Garansi, menunggu penggantian akun.
          <Link href='/admin/klaim-garansi?status=open' className='ms-auto shrink-0 font-semibold underline'>Buka</Link>
        </p>
      )}
      {canMove && report.claim?.status !== 'open' && eligible.length > 0 && (
        <div className='mt-2 flex flex-wrap items-center gap-2'>
          {eligible.length > 1 && (
            <Select value={chosen} onValueChange={setOrderId}>
              <SelectTrigger className='w-56' aria-label='Pesanan yang diklaim'><SelectValue /></SelectTrigger>
              <SelectContent>
                {eligible.map((o) => (<SelectItem key={o.id} value={String(o.id)}>{o.code} | {o.product_name}</SelectItem>))}
              </SelectContent>
            </Select>
          )}
          <Button type='button' variant='outline' size='sm' className='gap-1.5' disabled={busy} onClick={move}>
            <ShieldCheck className='size-4' /> Pindahkan ke Klaim Garansi
          </Button>
        </div>
      )}
      {canMove && report.claim?.status !== 'open' && eligible.length === 0 && (
        <p className='mt-2 text-xs text-muted-foreground'>Tidak ada pesanan dengan garansi aktif, jadi tidak bisa dipindahkan ke Klaim Garansi. Penggantian tetap bisa dilakukan manual dari halaman Pesanan.</p>
      )}
    </section>
  )
}

type ResolveTarget = { report: ReportRow } | { ids: number[] }

type ResolveProps = {
  target: ResolveTarget | null
  resolutions: Option[]
  onClose: () => void
  onDone: () => void
}

export function ResolveDialog({ target, resolutions, onClose, onDone }: ResolveProps) {
  return (
    <Dialog open={target !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='sm:max-w-lg'>
        {/* Dipasang ulang tiap dibuka agar isiannya selalu segar. */}
        {target && <ResolveBody target={target} resolutions={resolutions} onClose={onClose} onDone={onDone} />}
      </DialogContent>
    </Dialog>
  )
}

function ResolveBody({ target, resolutions, onClose, onDone }: Omit<ResolveProps, 'target'> & { target: ResolveTarget }) {
  const single = 'report' in target ? target.report : null
  const options = single ? resolutions : resolutions.filter((r) => r.value !== 'duplicate')
  const form = useForm({
    resolution: '',
    duplicate_of_id: '',
    resolution_note: '',
  })
  const errors = form.errors as Record<string, string | undefined>

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const opts = { preserveScroll: true, onSuccess: () => { onDone(); onClose() } }
    if (single) {
      form.patch(`/admin/laporan/${single.id}/selesai`, opts)
    } else {
      form.transform((d) => ({ ...d, ids: (target as { ids: number[] }).ids }))
      form.patch('/admin/laporan/massal/selesai', opts)
    }
  }

  return (
    <form onSubmit={submit} noValidate className='space-y-4'>
      <DialogHeader>
        <DialogTitle>{single ? 'Selesaikan laporan' : `Selesaikan ${(target as { ids: number[] }).ids.length} laporan`}</DialogTitle>
        <DialogDescription>
          {single ? `"${single.subject}" dari ${single.user.name}.` : 'Semua laporan terpilih yang masih menunggu akan ditandai selesai dengan cara yang sama.'}
        </DialogDescription>
      </DialogHeader>

      <div className='space-y-2'>
        <Label htmlFor='resolution'>Cara penyelesaian</Label>
        <Select
          value={form.data.resolution}
          onValueChange={(v) => {
            form.clearErrors('resolution')
            form.setData('resolution', v)
          }}
        >
          <SelectTrigger id='resolution' className='w-full'>
            <SelectValue placeholder='Pilih cara penyelesaian' />
          </SelectTrigger>
          <SelectContent>
            {options.map((r) => (
              <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
        <FieldError message={errors.resolution} />
      </div>

      {single && form.data.resolution === 'duplicate' && (
        <div className='space-y-2'>
          <Label htmlFor='duplicate_of_id'>Laporan asli (opsional)</Label>
          <Select value={form.data.duplicate_of_id} onValueChange={(v) => form.setData('duplicate_of_id', v)}>
            <SelectTrigger id='duplicate_of_id' className='w-full'>
              <SelectValue placeholder={single.others.length ? 'Pilih laporan asli' : 'Tidak ada laporan lain'} />
            </SelectTrigger>
            <SelectContent>
              {single.others.map((o) => (
                <SelectItem key={o.id} value={String(o.id)}>#{o.id} {o.subject}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FieldError message={errors.duplicate_of_id} />
        </div>
      )}

      <div className='space-y-2'>
        <Label htmlFor='resolution_note'>Catatan internal (opsional)</Label>
        <Textarea
          id='resolution_note'
          rows={3}
          maxLength={500}
          value={form.data.resolution_note}
          onChange={(e) => form.setData('resolution_note', e.target.value)}
          placeholder='Tidak terlihat oleh pelanggan.'
        />
        <FieldError message={errors.resolution_note} />
      </div>

      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
        <Button type='submit' disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Tandai selesai'}</Button>
      </DialogFooter>
    </form>
  )
}
