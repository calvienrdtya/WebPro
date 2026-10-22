import { type Option } from '@/features/products/types'

export type ProductOption = {
  id: number
  name: string
  price: number
  discount_percent: number
  final_price: number
  /** Rata-rata HPP akun produk ini (null bila belum ada akun ber-HPP). */
  average_cost: number | null
}

export type PricedProduct = {
  product_id: number
  name: string
  price_mode: string
  price: number | null
  discount_percent: number
  final_price: number
}

export type VariantRow = {
  id: number
  title: string
  months: number
  is_active: boolean
  products: PricedProduct[]
}

export type ProductPriceForm = {
  product_id: number | ''
  price_mode: string
  price: number | string
  discount_percent: number | string
}

export type VariantForm = {
  title: string
  months: number | string
  is_active: boolean
  products: ProductPriceForm[]
}

export type VariantOptions = {
  products: ProductOption[]
  modes: Option[]
}

export const CUSTOM = 'custom'
export const FROM_PRODUCT = 'from_product'
