import { type Option } from '@/features/products/types'

export type StatusOption = Option

export type AccountRow = {
  id: number
  product_id: number
  product_name: string
  product_slug: string
  email: string | null
  password: string | null
  profile: string | null
  pin: string | null
  cogs: number | null
  status: string
  sold_to: string | null
  sold_at: string | null
  created_at: string
  started_at: string | null
  active_days: number
  expires_at: string | null
  note: string | null
}

export type AccountForm = {
  product_id: string
  email: string
  password: string
  profile: string
  pin: string
  cogs: string
  profile_count: string
  status: string
  sold_to: string
  started_at: string
  active_days: string
  note: string
}

export type AccountFormOptions = {
  products: Option[]
  statuses: StatusOption[]
}
