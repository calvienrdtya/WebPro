import { useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
import { VariantFormDialog } from '@/features/variants/variant-form'
import { type VariantOptions, type VariantRow } from '@/features/variants/types'

type Props = VariantOptions & { variants: VariantRow[] }

const VISIBLE_PRODUCTS = 3

export default function Index({ variants, products, modes }: Props) {
  // null = tertutup; { variant: null } = tambah baru; { variant } = ubah.
  const [form, setForm] = useState<{ variant: VariantRow | null } | null>(null)
  const [deleting, setDeleting] = useState<VariantRow | null>(null)
  const [toggling, setToggling] = useState<number | null>(null)

  return (
    <>
      <Head title='Varian' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-center justify-between gap-3'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>Varian</h1>
            <p className='text-sm text-muted-foreground'>
              Pilihan durasi di halaman produk. Produk tanpa varian dijual 1 bulan dengan harga produk.
            </p>
          </div>
          <Button onClick={() => setForm({ variant: null })}>
            <Plus /> Tambah Varian
          </Button>
        </div>

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Title</TableHead>
                <TableHead>Durasi</TableHead>
                <TableHead>Produk yang berlaku</TableHead>
                <TableHead>Aktif</TableHead>
                <TableHead className='w-24'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {variants.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className='h-32 text-center text-muted-foreground'>
                    Belum ada varian. Klik "Tambah Varian" untuk memulai.
                  </TableCell>
                </TableRow>
              )}
              {variants.map((variant, index) => (
                <TableRow key={variant.id}>
                  <TableCell className='text-muted-foreground'>{index + 1}</TableCell>
                  <TableCell>
                    <button
                      type='button'
                      className='font-medium hover:underline'
                      title='Ubah varian'
                      onClick={() => setForm({ variant })}
                    >
                      {variant.title}
                    </button>
                  </TableCell>
                  <TableCell className='whitespace-nowrap tabular-nums'>{variant.months} bulan</TableCell>
                  <TableCell>
                    {variant.products.length === 0 ? (
                      <span className='text-sm text-muted-foreground'>Belum dipasang</span>
                    ) : (
                      <div className='flex flex-wrap items-center gap-1.5'>
                        {variant.products.slice(0, VISIBLE_PRODUCTS).map((p) => (
                          <Badge key={p.product_id} variant='secondary'>{p.name}</Badge>
                        ))}
                        {variant.products.length > VISIBLE_PRODUCTS && (
                          <span
                            className='text-xs text-muted-foreground'
                            title={variant.products.slice(VISIBLE_PRODUCTS).map((p) => p.name).join(', ')}
                          >
                            +{variant.products.length - VISIBLE_PRODUCTS} lainnya
                          </span>
                        )}
                      </div>
                    )}
                  </TableCell>
                  <TableCell>
                    <Switch
                      checked={variant.is_active}
                      disabled={toggling === variant.id}
                      aria-label={`${variant.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${variant.title}`}
                      onCheckedChange={() => {
                        setToggling(variant.id)
                        router.patch(`/admin/varian/${variant.id}/aktif`, {}, {
                          preserveScroll: true,
                          preserveState: true,
                          onFinish: () => setToggling(null),
                        })
                      }}
                    />
                  </TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8'
                        aria-label={`Ubah varian ${variant.title}`}
                        title='Ubah'
                        onClick={() => setForm({ variant })}
                      >
                        <Pencil />
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                        aria-label={`Hapus varian ${variant.title}`}
                        title='Hapus'
                        onClick={() => setDeleting(variant)}
                      >
                        <Trash2 />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </Main>

      <VariantFormDialog
        open={form !== null}
        onOpenChange={(open) => !open && setForm(null)}
        options={{ products, modes }}
        variant={form?.variant}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus varian?'
        desc={`Varian "${deleting?.title ?? ''}" akan dilepas dari ${deleting?.products.length ?? 0} produk dan hilang dari halaman produk. Pesanan yang sudah ada tidak terpengaruh.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => {
          if (!deleting) return
          router.delete(`/admin/varian/${deleting.id}`, { onFinish: () => setDeleting(null) })
        }}
      />
    </>
  )
}
