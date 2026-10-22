export type ProductOption = { id: number; name: string }

export type VoucherStatus = 'running' | 'scheduled' | 'ended' | 'inactive'

export type VoucherRow = {
  id: number
  code: string
  title: string
  description: string | null
  discount_type: 'percent' | 'fixed'
  discount_value: number
  max_discount: number | null
  min_purchase: number | null
  starts_on: string | null
  ends_on: string | null
  quota: number | null
  used_count: number
  claims_count: number
  per_user_limit: number | null
  requires_claim: boolean
  is_listed: boolean
  is_active: boolean
  applies_to_all: boolean
  status: VoucherStatus
  products: ProductOption[]
}

export type VoucherForm = {
  code: string
  title: string
  description: string
  discount_type: 'percent' | 'fixed'
  discount_value: number | string
  max_discount: number | string
  min_purchase: number | string
  starts_on: string
  ends_on: string
  quota: number | string
  per_user_limit: number | string
  requires_claim: boolean
  is_listed: boolean
  is_active: boolean
  applies_to_all: boolean
  product_ids: number[]
}
