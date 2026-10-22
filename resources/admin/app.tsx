import { type ComponentType, type ReactNode } from 'react'
import { createInertiaApp } from '@inertiajs/react'
import { AdminLayout } from '@/components/layout/admin-layout'
import './styles/index.css'

type PageModule = {
  default: ComponentType & { layout?: (page: ReactNode) => ReactNode }
}

// Code-splitting: tiap halaman jadi file terpisah yang baru diunduh saat halaman itu dibuka, jadi membuka
// Dashboard tidak ikut mengunduh kode Pesanan, AI, Tampilan Toko, dan lainnya.
// Nama toko dari Tampilan Toko > Identitas (diisi server di admin.blade.php).
const storeName = document.querySelector<HTMLMetaElement>('meta[name="store-name"]')?.content || 'Panel'

const pages = import.meta.glob<PageModule>('./pages/**/*.tsx')

createInertiaApp({
  title: (title) => (title ? `${title} - ${storeName} Admin` : `${storeName} Admin`),
  resolve: async (name) => {
    const page = await pages[`./pages/${name}.tsx`]()
    page.default.layout ??= (children) => <AdminLayout>{children}</AdminLayout>
    return page.default
  },
})
