import { type FormEvent, useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import { DatabaseBackup, Download, Loader2, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { FieldError } from '@/features/products/field-error'
import { formatBytes } from '@/features/logs/system-log'

type Backup = { name: string; type: 'otomatis' | 'manual'; size: number; created_at: string }

type Props = {
  backups: Backup[]
  settings: { enabled: boolean; interval_days: number; keep: number; max_interval_days: number; max_keep: number; time: string }
}

const URL = '/admin/backup'

const formatTime = (iso: string) =>
  new Date(iso).toLocaleString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

export default function Index({ backups, settings }: Props) {
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<Backup | null>(null)
  const form = useForm({ enabled: settings.enabled, interval_days: String(settings.interval_days), keep: String(settings.keep) })

  const create = () => {
    setCreating(true)
    router.post(URL, {}, { preserveScroll: true, onFinish: () => setCreating(false) })
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.put(`${URL}/pengaturan`, { preserveScroll: true })
  }

  const last = backups[0]

  return (
    <>
      <Head title='Backup' />
      <AdminHeader />
      <Main>
        <div className='mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between'>
          <div className='space-y-1'>
            <h1 className='text-2xl font-bold tracking-tight'>Backup</h1>
            <p className='max-w-2xl text-sm text-muted-foreground'>
              Salinan seluruh database toko (user, pesanan, stok akun, voucher, laporan, pengaturan) dalam satu file .sql.gz.
              {last ? ` Backup terakhir: ${formatTime(last.created_at)}.` : ' Belum ada backup.'}
            </p>
          </div>
          <Button onClick={create} disabled={creating} className='shrink-0'>
            {creating ? <Loader2 className='animate-spin' /> : <DatabaseBackup />}
            {creating ? 'Membuat backup...' : 'Buat backup sekarang'}
          </Button>
        </div>

        <div className='grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]'>
          <div className='space-y-3'>
            <div className='rounded-lg border'>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Dibuat</TableHead>
                    <TableHead>Jenis</TableHead>
                    <TableHead className='hidden sm:table-cell'>File</TableHead>
                    <TableHead className='text-end'>Ukuran</TableHead>
                    <TableHead className='w-24'><span className='sr-only'>Aksi</span></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {backups.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5} className='h-28 text-center text-muted-foreground'>
                        Belum ada backup. Tekan "Buat backup sekarang" untuk membuat yang pertama.
                      </TableCell>
                    </TableRow>
                  )}
                  {backups.map((b) => (
                    <TableRow key={b.name}>
                      <TableCell className='whitespace-nowrap'>{formatTime(b.created_at)}</TableCell>
                      <TableCell>
                        <Badge variant='outline' className='font-normal'>{b.type === 'otomatis' ? 'Otomatis' : 'Manual'}</Badge>
                      </TableCell>
                      <TableCell className='hidden max-w-72 truncate text-sm text-muted-foreground sm:table-cell'>{b.name}</TableCell>
                      <TableCell className='text-end whitespace-nowrap tabular-nums'>{formatBytes(b.size)}</TableCell>
                      <TableCell>
                        <div className='flex justify-end gap-1.5'>
                          <Button variant='outline' size='icon' className='size-8' asChild>
                            <a href={`${URL}/${b.name}/unduh`} aria-label={`Unduh ${b.name}`} title='Unduh'>
                              <Download />
                            </a>
                          </Button>
                          <Button
                            type='button'
                            variant='outline'
                            size='icon'
                            className='size-8 text-destructive hover:bg-destructive/10 hover:text-destructive'
                            aria-label={`Hapus ${b.name}`}
                            title='Hapus'
                            onClick={() => setDeleting(b)}
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

            <div className='space-y-2 rounded-lg border bg-muted/40 p-4 text-sm'>
              <p className='font-medium'>Penting</p>
              <ul className='list-disc space-y-1.5 ps-5 text-muted-foreground'>
                <li>File backup tersimpan di server yang sama. <span className='text-foreground'>Unduh dan simpan salinannya di tempat lain</span> (laptop atau Google Drive) secara berkala, agar tetap aman bila server rusak.</li>
                <li>Sandi stok akun di dalam backup terenkripsi dengan APP_KEY. Simpan nilai APP_KEY dari file .env di tempat aman; tanpa itu sandi akun tidak bisa dibaca setelah dipulihkan.</li>
                <li>Gambar unggahan (produk, logo, QRIS), bukti transfer, dan lampiran tidak termasuk. Cadangkan folder public/uploads dan storage/app/private lewat panel hosting.</li>
                <li>Cara memulihkan: buat database kosong, lalu impor file .sql.gz lewat phpMyAdmin (menu Import). Isi database tujuan akan diganti.</li>
              </ul>
            </div>
          </div>

          <form onSubmit={submit} noValidate className='h-fit space-y-4 rounded-lg border p-4'>
            <div>
              <h2 className='font-semibold'>Backup otomatis</h2>
              <p className='text-sm text-muted-foreground'>Diperiksa setiap hari pukul {settings.time.replace(':', '.')} oleh cron. Backup lama melebihi jumlah simpan dihapus otomatis.</p>
            </div>

            <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
              <div>
                <Label htmlFor='backup_enabled'>Backup otomatis</Label>
                <p className='text-xs text-muted-foreground'>{form.data.enabled ? 'Menyala.' : 'Dimatikan, hanya backup manual.'}</p>
              </div>
              <Switch id='backup_enabled' checked={form.data.enabled} onCheckedChange={(v) => form.setData('enabled', v)} />
            </div>

            <div className='space-y-2'>
              <Label htmlFor='backup_interval'>Buat backup setiap (hari)</Label>
              <Input
                id='backup_interval'
                type='number'
                min={1}
                max={settings.max_interval_days}
                inputMode='numeric'
                disabled={!form.data.enabled}
                value={form.data.interval_days}
                onChange={(e) => {
                  form.clearErrors('interval_days')
                  form.setData('interval_days', e.target.value)
                }}
                className='max-w-32'
              />
              <p className='text-xs text-muted-foreground'>1 = setiap hari. Antara 1 dan {settings.max_interval_days} hari.</p>
              <FieldError message={form.errors.interval_days} />
            </div>

            <div className='space-y-2'>
              <Label htmlFor='backup_keep'>Jumlah backup yang disimpan</Label>
              <Input
                id='backup_keep'
                type='number'
                min={1}
                max={settings.max_keep}
                inputMode='numeric'
                value={form.data.keep}
                onChange={(e) => {
                  form.clearErrors('keep')
                  form.setData('keep', e.target.value)
                }}
                className='max-w-32'
              />
              <p className='text-xs text-muted-foreground'>Termasuk backup manual. Antara 1 dan {settings.max_keep}.</p>
              <FieldError message={form.errors.keep} />
            </div>

            <Button type='submit' disabled={form.processing}>
              {form.processing ? 'Menyimpan...' : 'Simpan'}
            </Button>
          </form>
        </div>
      </Main>

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title='Hapus backup?'
        desc={`File ${deleting?.name ?? ''} dihapus permanen dari server. Tindakan ini tidak bisa dibatalkan.`}
        confirmText='Hapus'
        cancelBtnText='Batal'
        destructive
        handleConfirm={() => deleting && router.delete(`${URL}/${deleting.name}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
      />
    </>
  )
}
