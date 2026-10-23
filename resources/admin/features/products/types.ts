export type Option = { value: string; label: string }

export type ProductOptions = {
  categories: Option[]
  account_types: Option[]
  /** Biaya layanan aktif (Rp), dipakai produk yang tidak punya biaya khusus. */
  service_fee: number
}

export type VariantSummary = {
  id: number
  title: string
  months: number
  is_active: boolean
  mode_label: string
  list_price: number
  discount: number
  final_price: number
}

export type WarrantySummary = {
  id: number
  name: string
  price: number
  duration_days: number
  is_active: boolean
}

export type SpecForm = { label: string; value: string }

export type ProductForm = {
  name: string
  slug: string
  category: string
  /** Nama kategori baru; dibuat otomatis saat produk disimpan (kosong bila memilih yang sudah ada). */
  new_category: string
  account_type: string
  description: string
  highlights: string[]
  specs: SpecForm[]
  price: number | string
  discount_percent: number | string
  service_fee: number | string | null
  meta_title: string
  meta_description: string
  sort_order: number | string
  is_active: boolean
  image: File | null
  remove_image: boolean
}

export type EditableProduct = Omit<ProductForm, 'image' | 'remove_image' | 'new_category'> & {
  id: number
  image_url: string | null
  /** Ukuran asli/hasil dan tombol per gambar (lihat ImageOptimizeInfo). */
  image_info?: import('@/features/images/image-info').ImageInfo
  variants: VariantSummary[]
  warranties: WarrantySummary[]
}
