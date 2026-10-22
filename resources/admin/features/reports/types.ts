import { type OrderRow } from '@/features/orders/types'

export type ReportStatus = 'open' | 'resolved'

export type ReportOther = {
  id: number
  category_label: string
  subject: string
  status: ReportStatus
  created_at: string
}

export type ReportOrder = {
  id: number
  code: string
  product_name: string
  variant_title: string | null
  warranty_name: string | null
  warranty_open: boolean
  paid_at: string | null
}

export type ReportRow = {
  id: number
  /** Invoice pesanan yang dipilih pelapor, kosong bila laporan tidak terkait pesanan. */
  order_code: string | null
  /** Pesanan terkait dalam bentuk yang sama dengan modal Detail Pesanan. */
  order: OrderRow | null
  user: { id: number; name: string; email: string; phone: string | null }
  category: string
  category_label: string
  subject: string
  body: string
  status: ReportStatus
  resolution: string | null
  resolution_label: string | null
  resolution_note: string | null
  duplicate_of_id: number | null
  resolved_by: string | null
  resolved_at: string | null
  created_at: string
  attachments: { id: number; name: string; url: string }[]
  others: ReportOther[]
  orders: ReportOrder[]
  claim: { id: number; status: 'open' | 'done' | 'cancelled' } | null
}

export type Option = { value: string; label: string }

export const formatDateTime = (iso: string) =>
  new Date(iso).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })

/** Durasi ringkas dari menit, mis. "3 jam". */
export const formatMinutes = (minutes: number | null) => {
  if (minutes === null) return '-'
  if (minutes < 60) return `${Math.max(minutes, 1)} menit`
  if (minutes < 60 * 48) return `${Math.round(minutes / 60)} jam`
  return `${Math.round(minutes / 1440)} hari`
}
