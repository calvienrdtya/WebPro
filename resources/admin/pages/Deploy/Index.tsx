import { Head, router } from '@inertiajs/react'
import { AlertTriangle, CheckCircle2, RefreshCw, XCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { cn } from '@/lib/utils'
import { MARKDOWN_CLASS } from '@/features/ai/markdown'

type Check = { group: string; label: string; status: 'ok' | 'warn' | 'fail'; detail: string }
type Props = { checks: Check[]; guide: string; checkedAt: string }

const STYLE = {
  ok: { icon: CheckCircle2, color: 'text-emerald-600 dark:text-emerald-500' },
  warn: { icon: AlertTriangle, color: 'text-amber-600 dark:text-amber-500' },
  fail: { icon: XCircle, color: 'text-destructive' },
}

export default function DeployGuide({ checks, guide, checkedAt }: Props) {
  const groups = [...new Set(checks.map((c) => c.group))]
  const count = (s: Check['status']) => checks.filter((c) => c.status === s).length

  return (
    <>
      <Head title='Panduan Deploy' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Panduan Deploy</h1>
          <p className='text-sm text-muted-foreground'>
            Cek kesiapan membaca kondisi server tempat aplikasi ini berjalan. Di komputer lokal wajar banyak yang merah; yang penting semuanya hijau di server production.
          </p>
        </div>

        <section className='mb-6 max-w-4xl rounded-lg border p-4'>
          <div className='mb-3 flex flex-wrap items-center justify-between gap-3'>
            <div>
              <h2 className='font-semibold'>Cek kesiapan</h2>
              <p className='text-sm text-muted-foreground'>
                <span className='text-emerald-600 dark:text-emerald-500'>{count('ok')} siap</span>,{' '}
                <span className='text-amber-600 dark:text-amber-500'>{count('warn')} sebaiknya dibereskan</span>,{' '}
                <span className='text-destructive'>{count('fail')} wajib dibereskan</span>. Dicek {new Date(checkedAt).toLocaleTimeString('id-ID')}.
              </p>
            </div>
            <Button type='button' variant='outline' size='sm' onClick={() => router.reload({ only: ['checks', 'checkedAt'] })}>
              <RefreshCw className='size-4' /> Cek ulang
            </Button>
          </div>
          <div className='space-y-4'>
            {groups.map((g) => (
              <div key={g}>
                <p className='mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase'>{g}</p>
                <ul className='divide-y rounded-md border'>
                  {checks.filter((c) => c.group === g).map((c) => {
                    const { icon: Icon, color } = STYLE[c.status]
                    return (
                      <li key={c.label} className='flex gap-3 px-3 py-2 text-sm'>
                        <Icon className={cn('mt-0.5 size-4 shrink-0', color)} />
                        <div className='min-w-0'>
                          <p className='font-medium'>{c.label}</p>
                          <p className='break-words text-muted-foreground'>{c.detail}</p>
                        </div>
                      </li>
                    )
                  })}
                </ul>
              </div>
            ))}
          </div>
        </section>

        <article className={cn('max-w-4xl rounded-lg border p-4 sm:p-6', MARKDOWN_CLASS, '[&_pre]:my-2 [&_pre]:overflow-x-auto [&_pre]:rounded-md [&_pre]:bg-muted [&_pre]:p-3 [&_pre_code]:bg-transparent [&_pre_code]:p-0')} dangerouslySetInnerHTML={{ __html: guide }} />
      </Main>
    </>
  )
}
