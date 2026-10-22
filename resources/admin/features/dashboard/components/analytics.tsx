import { formatNumber, formatRupiah } from '@/lib/format'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { type DailyPoint, type StatusCount, type TopProduct } from '../types'
import { AnalyticsChart } from './analytics-chart'

type Props = {
  daily: DailyPoint[]
  topProducts: TopProduct[]
  byStatus: StatusCount[]
  periodLabel: string
}

export function Analytics({ daily, topProducts, byStatus, periodLabel }: Props) {
  return (
    <div className='space-y-4'>
      <Card>
        <CardHeader>
          <CardTitle>Pesanan Harian</CardTitle>
          <CardDescription>Pesanan dibuat dan pesanan yang sudah lunas, 30 hari terakhir</CardDescription>
        </CardHeader>
        <CardContent className='px-6'>
          <AnalyticsChart data={daily} />
        </CardContent>
      </Card>
      <div className='grid grid-cols-1 gap-4 lg:grid-cols-7'>
        <Card className='col-span-1 lg:col-span-4'>
          <CardHeader>
            <CardTitle>Produk Terlaris</CardTitle>
            <CardDescription>Pesanan lunas per produk, {periodLabel.toLowerCase()}</CardDescription>
          </CardHeader>
          <CardContent>
            {topProducts.length === 0 ? (
              <p className='py-6 text-center text-sm text-muted-foreground'>Belum ada pesanan lunas pada periode ini.</p>
            ) : (
              <SimpleBarList
                items={topProducts.map((p) => ({ name: p.name, value: p.orders, note: formatRupiah(p.revenue) }))}
                barClass='bg-primary'
                valueFormatter={(n) => `${formatNumber(n)} pesanan`}
              />
            )}
          </CardContent>
        </Card>
        <Card className='col-span-1 lg:col-span-3'>
          <CardHeader>
            <CardTitle>Status Pesanan</CardTitle>
            <CardDescription>Semua pesanan sejak toko dibuka</CardDescription>
          </CardHeader>
          <CardContent>
            <SimpleBarList
              items={byStatus.map((s) => ({ name: s.label, value: s.total }))}
              barClass='bg-muted-foreground'
              valueFormatter={(n) => formatNumber(n)}
            />
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

function SimpleBarList({
  items,
  valueFormatter,
  barClass,
}: {
  items: { name: string; value: number; note?: string }[]
  valueFormatter: (n: number) => string
  barClass: string
}) {
  const max = Math.max(...items.map((i) => i.value), 1)
  return (
    <ul className='space-y-3'>
      {items.map((i) => {
        const width = `${Math.round((i.value / max) * 100)}%`
        return (
          <li key={i.name} className='flex items-center justify-between gap-3'>
            <div className='min-w-0 flex-1'>
              <div className='mb-1 flex items-center justify-between gap-2 text-xs text-muted-foreground'>
                <span className='truncate'>{i.name}</span>
                {i.note && <span className='shrink-0 tabular-nums'>{i.note}</span>}
              </div>
              <div className='h-2.5 w-full rounded-full bg-muted'>
                <div className={`h-2.5 rounded-full ${barClass}`} style={{ width }} />
              </div>
            </div>
            <div className='ps-2 text-xs font-medium tabular-nums'>{valueFormatter(i.value)}</div>
          </li>
        )
      })}
    </ul>
  )
}
