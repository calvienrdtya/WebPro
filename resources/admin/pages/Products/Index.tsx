import { useEffect, useRef, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { MoreHorizontal, Package, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { formatRupiah } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { type Option } from '@/features/products/types'

type ProductRow = {
  id: number
  name: string
  slug: string
  category: string
  category_label: string
  image_url: string | null
  is_active: boolean
  price: number
  discount_percent: number
  final_price: number
  variants_count: number
  price_min: number | null
  price_max: number | null
}

type Paginated<T> = {
  data: T[]
  current_page: number
  last_page: number
  total: number
  from: number | null
  to: number | null
  prev_page_url: string | null
  next_page_url: string | null
}

type Filters = { q: string; category: string; status: string }

type Props = {
  products: Paginated<ProductRow>
  filters: Filters
  categories: Option[]
}

const ALL = 'all'
const URL = '/admin/produk'

export default function Index({ products, filters, categories }: Props) {
  const [q, setQ] = useState(filters.q)
  const [deleting, setDeleting] = useState<ProductRow | null>(null)
  const [toggling, setToggling] = useState<number | null>(null)
  const firstRun = useRef(true)

  const visit = (patch: Partial<Filters>) => {
    const next = { ...filters, q, ...patch }
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v))
    router.get(URL, params, { preserveState: true, replace: true })
  }

  useEffect(() => {
    if (firstRun.current) {
      firstRun.current = false
      return
    }
    const timer = setTimeout(() => visit({ q }), 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q])

  const hasFilters = Boolean(filters.q || filters.category || filters.status)

  return (
    <>
      <Head title='Produk' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-center justify-between gap-3'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>Produk</h1>
            <p className='text-sm text-muted-foreground'>
              Kelola katalog produk, varian durasi, dan harga.
            </p>
          </div>
          <Button asChild>
            <Link href={`${URL}/baru`}>
              <Plus /> Tambah Produk
            </Link>
          </Button>
        </div>

        <div className='mb-4 flex flex-col gap-2 sm:flex-row'>
          <div className='relative flex-1'>
            <Search className='absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' aria-hidden='true' />
            <Input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder='Cari nama atau slug produk'
              aria-label='Cari produk'
              className='ps-9'
            />
          </div>
          <Select value={filters.category || ALL} onValueChange={(v) => visit({ category: v === ALL ? '' : v })}>
            <SelectTrigger className='w-full sm:w-44' aria-label='Filter kategori'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>Semua kategori</SelectItem>
              {categories.map((c) => (
                <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select value={filters.status || ALL} onValueChange={(v) => visit({ status: v === ALL ? '' : v })}>
            <SelectTrigger className='w-full sm:w-40' aria-label='Filter status'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>Semua status</SelectItem>
              <SelectItem value='active'>Aktif</SelectItem>
              <SelectItem value='inactive'>Nonaktif</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Produk</TableHead>
                <TableHead className='hidden md:table-cell'>Kategori</TableHead>
                <TableHead className='hidden lg:table-cell'>Harga produk</TableHead>
                <TableHead className='hidden sm:table-cell'>Varian &amp; harga</TableHead>
                <TableHead>Aktif</TableHead>
                <TableHead className='w-12'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {products.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className='h-32 text-center text-muted-foreground'>
                    {hasFilters ? 'Tidak ada produk yang cocok dengan pencarian.' : 'Belum ada produk. Klik "Tambah Produk" untuk memulai.'}
                  </TableCell>
                </TableRow>
              )}
              {products.data.map((product) => (
                <TableRow key={product.id}>
                  <TableCell>
                    <div className='flex items-center gap-3'>
                      <div className='flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted'>
                        {product.image_url ? (
                          <img src={product.image_url} alt='' className='size-full object-cover' loading='lazy' />
                        ) : (
                          <Package className='size-4 text-muted-foreground' aria-hidden='true' />
                        )}
                      </div>
                      <div className='min-w-0'>
                        <Link href={`${URL}/${product.id}/ubah`} className='block truncate font-medium hover:underline'>
                          {product.name}
                        </Link>
                        <p className='truncate text-xs text-muted-foreground'>/{product.slug}</p>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className='hidden md:table-cell'>
                    <Badge variant='secondary'>{product.category_label}</Badge>
                  </TableCell>
                  <TableCell className='hidden lg:table-cell'>
                    <p className='text-sm font-medium'>{formatRupiah(product.final_price)}</p>
                    {product.discount_percent > 0 && (
                      <p className='text-xs text-muted-foreground'>
                        <span className='line-through'>{formatRupiah(product.price)}</span>{' '}
                        <span className='text-emerald-600'>-{product.discount_percent}%</span>
                      </p>
                    )}
                  </TableCell>
                  <TableCell className='hidden sm:table-cell'>
                    <p className='text-sm'>
                      {product.price_min === null
                        ? 'Belum ada varian'
                        : product.price_min === product.price_max
                          ? formatRupiah(product.price_min)
                          : `${formatRupiah(product.price_min)} - ${formatRupiah(product.price_max ?? 0)}`}
                    </p>
                    <p className='text-xs text-muted-foreground'>{product.variants_count} varian</p>
                  </TableCell>
                  <TableCell>
                    <Switch
                      checked={product.is_active}
                      disabled={toggling === product.id}
                      aria-label={`${product.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${product.name}`}
                      onCheckedChange={() => {
                        setToggling(product.id)
                        router.patch(`${URL}/${product.id}/aktif`, {}, {
                          preserveScroll: true,
                          preserveState: true,
                          onFinish: () => setToggling(null),
                        })
                      }}
                    />
                  </TableCell>
                  <TableCell>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <Button variant='ghost' size='icon' aria-label={`Aksi untuk ${product.name}`}>
                          <MoreHorizontal />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align='end'>
                        <DropdownMenuItem asChild>
                          <Link href={`${URL}/${product.id}/ubah`}>
                            <Pencil /> Ubah
                          </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant='destructive' onClick={() => setDeleting(product)}>
                          <Trash2 /> Hapus
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        <div className='mt-4 flex flex-wrap items-center justify-between gap-2'>
          <p className='text-sm text-muted-foreground'>
            {products.total > 0
              ? `Menampilkan ${products.from}-${products.to} dari ${products.total} produk`
              : 'Tidak ada produk'}
          </p>
          <div className='flex gap-2'>
            <Button variant='outline' size='sm' asChild={Boolean(products.prev_page_url)} disabled={!products.prev_page_url}>
              {products.prev_page_url ? <Link href={products.prev_page_url} preserveScroll>Sebelumnya</Link> : <span>Sebelumnya</span>}
            </Button>
            <Button variant='outline' size='sm' asChild={Boolean(products.next_page_url)} disabled={!products.next_page_url}>
              {products.next_page_url ? <Link href={products.next_page_url} preserveScroll>Berikutnya</Link> : <span>Berikutnya</span>}
            </Button>
          </div>
        </div>
      </Main>

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus produk?'
        desc={`Produk "${deleting?.name ?? ''}" akan disembunyikan dari toko dan daftar ini. Alamat (slug) produk tidak bisa dipakai lagi.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => {
          if (!deleting) return
          router.delete(`${URL}/${deleting.id}`, { onFinish: () => setDeleting(null) })
        }}
      />
    </>
  )
}
