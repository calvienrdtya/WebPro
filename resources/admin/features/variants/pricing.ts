import { CUSTOM, type ProductOption, type ProductPriceForm } from './types'

export type Quote = {
  list: number
  discount: number
  final: number
  perMonth: number
  warnings: string[]
}

/** Harga sebuah baris produk, dengan aturan yang sama seperti di server. Null bila belum lengkap. */
export function quote(row: ProductPriceForm, product: ProductOption | undefined, months: number): Quote | null {
  if (!product || !Number.isInteger(months) || months < 1) return null

  const custom = row.price_mode === CUSTOM
  const list = custom ? Number(row.price) : product.price * months
  const discount = custom ? Number(row.discount_percent) || 0 : product.discount_percent

  if (!list || list < 1 || discount < 0 || discount > 99) return null

  const final = Math.round((list * (100 - discount)) / 100)
  const perMonth = Math.round(final / months)
  const warnings: string[] = []

  // Peringatan saja, tidak memblokir penyimpanan.
  if (months > 1 && perMonth >= product.final_price)
    warnings.push(`Per bulan tidak lebih murah daripada beli 1 bulan (${rupiah(product.final_price)}).`)

  if (product.average_cost !== null && final < product.average_cost * months)
    warnings.push(`Di bawah perkiraan modal ${rupiah(product.average_cost * months)}.`)

  return { list, discount, final, perMonth, warnings }
}

const fmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 })
const rupiah = (n: number) => fmt.format(n)
