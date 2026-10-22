import { useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { AlertTriangle, ArrowLeftRight, Boxes, Check, Recycle } from 'lucide-react'
import { formatRupiah } from '@/lib/format'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { formatDay } from './types'

type Delivery = {
  id: number
  sequence: number
  delivered_at: string
  warranty_ends_on: string | null
  within_warranty: boolean
  account: { email: string | null; profile: string | null; started_at: string | null; expires_at: string | null; days_left: number | null } | null
}

type Candidate = { id: number; email: string | null; profile: string | null; cogs: number; expires_at: string | null; days_left: number | null }

type Replacement = { id: number; email: string | null; profile: string | null; expires_at: string | null; days_left: number | null; users: number; from_order: string | null }

type Options = {
  order: { id: number; code: string; customer_name: string; product_name: string; months: number; paid_at: string | null; warranty_name: string | null; age_days: number | null }
  deliveries: Delivery[]
  open_claim_id: number | null
  reasons: { value: string; label: string }[]
  candidates: Candidate[]
  replacements: Replacement[]
}

type Props = { orderId: number | null; onClose: () => void }

/** Ganti akun satu kiriman: ambil dari stok (HPP terendah dulu) atau Replacement (akun bekas pesanan lain, dipakai bersama). */
export function ReplaceAccountDialog({ orderId, onClose }: Props) {
  return (
    <Dialog open={orderId !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className='max-h-[92vh] overflow-y-auto sm:max-w-2xl'>
        {/* Dipasang ulang tiap dibuka agar data dan isian selalu segar. */}
        {orderId !== null && <Body orderId={orderId} onClose={onClose} />}
      </DialogContent>
    </Dialog>
  )
}

function Body({ orderId, onClose }: { orderId: number; onClose: () => void }) {
  const [data, setData] = useState<Options | null>(null)
  const [error, setError] = useState('')
  const [deliveryId, setDeliveryId] = useState('')
  const [pick, setPick] = useState<{ mode: 'stock' | 'replacement'; id: number | null }>({ mode: 'stock', id: null })
  const [reason, setReason] = useState('')
  const [note, setNote] = useState('')
  const [confirmOutside, setConfirmOutside] = useState(false)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let alive = true
    fetch(`/admin/pesanan/${orderId}/pengganti`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then((res) => (res.ok ? res.json() : Promise.reject(new Error('Gagal memuat data pesanan.'))))
      .then((json: Options) => {
        if (!alive) return
        setData(json)
        setDeliveryId(String(json.deliveries[json.deliveries.length - 1]?.id ?? ''))
      })
      .catch((e: Error) => alive && setError(e.message))
    return () => {
      alive = false
    }
  }, [orderId])

  const delivery = data?.deliveries.find((d) => String(d.id) === deliveryId) ?? null
  const outside = delivery ? !delivery.within_warranty : false
  const oldLeft = delivery?.account?.days_left ?? null
  const accountId = pick.id
  const mode = pick.mode
  const canSubmit = Boolean(delivery && accountId && reason && (!outside || confirmOutside)) && !busy

  const submit = () => {
    if (!data || !delivery || !accountId) return
    setBusy(true)
    router.post(`/admin/pesanan/${orderId}/ganti-akun`, {
      delivery_id: delivery.id,
      account_id: accountId,
      reason,
      note,
      confirm_outside: confirmOutside,
      claim_id: data.open_claim_id,
      mode,
    }, {
      preserveScroll: true,
      preserveState: true,
      // Kegagalan bisnis (mis. stok diambil orang lain) kembali sebagai pesan error flash: dialog tetap terbuka.
      onSuccess: (page) => {
        if (!(page.props as { flash?: { error?: string } }).flash?.error) onClose()
      },
      onFinish: () => setBusy(false),
    })
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle className='flex items-center gap-2'>
          <ArrowLeftRight className='size-5 text-primary' /> Ganti Akun
        </DialogTitle>
        <DialogDescription>
          {data ? `${data.order.code} | ${data.order.customer_name} | ${data.order.product_name}` : 'Memuat data pesanan...'}
        </DialogDescription>
      </DialogHeader>

      {error && <p className='rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive'>{error}</p>}

      {data && !delivery && <p className='text-sm text-muted-foreground'>Belum ada akun terkirim untuk pesanan ini.</p>}

      {data && delivery && (
        <div className='space-y-4 text-sm'>
          <div className='grid gap-2 sm:grid-cols-3'>
            <Info label='Umur order' value={data.order.age_days === null ? '-' : `${data.order.age_days} hari`} sub={data.order.paid_at ? `sejak ${formatDay(data.order.paid_at)}` : undefined} />
            <Info
              label='Garansi'
              value={delivery.warranty_ends_on ? (delivery.within_warranty ? `s/d ${formatDay(delivery.warranty_ends_on)}` : 'Sudah habis') : 'Tanpa garansi'}
              sub={delivery.warranty_ends_on && !delivery.within_warranty ? `berakhir ${formatDay(delivery.warranty_ends_on)}` : (data.order.warranty_name ?? undefined)}
              tone={outside ? 'bad' : 'good'}
            />
            <Info
              label='Akun aktif'
              value={oldLeft === null ? '-' : `sisa ${oldLeft} hari`}
              sub={delivery.account?.expires_at ? `mati ${formatDay(delivery.account.expires_at)}` : undefined}
            />
          </div>

          {data.deliveries.length > 1 && (
            <div className='space-y-2'>
              <Label htmlFor='delivery'>Akun yang diganti</Label>
              <Select value={deliveryId} onValueChange={(v) => { setDeliveryId(v); setConfirmOutside(false) }}>
                <SelectTrigger id='delivery' className='w-full'><SelectValue /></SelectTrigger>
                <SelectContent>
                  {data.deliveries.map((d) => (
                    <SelectItem key={d.id} value={String(d.id)}>Bulan ke-{d.sequence} | {d.account?.email ?? '-'}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
          {data.deliveries.length === 1 && delivery.account && (
            <p className='rounded-lg border bg-muted/40 px-3 py-2'>
              <span className='text-muted-foreground'>Akun sekarang: </span>
              <span className='break-all text-[13px]'>{delivery.account.email}{delivery.account.profile ? ` | ${delivery.account.profile}` : ''}</span>
            </p>
          )}

          <div className='space-y-3'>
            <div className='flex items-center justify-between gap-2'>
              <h3 className='text-xs font-bold uppercase tracking-wide'>Pilihan akun pengganti</h3>
              <span className='text-xs text-muted-foreground'>{data.replacements.length} replacement + {data.candidates.length} stok</span>
            </div>

            {/* Replacement: akun bekas pesanan lain, dipakai bersama. */}
            <section className='overflow-hidden rounded-xl border border-amber-500/40 bg-amber-500/5'>
              <div className='flex items-center justify-between gap-2 border-b border-amber-500/30 bg-amber-500/10 px-3 py-2'>
                <div className='min-w-0'>
                  <p className='flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-400'>
                    <Recycle className='size-4' /> Replacement
                  </p>
                  <p className='text-xs text-muted-foreground'>Akun bekas pesanan lain yang garansinya sudah habis, dipakai bersama.</p>
                </div>
                <span className='shrink-0 rounded-full bg-amber-500/20 px-2 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-400'>{data.replacements.length}</span>
              </div>
              {data.replacements.length === 0 ? (
                <p className='px-3 py-4 text-center text-xs text-muted-foreground'>Belum ada akun replacement untuk produk ini.</p>
              ) : (
                <ul className='max-h-52 divide-y divide-amber-500/20 overflow-y-auto' role='radiogroup' aria-label='Akun replacement'>
                  {data.replacements.map((r, i) => (
                    <AccountRow
                      key={r.id}
                      index={i + 1}
                      email={r.email}
                      profile={r.profile}
                      sub={`dari ${r.from_order ?? '-'} | dipakai ${r.users} orang`}
                      daysLeft={r.days_left}
                      longer={oldLeft !== null && r.days_left !== null && r.days_left > oldLeft}
                      selected={pick.mode === 'replacement' && pick.id === r.id}
                      tone='amber'
                      onPick={() => setPick({ mode: 'replacement', id: r.id })}
                    />
                  ))}
                </ul>
              )}
            </section>

            {/* Stok: akun baru, HPP terendah dulu. */}
            <section className='overflow-hidden rounded-xl border'>
              <div className='flex items-center justify-between gap-2 border-b bg-muted/50 px-3 py-2'>
                <div className='min-w-0'>
                  <p className='flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-400'>
                    <Boxes className='size-4' /> Ambil dari stok
                  </p>
                  <p className='text-xs text-muted-foreground'>Akun baru yang tersedia, HPP terendah dulu.</p>
                </div>
                <span className='shrink-0 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-400'>{data.candidates.length}</span>
              </div>
              {data.candidates.length === 0 ? (
                <p className='px-3 py-4 text-center text-xs text-muted-foreground'>Stok akun {data.order.product_name} kosong.</p>
              ) : (
                <ul className='max-h-60 divide-y overflow-y-auto' role='radiogroup' aria-label='Akun dari stok'>
                  {data.candidates.map((c, i) => (
                    <AccountRow
                      key={c.id}
                      index={i + 1}
                      email={c.email}
                      profile={c.profile}
                      sub={c.cogs === 0 ? 'HPP 0' : `HPP ${formatRupiah(c.cogs)}`}
                      daysLeft={c.days_left}
                      longer={oldLeft !== null && c.days_left !== null && c.days_left > oldLeft}
                      selected={pick.mode === 'stock' && pick.id === c.id}
                      tone='emerald'
                      onPick={() => setPick({ mode: 'stock', id: c.id })}
                    />
                  ))}
                </ul>
              )}
            </section>
          </div>

          <div className='grid gap-3 sm:grid-cols-2'>
            <div className='space-y-2'>
              <Label htmlFor='reason'>Alasan masuk bengkel <span className='text-destructive'>*</span></Label>
              <Select value={reason} onValueChange={setReason}>
                <SelectTrigger id='reason' className='w-full'><SelectValue placeholder='-- Pilih alasan --' /></SelectTrigger>
                <SelectContent>
                  {data.reasons.map((r) => (<SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>))}
                </SelectContent>
              </Select>
            </div>
            <div className='space-y-2'>
              <Label htmlFor='claim_note'>Catatan (opsional)</Label>
              <Input id='claim_note' value={note} maxLength={200} onChange={(e) => setNote(e.target.value)} placeholder='Contoh: profil terkunci' />
            </div>
          </div>

          {outside && (
            <label className='flex cursor-pointer items-start gap-2.5 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2.5'>
              <input type='checkbox' className='mt-0.5 size-4 accent-amber-500' checked={confirmOutside} onChange={(e) => setConfirmOutside(e.target.checked)} />
              <span>
                <span className='font-semibold'>Garansi sudah habis atau pesanan tanpa garansi.</span>
                <span className='block text-muted-foreground'>Centang untuk tetap mengganti akun (keputusan admin). Klaim ditandai "di luar garansi".</span>
              </span>
            </label>
          )}
        </div>
      )}

      <DialogFooter>
        <Button type='button' variant='outline' onClick={onClose}>Batal</Button>
        <Button type='button' disabled={!canSubmit} onClick={submit} className='gap-1.5'>
          <ArrowLeftRight className='size-4' /> {busy ? 'Mengganti...' : 'Ganti Akun'}
        </Button>
      </DialogFooter>
    </>
  )
}

function AccountRow({ index, email, profile, sub, daysLeft, longer, selected, tone, onPick }: {
  index: number
  email: string | null
  profile: string | null
  sub: string
  daysLeft: number | null
  longer: boolean
  selected: boolean
  tone: 'amber' | 'emerald'
  onPick: () => void
}) {
  const accent = tone === 'amber'
    ? { circle: 'bg-amber-500/15 text-amber-700 dark:text-amber-400', btn: 'bg-amber-500 text-white hover:bg-amber-600', row: 'bg-amber-500/10' }
    : { circle: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400', btn: 'bg-emerald-600 text-white hover:bg-emerald-700', row: 'bg-emerald-500/10' }

  return (
    <li className={`flex items-center gap-3 px-3 py-2.5 transition ${selected ? accent.row : 'hover:bg-muted/40'}`}>
      <span className={`flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${accent.circle}`}>{index}</span>
      <div className='min-w-0 flex-1'>
        <p className='truncate text-[13px]' title={email ?? ''}>{email ?? '-'}{profile ? ` | ${profile}` : ''}</p>
        <p className='truncate text-xs text-muted-foreground'>
          {sub}
          {longer && <span className='ms-2 inline-flex items-center gap-1 text-amber-600'><AlertTriangle className='size-3' /> melebihi sisa akun lama</span>}
        </p>
      </div>
      <span className='shrink-0 rounded-md bg-amber-500/15 px-2 py-0.5 text-xs font-semibold tabular-nums text-amber-700 dark:text-amber-400'>
        {daysLeft === null ? '-' : `${daysLeft} hari`}
      </span>
      <button
        type='button'
        role='radio'
        aria-checked={selected}
        onClick={onPick}
        className={`inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md px-3 text-xs font-semibold transition ${selected ? 'ring-2 ring-primary ring-offset-1 ring-offset-background ' + accent.btn : accent.btn}`}
      >
        {selected ? <><Check className='size-3.5' /> Dipilih</> : 'Pilih'}
      </button>
    </li>
  )
}

function Info({ label, value, sub, tone }: { label: string; value: string; sub?: string; tone?: 'good' | 'bad' }) {
  const color = tone === 'bad' ? 'text-destructive' : ''
  return (
    <div className='rounded-lg border px-3 py-2'>
      <p className='text-xs text-muted-foreground'>{label}</p>
      <p className={`font-semibold ${color}`}>{value}</p>
      {sub && <p className='text-xs text-muted-foreground'>{sub}</p>}
    </div>
  )
}
