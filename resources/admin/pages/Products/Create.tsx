import { Head } from '@inertiajs/react'
import { ProductFormView } from '@/features/products/product-form'
import { type ProductOptions } from '@/features/products/types'

export default function Create({ options }: { options: ProductOptions }) {
  return (
    <>
      <Head title='Tambah Produk' />
      <ProductFormView options={options} />
    </>
  )
}
