export type OrderStatus = 'pending' | 'verifying' | 'paid' | 'completed' | 'expired' | 'rejected' | 'cancelled'

export type DeliveryRow = {
  id: number
  sequence: number
  due_on: string | null
  delivered_at: string | null
  warranty_ends_on: string | null
  cogs: number | null
  account: {
    email: string | null
    password: string | null
    profile: string | null
    pin: string | null
    started_at: string | null
    expires_at: string | null
  } | null
}

export type ClaimHistory = {
  id: number
  status: 'open' | 'done' | 'cancelled'
  reason: string | null
  outside_warranty: boolean
  mode: 'stock' | 'replacement'
  old_email: string | null
  new_email: string | null
  created_at: string
  replaced_at: string | null
}

export type OrderRow = {
  id: number
  code: string
  user_id: number
  customer_name: string
  customer_email: string
  customer_phone: string
  product_name: string
  variant_title: string | null
  months: number
  warranty_name: string | null
  warranty_price: number
  voucher_code: string | null
  voucher_discount: number
  list_price: number
  product_price: number
  service_fee: number
  total: number
  status: OrderStatus
  status_label: string
  reject_reason: string | null
  admin_note: string | null
  warranty_days: number | null
  service_ends_on: string | null
  has_proof: boolean
  verified_by: string | null
  created_at: string
  expires_at: string | null
  claimed_paid_at: string | null
  paid_at: string | null
  deliveries: DeliveryRow[]
  claims: ClaimHistory[]
}

export const statusBadge: Record<OrderStatus, string> = {
  pending: 'bg-muted text-muted-foreground',
  verifying: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
  paid: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
  completed: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
  expired: 'bg-muted text-muted-foreground',
  rejected: 'bg-destructive/15 text-destructive',
  cancelled: 'bg-muted text-muted-foreground',
}

export const formatDateTime = (iso: string) =>
  new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

export const formatDay = (date: string) =>
  new Date(date + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
