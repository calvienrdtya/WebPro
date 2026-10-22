export type ProductOption = { id: number; name: string }

export type WarrantyRow = {
  id: number
  name: string
  price: number
  duration_days: number
  is_active: boolean
  applies_to_all: boolean
  products: ProductOption[]
}

export type WarrantyForm = {
  name: string
  price: number | string
  duration_days: number | string
  is_active: boolean
  applies_to_all: boolean
  product_ids: number[]
}
