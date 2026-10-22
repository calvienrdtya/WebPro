import { createContext, useContext, useEffect, useState } from 'react'
import { AlertCircle, ArrowRight, CalendarCheck, Check, CheckCircle2, Clock, Copy, ExternalLink, Link2, MousePointerClick, ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'
import { copyText } from '@/lib/clipboard'
import { formatRupiah } from '@/lib/format'
import { diffDays, localToday } from '@/features/accounts/dates'
import { formatDateTime, formatDay, type OrderRow } from './types'

/*
 * Bagian tampilan detail pesanan yang dipakai bersama modal Detail Pesanan (menu Pesanan)
 * dan modal Detail Laporan (menu Laporan), supaya keduanya selalu sama persis.
 */

// Palet modal detail: navy + gold (mode gelap memakai navy sebagai dasar).
export const navy = 'text-[#0A0F1F] dark:text-white'
export const muted = 'text-[#6B7280] dark:text-[#9CA3AF]'
export const hairline = 'border-[#E5E7EB] dark:border-white/10'
export const labelCls = `text-[11px] font-semibold uppercase tracking-[0.05em] ${muted}`
export const ghostAction =
  'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[12.5px] font-medium text-[#4B5563] transition hover:bg-[#FEF3C7] hover:text-[#B45309] disabled:pointer-events-none disabled:opacity-45 dark:text-[#D1D5DB] dark:hover:bg-[#F59E0B]/15 dark:hover:text-[#FBBF24]'
export const iconAction =
  'inline-flex size-8 items-center justify-center rounded-lg text-[#6B7280] transition hover:bg-[#FEF3C7] hover:text-[#B45309] disabled:pointer-events-none disabled:opacity-45 dark:hover:bg-[#F59E0B]/15 dark:hover:text-[#FBBF24] [&_svg]:size-4'

export function Row({ label, value, tone }: { label: string; value: string; tone?: 'save' }) {
  const save = tone === 'save'
  return (
    <div className={`flex items-center justify-between gap-3 py-2.5 text-[13.5px] ${save ? '-mx-2 rounded-lg bg-[#D1FAE5]/40 px-2 dark:bg-[#10B981]/10' : ''}`}>
      <dt className={save ? 'text-[#059669] dark:text-[#34D399]' : 'text-[#4B5563] dark:text-[#D1D5DB]'}>{label}</dt>
      <dd className={`tabular-nums ${save ? 'font-semibold text-[#059669] dark:text-[#34D399]' : `font-medium ${navy}`}`}>{value}</dd>
    </div>
  )
}

/** Baris waktu pembayaran dengan ikon gold. */
export function Timeline({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <div className='flex items-center justify-between gap-3 text-[12.5px]'>
      <dt className='flex items-center gap-2 text-[#4B5563] dark:text-[#9CA3AF]'>
        <span className='text-[#F59E0B] [&_svg]:size-3.5'>{icon}</span>
        {label}
      </dt>
      <dd className={`font-medium tabular-nums ${navy}`}>{value}</dd>
    </div>
  )
}

/** Angka naik dari 0 ke nilai akhir saat modal dibuka (dilewati bila pengguna memilih tanpa animasi). */
export function useCountUp(target: number, key: unknown) {
  const [value, setValue] = useState(target)

  useEffect(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setValue(target)
      return
    }
    const start = performance.now()
    let frame = 0
    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / 600)
      setValue(Math.round(target * (1 - Math.pow(1 - t, 3))))
      if (t < 1) frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(frame)
  }, [target, key])

  return value
}

/** "(27 hari lagi)" berwarna menurut sisa hari; sudah lewat = "habis". */
export function DaysLeft({ date, today }: { date: string; today: string }) {
  const left = diffDays(today, date)
  const color =
    left > 7 ? 'text-[#059669] dark:text-[#34D399]' : left > 0 ? 'text-[#B45309] dark:text-[#FBBF24]' : 'text-[#DC2626] dark:text-[#F87171]'

  return <span className={`text-[12.5px] font-medium ${color}`}>({left > 0 ? `${left} hari lagi` : left === 0 ? 'berakhir hari ini' : 'sudah habis'})</span>
}

/** Tiga nada kartu info: gold (garansi), hijau (profil order), biru (akun premium). Ikon bulat solid, latar soft yang terlihat. */
const tones = {
  gold: {
    card: 'border-[#F59E0B] bg-[#FEF3C7]/60 dark:bg-[#F59E0B]/12',
    icon: 'bg-[#F59E0B] text-white shadow-[0_2px_6px_rgba(245,158,11,0.35)]',
    arrow: 'text-[#F59E0B]',
  },
  green: {
    card: 'border-[#10B981] bg-[#D1FAE5]/60 dark:bg-[#10B981]/12',
    icon: 'bg-[#10B981] text-white shadow-[0_2px_6px_rgba(16,185,129,0.35)]',
    arrow: 'text-[#10B981]',
  },
  blue: {
    card: 'border-[#3B82F6] bg-[#E0E7FF]/60 dark:bg-[#3B82F6]/12',
    icon: 'bg-[#3B82F6] text-white shadow-[0_2px_6px_rgba(59,130,246,0.35)]',
    arrow: 'text-[#3B82F6]',
  },
} as const

type Tone = keyof typeof tones

const ToneContext = createContext<Tone>('gold')

/** Panah antar tanggal mengikuti warna kartunya. */
function Arrow() {
  const tone = useContext(ToneContext)
  return <ArrowRight className={`size-3.5 ${tones[tone].arrow}`} />
}

/** Kartu info: ikon bulat di kiri, judul, lalu isi; nada warna dibedakan per kartu. */
export function InfoSection({ icon, title, index, tone, children }: { icon: React.ReactNode; title: string; index: number; tone: Tone; children: React.ReactNode }) {
  const t = tones[tone]
  return (
    <section
      className={`flex items-start gap-3.5 rounded-[14px] border-l-4 p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)] animate-in fade-in-0 slide-in-from-bottom-2 duration-300 ${t.card}`}
      style={{ animationDelay: `${index * 50}ms`, animationFillMode: 'backwards' }}
    >
      <span className={`flex size-9 shrink-0 items-center justify-center rounded-full [&_svg]:size-[18px] ${t.icon}`}>{icon}</span>
      <div className='min-w-0 flex-1'>
        <h3 className={`text-sm font-bold ${navy}`}>{title}</h3>
        <ToneContext.Provider value={tone}>
          <div className='mt-1.5 space-y-1 text-[13.5px] text-[#374151] dark:text-[#D1D5DB]'>{children}</div>
        </ToneContext.Provider>
      </div>
    </section>
  )
}

export function Panel({ index, className = '', children }: { index: number; className?: string; children: React.ReactNode }) {
  return (
    <section
      className={`rounded-2xl border p-4 transition-[border-color,box-shadow] animate-in fade-in-0 slide-in-from-bottom-2 duration-300 hover:border-[#F59E0B]/45 hover:shadow-[0_8px_22px_rgba(10,15,31,0.07)] sm:p-5 ${hairline} ${className}`}
      style={{ animationDelay: `${index * 50}ms`, animationFillMode: 'backwards' }}
    >
      {children}
    </section>
  )
}

export function StatusPill({ order }: { order: OrderRow }) {
  const ok = order.status === 'paid' || order.status === 'completed'
  const cls = ok
    ? 'border-[#10B981]/30 bg-[#10B981]/15 text-[#059669] dark:text-[#34D399]'
    : order.status === 'verifying'
      ? 'border-[#F59E0B]/30 bg-[#F59E0B]/15 text-[#B45309] dark:text-[#FBBF24]'
      : order.status === 'rejected'
        ? 'border-[#DC2626]/30 bg-[#DC2626]/10 text-[#B91C1C] dark:text-[#F87171]'
        : 'border-[#9CA3AF]/40 bg-[#F3F4F6] text-[#4B5563] dark:bg-white/10 dark:text-[#D1D5DB]'

  return (
    <span className={`inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-[12.5px] font-semibold ${cls}`}>
      {ok && <Check className='size-3.5' strokeWidth={3} />}
      {order.status_label}
    </span>
  )
}

export const dateOf = (iso: string) => iso.slice(0, 10)

/** Kode + status, data pelanggan/produk/varian, lalu kartu Garansi Customer, Profil Order, dan Akun Premium. */
export function OrderOverview({ order }: { order: OrderRow }) {
  const [copied, setCopied] = useState(false)
  const today = localToday()
  const delivered = order.deliveries.filter((d) => d.delivered_at)
  const modal = delivered.reduce((sum, d) => sum + (d.cogs ?? 0), 0)
  const buyDate = dateOf(order.paid_at ?? order.created_at)

  const copyCode = async () => {
    if (await copyText(order.code)) {
      setCopied(true)
      toast.success('Kode pesanan disalin')
      setTimeout(() => setCopied(false), 1500)
    }
  }

  return (
    <>
      <div className='flex items-start justify-between gap-3'>
        <div className='min-w-0'>
          <div className='flex items-center gap-1.5'>
            <span className={`text-base tracking-tight sm:text-[17px] ${navy}`}>{order.code}</span>
            <button type='button' className={iconAction} aria-label='Salin kode pesanan' onClick={copyCode}>
              {copied ? <Check className='text-[#10B981]' /> : <Copy />}
            </button>
          </div>
          <p className={`mt-1 text-[13.5px] ${muted}`}>{formatDateTime(order.created_at)}</p>
        </div>
        <StatusPill order={order} />
      </div>

      <div className='grid gap-5 sm:grid-cols-3 sm:gap-6'>
        <div className='min-w-0'>
          <p className={labelCls}>Pelanggan</p>
          <p className={`mt-1.5 text-[15px] font-semibold ${navy}`}>{order.customer_name}</p>
          <p className={`mt-0.5 break-all text-[13.5px] ${muted}`}>{order.customer_email}</p>
          <p className={`text-[13.5px] ${muted}`}>{order.customer_phone}</p>
        </div>
        <div className='min-w-0'>
          <p className={labelCls}>Produk</p>
          <p className={`mt-1.5 text-[15px] font-semibold ${navy}`}>{order.product_name}</p>
          <p className={`mt-0.5 text-[15px] font-bold ${navy}`}>{formatRupiah(order.total)}</p>
          <p className={`text-[13.5px] ${muted}`}>
            Modal {delivered.length ? formatRupiah(modal) : '-'}
            {order.months > 1 && delivered.length ? ` (${delivered.length}/${order.months} akun)` : ''}
          </p>
        </div>
        <div className='min-w-0'>
          <p className={labelCls}>Varian</p>
          <p className={`mt-1.5 text-[15px] font-semibold ${navy}`}>{order.variant_title ?? '1 Bulan'}</p>
          <p className={`mt-0.5 text-[13.5px] ${muted}`}>{order.months} akun</p>
        </div>
      </div>

      <div className='space-y-3'>
        <InfoSection icon={<ShieldCheck />} title='Garansi Customer' index={0} tone='gold'>
          {order.warranty_name ? (
            <>
              <p>
                <span className='font-semibold'>{order.warranty_name}</span> <span className={muted}>| {order.warranty_days} hari per akun</span>
              </p>
              {delivered.length === 0 && <p className={muted}>Masa garansi mulai dihitung saat akun terkirim.</p>}
              {delivered.map((d) =>
                d.warranty_ends_on ? (
                  <p key={d.id} className='flex flex-wrap items-center gap-x-2'>
                    {order.months > 1 && <span className={muted}>Bulan ke-{d.sequence}:</span>}
                    <span>s/d {formatDay(d.warranty_ends_on)}</span>
                    <DaysLeft date={d.warranty_ends_on} today={today} />
                  </p>
                ) : null,
              )}
            </>
          ) : (
            <p>Tanpa garansi.</p>
          )}
        </InfoSection>

        <InfoSection icon={<CalendarCheck />} title='Profil Order' index={1} tone='green'>
          <p className='flex flex-wrap items-center gap-x-2'>
            <span>Beli: <strong className={navy}>{formatDay(buyDate)}</strong></span>
            {order.service_ends_on && (
              <>
                <Arrow />
                <span>Layanan sampai: <strong className={navy}>{formatDay(order.service_ends_on)}</strong></span>
                <DaysLeft date={order.service_ends_on} today={today} />
              </>
            )}
          </p>
          {!order.paid_at && <p className={muted}>Belum dikonfirmasi; tanggal beli memakai tanggal pesanan dibuat.</p>}
        </InfoSection>

        <InfoSection icon={<Link2 />} title='Akun Premium' index={2} tone='blue'>
          {delivered.filter((d) => d.account?.expires_at).length === 0 ? (
            <p className={muted}>Akun belum terkirim.</p>
          ) : (
            delivered.map((d) =>
              d.account?.expires_at ? (
                <p key={d.id} className='flex flex-wrap items-center gap-x-2'>
                  {order.months > 1 && <span className={muted}>Bulan ke-{d.sequence}:</span>}
                  {d.account.started_at && (
                    <>
                      <span>Akun aktif: <strong className={navy}>{formatDay(d.account.started_at)}</strong></span>
                      <Arrow />
                    </>
                  )}
                  <span>Akun mati: <strong className={navy}>{formatDay(d.account.expires_at)}</strong></span>
                  <DaysLeft date={d.account.expires_at} today={today} />
                </p>
              ) : null,
            )
          )}
        </InfoSection>
      </div>
    </>
  )
}

/** Rincian pembayaran, total, waktu bayar, dan bukti transfer. */
export function OrderPayment({ order, index }: { order: OrderRow; index: number }) {
  const totalShown = useCountUp(order.total, order.id)

  return (
    <Panel index={index} className='bg-[#F9FAFB] dark:bg-white/[0.03]'>
      <div className='flex flex-wrap items-center justify-between gap-2'>
        <h3 className={`text-[15px] font-bold ${navy}`}>PEMBAYARAN</h3>
        {order.verified_by && <p className={`text-[12.5px] ${muted}`}>Diproses oleh {order.verified_by}</p>}
      </div>
      <dl className='mt-2 divide-y divide-dashed divide-[#E5E7EB] dark:divide-white/10'>
        <Row label='Harga produk' value={formatRupiah(order.list_price)} />
        {order.list_price > order.product_price && <Row tone='save' label='Diskon produk' value={`- ${formatRupiah(order.list_price - order.product_price)}`} />}
        {order.warranty_name && <Row label={`Garansi (${order.warranty_name})`} value={formatRupiah(order.warranty_price)} />}
        {order.service_fee > 0 && <Row label='Biaya layanan' value={formatRupiah(order.service_fee)} />}
        {order.voucher_code && <Row tone='save' label={`Voucher ${order.voucher_code}`} value={`- ${formatRupiah(order.voucher_discount)}`} />}
      </dl>
      <div className='mt-4 flex items-center justify-between gap-3 rounded-xl border border-[#F59E0B]/40 bg-gradient-to-r from-[#FBBF24]/15 to-[#F59E0B]/15 px-4 py-3.5'>
        <span className={`text-base font-bold ${navy}`}>Total</span>
        <span className={`text-[22px] leading-none font-extrabold tabular-nums ${navy}`}>{formatRupiah(totalShown)}</span>
      </div>
      <dl className='mt-4 space-y-2'>
        <Timeline icon={<Clock />} label='Batas bayar' value={order.expires_at ? formatDateTime(order.expires_at) : '-'} />
        <Timeline icon={<MousePointerClick />} label='Menekan "Sudah Bayar"' value={order.claimed_paid_at ? formatDateTime(order.claimed_paid_at) : '-'} />
        <Timeline icon={<CheckCircle2 />} label='Dikonfirmasi' value={order.paid_at ? formatDateTime(order.paid_at) : '-'} />
      </dl>
      {order.reject_reason && (
        <p className='mt-3 rounded-[10px] border border-[#DC2626]/30 bg-[#DC2626]/10 px-3 py-2 text-[13px] text-[#B91C1C] dark:text-[#F87171]'>Alasan penolakan: {order.reject_reason}</p>
      )}
      {order.has_proof ? (
        <a
          href={`/admin/pesanan/${order.id}/bukti`}
          target='_blank'
          rel='noreferrer'
          className='mt-3 inline-flex items-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-[13px] font-semibold text-[#374151] transition hover:border-[#F59E0B] dark:border-white/15 dark:bg-transparent dark:text-[#D1D5DB]'
        >
          <ExternalLink className='size-4' /> Lihat bukti transfer
        </a>
      ) : (
        <p className='mt-4 flex items-center gap-2.5 rounded-lg border-l-[3px] border-[#F59E0B] bg-[#FEF3C7] px-3 py-2.5 text-[12.5px] italic text-[#374151] dark:bg-[#F59E0B]/10 dark:text-[#FCD34D]'>
          <AlertCircle className='size-4 shrink-0 text-[#F59E0B] not-italic' /> Pelanggan tidak mengunggah bukti transfer.
        </p>
      )}
    </Panel>
  )
}
