import { useEffect, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import { ArrowLeftRight, ArrowRight, CalendarDays, Check, Copy, History, Mail, Pencil, ReceiptText, Send, X } from 'lucide-react'
import { toast } from 'sonner'
import { copyText } from '@/lib/clipboard'
import { formatRupiah } from '@/lib/format'
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
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { diffDays, localToday } from '@/features/accounts/dates'
import { ReplaceAccountDialog } from './replace-account-dialog'
import { ghostAction, hairline, iconAction, muted, navy, OrderOverview, OrderPayment, Panel } from './order-summary'
import { formatDateTime, formatDay, statusBadge, type DeliveryRow, type OrderRow } from './types'

export function StatusBadge({ order }: { order: OrderRow }) {
  return (
    <Badge variant='secondary' className={statusBadge[order.status]}>
      {order.status_label}
    </Badge>
  )
}


type DetailProps = { order: OrderRow | null; onClose: () => void }

export function OrderDetailDialog({ order, onClose }: DetailProps) {
  const [busyId, setBusyId] = useState<number | null>(null)
  const [note, setNote] = useState('')
  const [noteBusy, setNoteBusy] = useState(false)
  const [copied, setCopied] = useState<string | null>(null)
  const [replacing, setReplacing] = useState<number | null>(null)
  const today = localToday()

  // Catatan mengikuti pesanan yang sedang dibuka.
  useEffect(() => {
    setNote(order?.admin_note ?? '')
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [order?.id])

  const copy = async (key: string, text: string, label: string) => {
    if (await copyText(text)) {
      setCopied(key)
      toast.success(`${label} disalin`)
      setTimeout(() => setCopied((c) => (c === key ? null : c)), 1500)
    }
  }

  const resend = (orderId: number, deliveryId: number) => {
    setBusyId(deliveryId)
    router.post(`/admin/pesanan/${orderId}/kiriman/${deliveryId}/kirim-ulang`, {}, {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setBusyId(null),
    })
  }

  const sendNow = (orderId: number, deliveryId: number) => {
    setBusyId(deliveryId)
    router.post(`/admin/pesanan/${orderId}/kiriman/${deliveryId}/kirim`, {}, {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setBusyId(null),
    })
  }

  const saveNote = () => {
    if (!order) return
    setNoteBusy(true)
    router.patch(`/admin/pesanan/${order.id}/catatan`, { note }, {
      preserveScroll: true,
      preserveState: true,
      onFinish: () => setNoteBusy(false),
    })
  }

  const accountText = (o: OrderRow, d: DeliveryRow) =>
    [
      `Akun ${o.product_name}${o.months > 1 ? ` (bulan ke-${d.sequence})` : ''}`,
      '',
      `Email : ${d.account?.email || '-'}`,
      `Password : ${d.account?.password || '-'}`,
      `Profil : ${d.account?.profile || '-'}`,
      `Pin : ${d.account?.pin || '-'}`,
    ].join('\n')

  return (
    <Dialog open={order !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogPortal>
        <DialogOverlay className='bg-[#0A0F1F]/60 backdrop-blur-[4px]' />
        <DialogPrimitive.Content
          aria-modal='true'
          className='fixed top-1/2 left-1/2 z-50 flex max-h-[90vh] w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-[18px] border-0 bg-white shadow-[0_20px_60px_rgba(0,0,0,0.15)] duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 sm:max-w-[760px] dark:bg-[#0F1629] dark:ring-1 dark:ring-white/10'
        >
          {order && (() => {
            const delivered = order.deliveries.filter((d) => d.delivered_at)
            const lastDelivered = delivered[delivered.length - 1]
            const firstAccount = order.deliveries.find((d) => d.account?.email)?.account?.email
            const editable = order.deliveries.some((d) => d.account && !d.delivered_at) ? false : Boolean(firstAccount)

            return (
              <>
                <div className={`flex items-center gap-3 border-b px-6 py-4 sm:px-7 ${hairline}`}>
                  <span className='flex size-9 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/15 text-[#F59E0B]'>
                    <ReceiptText className='size-[18px]' />
                  </span>
                  <DialogPrimitive.Title className={`flex-1 text-lg font-bold sm:text-xl ${navy}`}>Detail Pesanan</DialogPrimitive.Title>
                  <DialogPrimitive.Description className='sr-only'>Informasi lengkap pesanan {order.code}</DialogPrimitive.Description>
                  <DialogPrimitive.Close
                    aria-label='Tutup modal'
                    className='flex size-9 shrink-0 items-center justify-center rounded-full bg-[#F3F4F6] text-[#4B5563] transition duration-200 hover:rotate-90 hover:bg-[#E5E7EB] focus-visible:ring-2 focus-visible:ring-[#F59E0B] focus-visible:outline-none dark:bg-white/10 dark:text-[#D1D5DB] dark:hover:bg-white/20'
                  >
                    <X className='size-4' />
                  </DialogPrimitive.Close>
                </div>

                <div className='space-y-5 overflow-y-auto px-6 py-5 sm:px-7 sm:py-6'>
                  <OrderOverview order={order} />

                  <Panel index={3} className='bg-white shadow-[0_2px_8px_rgba(0,0,0,0.04)] dark:bg-white/[0.03]'>
                    <div className='flex flex-wrap items-center justify-between gap-2'>
                      <h3 className={`text-[15px] font-bold ${navy}`}>AKUN ({order.deliveries.length})</h3>
                      {delivered.length > 0 && <div className='flex flex-wrap items-center gap-x-3 gap-y-1'>
                        {editable ? (
                          <a className={ghostAction} href={`/admin/akun?q=${encodeURIComponent(firstAccount ?? '')}`} target='_blank' rel='noreferrer'>
                            <Pencil className='size-3.5' /> Edit Akun
                          </a>
                        ) : (
                          <button type='button' className={ghostAction} disabled title='Akun belum terkirim'>
                            <Pencil className='size-3.5' /> Edit Akun
                          </button>
                        )}
                        <button
                          type='button'
                          className={ghostAction}
                          disabled={!['paid', 'completed'].includes(order.status)}
                          title='Ganti akun dari stok'
                          onClick={() => setReplacing(order.id)}
                        >
                          <ArrowLeftRight className='size-3.5' /> Ganti Akun
                        </button>
                        <button
                          type='button'
                          className={ghostAction}
                          onClick={() => copy('all', delivered.map((d) => accountText(order, d)).join('\n\n'), 'Akun')}
                        >
                          {copied === 'all' ? <Check className='size-3.5 text-[#10B981]' /> : <Copy className='size-3.5' />} Copy All
                        </button>
                      </div>}
                    </div>

                                        <ul className='mt-3 space-y-3'>
                      {order.deliveries.map((d) => (
                        <li key={d.id}>
                          {order.months > 1 && (
                            <p className={`mb-1.5 text-xs font-semibold ${muted}`}>
                              Bulan ke-{d.sequence}{' '}
                              {d.delivered_at
                                ? `| terkirim ${formatDateTime(d.delivered_at)}`
                                : d.due_on
                                  ? `| jatuh tempo ${formatDay(d.due_on)}`
                                  : '| belum dikirim'}
                              {!d.delivered_at && d.due_on && diffDays(today, d.due_on) <= 0 && (
                                <span className='ms-2 rounded-full bg-[#F59E0B]/15 px-2 py-0.5 text-[11px] font-semibold text-[#B45309] dark:text-[#FBBF24]'>tertunda</span>
                              )}
                            </p>
                          )}
                          {d.account ? (
                            <>
                              <div className='flex items-center justify-between gap-2 rounded-xl border border-dashed border-[#F59E0B]/40 bg-gradient-to-br from-[#FFFBEB] to-[#FEF3C7] px-3.5 py-3 dark:from-[#F59E0B]/10 dark:to-[#F59E0B]/5'>
                                <p className={`min-w-0 break-all text-[13.5px] ${navy}`}>
                                  {[d.account.email, d.account.password].map((v) => v || '-').join('|')}
                                  {d.account.profile ? `|${d.account.profile}` : ''}
                                  {d.account.pin ? `|PIN ${d.account.pin}` : ''}
                                </p>
                                <div className='flex shrink-0 items-center'>
                                  <button type='button' className={iconAction} aria-label={`Salin akun${order.months > 1 ? ` bulan ke-${d.sequence}` : ''}`} onClick={() => copy(`d${d.id}`, accountText(order, d), 'Akun')}>
                                    {copied === `d${d.id}` ? <Check className='text-[#10B981]' /> : <Copy />}
                                  </button>
                                  {d.delivered_at && (
                                    <button
                                      type='button'
                                      className={iconAction}
                                      title='Kirim ulang email akun'
                                      aria-label={`Kirim ulang email akun${order.months > 1 ? ` bulan ke-${d.sequence}` : ''}`}
                                      disabled={busyId === d.id}
                                      onClick={() => resend(order.id, d.id)}
                                    >
                                      <Mail />
                                    </button>
                                  )}
                                </div>
                              </div>
                              <div className='mt-3 flex flex-wrap items-center gap-x-6 gap-y-1.5 text-[13px]'>
                                {d.cogs !== null && (
                                  <span className='inline-flex items-center gap-2'>
                                    <span className={`text-[10.5px] font-semibold uppercase tracking-[0.06em] ${muted}`}>COGS</span>
                                    <strong className={`text-[13.5px] font-semibold ${navy}`}>{formatRupiah(d.cogs)}</strong>
                                  </span>
                                )}
                                {d.account.expires_at && (
                                  <span className='inline-flex items-center gap-1.5 text-[#374151] dark:text-[#D1D5DB]'>
                                    <CalendarDays className='size-3.5 text-[#F59E0B]' /> Exp: {formatDay(d.account.expires_at)}
                                  </span>
                                )}
                              </div>
                            </>
                          ) : (
                            <div className='flex flex-wrap items-center justify-between gap-2 rounded-xl border border-dashed border-[#D1D5DB] px-3.5 py-3 dark:border-white/15'>
                              <p className={`text-[13.5px] ${muted}`}>
                                {order.status !== 'paid'
                                  ? 'Akun dikaitkan otomatis saat pembayaran dikonfirmasi.'
                                  : d.due_on && diffDays(today, d.due_on) <= 0
                                    ? 'Menunggu stok. Akun dikirim otomatis begitu stok ditambah.'
                                    : 'Akun diambil dari stok saat jatuh tempo.'}
                              </p>
                              {order.status === 'paid' && (
                                <button
                                  type='button'
                                  disabled={busyId === d.id}
                                  onClick={() => sendNow(order.id, d.id)}
                                  className='inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-3 py-1.5 text-[12.5px] font-semibold text-[#0A0F1F] shadow-[0_2px_6px_rgba(245,158,11,0.3)] transition hover:brightness-105 active:scale-[0.98] disabled:opacity-60'
                                >
                                  <Send className='size-3.5' /> {busyId === d.id ? 'Mengirim...' : 'Kirim sekarang'}
                                </button>
                              )}
                            </div>
                          )}
                        </li>
                      ))}
                    </ul>
                  </Panel>

                  {order.claims.length > 0 && (
                    <Panel index={4} className='bg-[#F9FAFB] dark:bg-white/[0.03]'>
                      <div className='flex items-center justify-between gap-2'>
                        <h3 className={`text-[15px] font-bold ${navy}`}>RIWAYAT KLAIM ({order.claims.length})</h3>
                        <span className='flex size-6 items-center justify-center rounded-full bg-[#F59E0B] text-xs font-bold text-[#0A0F1F]'>{order.claims.length}</span>
                      </div>
                      <ul className='mt-3 space-y-2.5'>
                        {order.claims.map((c) => (
                          <li key={c.id} className='flex items-center justify-between gap-3 rounded-xl border border-[#F3F4F6] bg-white p-3.5 transition hover:border-[#F59E0B]/40 dark:border-white/10 dark:bg-white/5'>
                            <div className='min-w-0'>
                              <p className={`text-[13.5px] font-semibold ${navy}`}>{c.reason ?? 'Klaim'}</p>
                              {c.status === 'done' ? (
                                <p className={`mt-0.5 flex flex-wrap items-center gap-x-1.5 break-all text-[12.5px] ${muted}`}>
                                  {c.old_email ?? '-'} <ArrowRight className='size-3.5 shrink-0 text-[#F59E0B]' /> {c.new_email ?? '-'}
                                </p>
                              ) : (
                                <p className={`mt-0.5 text-xs ${muted}`}>{c.status === 'open' ? 'Menunggu penggantian' : 'Dibatalkan'}</p>
                              )}
                              <p className='mt-1 text-[11.5px] italic text-[#9CA3AF]'>
                                {c.status === 'done' && (c.mode === 'replacement' ? 'Replacement (dipakai bersama)' : 'Dari stok')}
                                {c.outside_warranty && <span className='ms-2 font-medium not-italic text-[#B45309] dark:text-[#FBBF24]'>di luar garansi</span>}
                              </p>
                            </div>
                            <div className='flex shrink-0 flex-col items-end gap-1.5'>
                              <span className={`text-[12.5px] ${muted}`}>{formatDateTime(c.replaced_at ?? c.created_at)}</span>
                              <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                                c.status === 'done'
                                  ? 'bg-[#10B981]/15 text-[#059669] dark:text-[#34D399]'
                                  : c.status === 'open'
                                    ? 'bg-[#F59E0B]/15 text-[#B45309] dark:text-[#FBBF24]'
                                    : 'bg-[#F3F4F6] text-[#6B7280] dark:bg-white/10 dark:text-[#9CA3AF]'
                              }`}>
                                {c.status === 'done' ? 'Sukses' : c.status === 'open' ? 'Menunggu' : 'Dibatalkan'}
                              </span>
                            </div>
                          </li>
                        ))}
                      </ul>
                    </Panel>
                  )}

                  <OrderPayment order={order} index={5} />

                  <Panel index={6} className='bg-[#F9FAFB] dark:bg-white/[0.03]'>
                    <div className='flex items-center justify-between gap-2'>
                      <h3 className={`flex flex-wrap items-baseline gap-x-1.5 text-[11.5px] font-bold uppercase tracking-[0.05em] ${navy}`}>
                        Notes internal <span className={`text-[11.5px] font-normal normal-case italic ${muted}`}>(admin only)</span>
                      </h3>
                      <button
                        type='button'
                        disabled={noteBusy || note === (order.admin_note ?? '')}
                        onClick={saveNote}
                        className='rounded-[9px] bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-4 py-2 text-[12.5px] font-semibold text-[#0A0F1F] shadow-[0_2px_6px_rgba(245,158,11,0.3)] transition hover:brightness-105 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none'
                      >
                        {noteBusy ? 'Menyimpan...' : 'Simpan'}
                      </button>
                    </div>
                    <textarea
                      rows={4}
                      maxLength={2000}
                      value={note}
                      onChange={(e) => setNote(e.target.value)}
                      placeholder='Tulis notes internal disini...'
                      aria-label='Notes internal'
                      className={`mt-3 min-h-[100px] w-full resize-y rounded-xl border bg-white px-3.5 py-3 text-sm placeholder:text-[#9CA3AF] focus:border-[#F59E0B] focus:shadow-[0_0_0_3px_rgba(245,158,11,0.15)] focus:outline-none dark:bg-transparent ${hairline} ${navy}`}
                    />
                  </Panel>
                </div>

                <div className='flex flex-col-reverse gap-3 border-t border-[#F3F4F6] px-6 py-4 sm:flex-row sm:justify-end sm:px-7 dark:border-white/10'>
                  <Link
                    href={`/admin/log-aktivitas?subjek=order:${order.id}`}
                    className='inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-[#6B7280] transition hover:text-[#0A0F1F] focus-visible:ring-2 focus-visible:ring-[#F59E0B] focus-visible:outline-none sm:me-auto sm:w-auto dark:hover:text-white'
                  >
                    <History className='size-4' /> Riwayat aktivitas
                  </Link>
                  <DialogPrimitive.Close className='w-full rounded-xl bg-[#F3F4F6] px-5 py-2.5 text-sm font-semibold text-[#374151] transition hover:bg-[#E5E7EB] focus-visible:ring-2 focus-visible:ring-[#F59E0B] focus-visible:outline-none sm:w-auto dark:bg-white/10 dark:text-[#E5E7EB] dark:hover:bg-white/20'>
                    Tutup
                  </DialogPrimitive.Close>
                  {lastDelivered && (
                    <button
                      type='button'
                      disabled={busyId === lastDelivered.id}
                      onClick={() => resend(order.id, lastDelivered.id)}
                      className='inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#FBBF24] to-[#F59E0B] px-5 py-2.5 text-sm font-semibold text-[#0A0F1F] shadow-[0_4px_12px_rgba(245,158,11,0.3)] transition hover:shadow-[0_6px_16px_rgba(245,158,11,0.4)] hover:brightness-105 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto'
                    >
                      <Mail className='size-4' /> Resend Email
                    </button>
                  )}
                </div>
              </>
            )
          })()}
          {/* Di dalam Content agar klik di dialog ini tidak dianggap klik di luar modal pesanan. */}
          <ReplaceAccountDialog orderId={replacing} onClose={() => setReplacing(null)} />
        </DialogPrimitive.Content>
      </DialogPortal>
    </Dialog>
  )
}

type RejectProps = { order: OrderRow | null; onClose: () => void }

export function RejectOrderDialog({ order, onClose }: RejectProps) {
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)

  const submit = () => {
    if (!order) return
    setBusy(true)
    router.patch(`/admin/pesanan/${order.id}/tolak`, { reason }, {
      preserveScroll: true,
      onSuccess: () => {
        setReason('')
        onClose()
      },
      onFinish: () => setBusy(false),
    })
  }

  return (
    <Dialog open={order !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Tolak pesanan {order?.code}?</DialogTitle>
          <DialogDescription>
            Stok akun dan kuota voucher dilepas. Alasan tampil ke pelanggan di halaman pesanannya.
          </DialogDescription>
        </DialogHeader>
        <div className='space-y-2'>
          <Label htmlFor='reject_reason'>Alasan (opsional)</Label>
          <Input
            id='reject_reason'
            value={reason}
            maxLength={200}
            onChange={(e) => setReason(e.target.value)}
            placeholder='Contoh: pembayaran belum masuk'
          />
        </div>
        <DialogFooter>
          <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
          <Button type='button' variant='destructive' disabled={busy} onClick={submit}>
            {busy ? 'Menolak...' : 'Tolak pesanan'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
