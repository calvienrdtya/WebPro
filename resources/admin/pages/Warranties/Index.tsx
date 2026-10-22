import { useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { formatRupiah } from '@/lib/format'
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
import { WarrantyFormDialog } from '@/features/warranties/warranty-form'
import { type ProductOption, type WarrantyRow } from '@/features/warranties/types'

type Props = { warranties: WarrantyRow[]; products: ProductOption[] }

const VISIBLE_PRODUCTS = 3

export default function Index({ warranties, products }: Props) {
  // null = tertutup; { warranty: null } = tambah baru; { warranty } = ubah.
  const [form, setForm] = useState<{ warranty: WarrantyRow | null } | null>(null)
  const [deleting, setDeleting] = useState<WarrantyRow | null>(null)
  const [toggling, setToggling] = useState<number | null>(null)

  return (
    <>
      <Head title='Garansi' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-center justify-between gap-3'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>Garansi</h1>
            <p className='text-sm text-muted-foreground'>
              Paket garansi berbayar yang bisa dipilih pelanggan saat checkout.
            </p>
          </div>
          <Button onClick={() => setForm({ warranty: null })}>
            <Plus /> Tambah Garansi
          </Button>
        </div>

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Nama</TableHead>
                <TableHead>Harga</TableHead>
                <TableHead>Masa garansi</TableHead>
                <TableHead>Produk yang berlaku</TableHead>
                <TableHead>Aktif</TableHead>
                <TableHead className='w-24'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {warranties.length === 0 && (
                <TableRow>
                  <TableCell colSpan={7} className='h-32 text-center text-muted-foreground'>
                    Belum ada garansi. Klik "Tambah Garansi" untuk memulai.
                  </TableCell>
                </TableRow>
              )}
              {warranties.map((warranty, index) => (
                <TableRow key={warranty.id}>
                  <TableCell className='text-muted-foreground'>{index + 1}</TableCell>
                  <TableCell className='max-w-72'>
                    <button
                      type='button'
                      className='block max-w-full truncate text-start font-medium hover:underline'
                      title='Ubah garansi'
                      onClick={() => setForm({ warranty })}
                    >
                      {warranty.name}
                    </button>
                  </TableCell>
                  <TableCell className='whitespace-nowrap font-medium tabular-nums'>
                    {warranty.price === 0 ? 'Gratis' : formatRupiah(warranty.price)}
                  </TableCell>
                  <TableCell className='whitespace-nowrap tabular-nums'>{warranty.duration_days} hari</TableCell>
                  <TableCell>
                    {warranty.applies_to_all ? (
                      <Badge variant='secondary'>Semua produk</Badge>
                    ) : warranty.products.length === 0 ? (
                      <span className='text-sm text-muted-foreground'>Belum dipasang</span>
                    ) : (
                      <div className='flex flex-wrap items-center gap-1.5'>
                        {warranty.products.slice(0, VISIBLE_PRODUCTS).map((p) => (
                          <Badge key={p.id} variant='secondary'>{p.name}</Badge>
                        ))}
                        {warranty.products.length > VISIBLE_PRODUCTS && (
                          <span
                            className='text-xs text-muted-foreground'
                            title={warranty.products.slice(VISIBLE_PRODUCTS).map((p) => p.name).join(', ')}
                          >
                            +{warranty.products.length - VISIBLE_PRODUCTS} lainnya
                          </span>
                        )}
                      </div>
                    )}
                  </TableCell>
                  <TableCell>
                    <Switch
                      checked={warranty.is_active}
                      disabled={toggling === warranty.id}
                      aria-label={`${warranty.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${warranty.name}`}
                      onCheckedChange={() => {
                        setToggling(warranty.id)
                        router.patch(`/admin/garansi/${warranty.id}/aktif`, {}, {
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
                        aria-label={`Ubah garansi ${warranty.name}`}
                        title='Ubah'
                        onClick={() => setForm({ warranty })}
                      >
                        <Pencil />
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                        aria-label={`Hapus garansi ${warranty.name}`}
                        title='Hapus'
                        onClick={() => setDeleting(warranty)}
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

      <WarrantyFormDialog
        open={form !== null}
        onOpenChange={(open) => !open && setForm(null)}
        products={products}
        warranty={form?.warranty}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus garansi?'
        desc={`Garansi "${deleting?.name ?? ''}" akan ${deleting?.applies_to_all ? 'dilepas dari semua produk' : `dilepas dari ${deleting?.products.length ?? 0} produk`} dan tidak ditawarkan lagi. Pesanan yang sudah ada tidak terpengaruh.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => {
          if (!deleting) return
          router.delete(`/admin/garansi/${deleting.id}`, { onFinish: () => setDeleting(null) })
        }}
      />
    </>
  )
}
