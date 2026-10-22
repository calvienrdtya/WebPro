import { useState } from 'react'
import { Head, router } from '@inertiajs/react'
import { Info, Pencil, Plus, Trash2 } from 'lucide-react'
import { formatNumber, formatRupiah } from '@/lib/format'
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
import { ServiceFeeFormDialog } from '@/features/service-fees/service-fee-form'
import { type ServiceFeeRow } from '@/features/service-fees/types'

type Props = { fees: ServiceFeeRow[] }

export default function Index({ fees }: Props) {
  // null = tertutup; { fee: null } = tambah baru; { fee } = ubah.
  const [form, setForm] = useState<{ fee: ServiceFeeRow | null } | null>(null)
  const [deleting, setDeleting] = useState<ServiceFeeRow | null>(null)
  const [toggling, setToggling] = useState<number | null>(null)

  const active = fees.find((f) => f.is_active)

  return (
    <>
      <Head title='Biaya Layanan' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-wrap items-center justify-between gap-3'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>Biaya Layanan</h1>
            <p className='text-sm text-muted-foreground'>
              Biaya admin yang ditambahkan ke setiap pesanan baru. Hanya satu yang aktif dalam satu waktu.
            </p>
          </div>
          <Button onClick={() => setForm({ fee: null })}>
            <Plus /> Buat Biaya Layanan
          </Button>
        </div>

        <div className='mb-4 grid gap-3 sm:grid-cols-2'>
          <StatCard label='Biaya yang berlaku sekarang' value={active ? formatRupiah(active.amount) : 'Tidak ada'} note={active?.name ?? 'Pesanan baru tidak dikenai biaya layanan.'} />
          <StatCard label='Jumlah biaya layanan' value={formatNumber(fees.length)} note={`${active ? 1 : 0} aktif`} />
        </div>

        <div className='mb-4 flex items-start gap-2 rounded-lg border bg-muted/40 px-3 py-2 text-sm text-muted-foreground'>
          <Info className='mt-0.5 size-4 shrink-0' aria-hidden='true' />
          <p>
            Nominal disalin ke pesanan saat dibuat, jadi mengubah atau menghapus biaya tidak mengubah pesanan yang sudah ada. Pesanan yang sedang menunggu pembayaran tetap memakai biaya saat pesanan dibuat.
          </p>
        </div>

        <div className='rounded-lg border'>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className='w-12'>No</TableHead>
                <TableHead>Nama Biaya</TableHead>
                <TableHead>Nominal</TableHead>
                <TableHead>Aktif</TableHead>
                <TableHead className='w-24'><span className='sr-only'>Aksi</span></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {fees.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className='h-32 text-center text-muted-foreground'>
                    Belum ada biaya layanan. Klik "Buat Biaya Layanan" untuk memulai.
                  </TableCell>
                </TableRow>
              )}
              {fees.map((fee, index) => (
                <TableRow key={fee.id}>
                  <TableCell className='text-muted-foreground'>{index + 1}</TableCell>
                  <TableCell className='max-w-72'>
                    <button type='button' className='block max-w-full truncate text-start font-medium hover:underline' title='Ubah biaya layanan' onClick={() => setForm({ fee })}>
                      {fee.name}
                    </button>
                  </TableCell>
                  <TableCell className='whitespace-nowrap font-medium tabular-nums'>{formatRupiah(fee.amount)}</TableCell>
                  <TableCell>
                    <div className='flex items-center gap-2'>
                      <Switch
                        checked={fee.is_active}
                        disabled={toggling === fee.id}
                        aria-label={`${fee.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${fee.name}`}
                        onCheckedChange={() => {
                          setToggling(fee.id)
                          router.patch(`/admin/biaya-layanan/${fee.id}/aktif`, {}, {
                            preserveScroll: true,
                            preserveState: true,
                            onFinish: () => setToggling(null),
                          })
                        }}
                      />
                      {fee.is_active && <Badge variant='secondary' className='bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'>Berlaku</Badge>}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className='flex items-center justify-end gap-1.5'>
                      <Button type='button' variant='outline' size='icon' className='size-8' aria-label={`Ubah biaya layanan ${fee.name}`} title='Ubah' onClick={() => setForm({ fee })}>
                        <Pencil />
                      </Button>
                      <Button
                        type='button'
                        variant='outline'
                        size='icon'
                        className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                        aria-label={`Hapus biaya layanan ${fee.name}`}
                        title='Hapus'
                        onClick={() => setDeleting(fee)}
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

      <ServiceFeeFormDialog open={form !== null} onOpenChange={(open) => !open && setForm(null)} fee={form?.fee} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus biaya layanan?'
        desc={`Biaya "${deleting?.name ?? ''}" dihapus${deleting?.is_active ? ' dan pesanan baru tidak lagi dikenai biaya layanan' : ''}. Pesanan yang sudah ada tidak terpengaruh.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => {
          if (!deleting) return
          router.delete(`/admin/biaya-layanan/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })
        }}
      />
    </>
  )
}

function StatCard({ label, value, note }: { label: string; value: string; note: string }) {
  return (
    <div className='rounded-lg border bg-card px-4 py-3'>
      <p className='text-xs text-muted-foreground'>{label}</p>
      <p className='mt-1 text-xl font-semibold tabular-nums'>{value}</p>
      <p className='mt-0.5 truncate text-xs text-muted-foreground'>{note}</p>
    </div>
  )
}
