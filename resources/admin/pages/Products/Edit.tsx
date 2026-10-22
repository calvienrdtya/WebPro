import { Head } from '@inertiajs/react'
import { ProductFormView } from '@/features/products/product-form'
import {
  type EditableProduct,
  type ProductOptions,
} from '@/features/products/types'

export default function Edit({
  options,
  product,
}: {
  options: ProductOptions
  product: EditableProduct
}) {
  return (
    <>
      <Head title={`Ubah ${product.name}`} />
      <ProductFormView key={product.id} options={options} product={product} />
    </>
  )
}
