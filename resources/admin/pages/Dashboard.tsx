import { Head, router } from '@inertiajs/react'
import { BadgeDollarSign, Clock, Coins, PackageCheck, Receipt, ShieldCheck, TrendingUp, Truck, Users } from 'lucide-react'
import { formatNumber, formatRupiah } from '@/lib/format'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { Analytics } from '@/features/dashboard/components/analytics'
import {
  LatestUsers,
  type LatestUser,
} from '@/features/dashboard/components/latest-users'
import { Overview } from '@/features/dashboard/components/overview'
import { StatCard } from '@/features/dashboard/components/stat-card'
import {
  PERIOD_OPTIONS,
  trend,
  type DailyPoint,
  type Finance,
  type MonthlyPoint,
  type StatusCount,
  type TopProduct,
} from '@/features/dashboard/types'
import { UserPlus } from 'lucide-react'

type DashboardProps = {
  period: string
  finance: Finance
  previous: Finance | null
  monthly: MonthlyPoint[]
  daily: DailyPoint[]
  top_products: TopProduct[]
  by_status: StatusCount[]
  queue: { verifying: number; unpaid: number; overdue: number }
  users: {
    total: number
    this_month: number
    latest: LatestUser[]
  }
}

export default function Dashboard({ period, finance, previous, monthly, daily, top_products, by_status, queue, users }: DashboardProps) {
  const periodLabel = PERIOD_OPTIONS.find((p) => p.value === period)?.label ?? 'Bulan ini'
  const prev = previous ?? undefined

  const hppNote =
    finance.hpp_estimate > 0
      ? `HPP ${formatRupiah(finance.hpp)} (termasuk estimasi ${formatRupiah(finance.hpp_estimate)})`
      : `HPP ${formatRupiah(finance.hpp)}`

  return (
    <>
      <Head title='Dashboard' />

      <AdminHeader />

      <Main>
        <div className='mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between'>
          <div className='flex flex-col gap-1'>
            <h1 className='text-2xl font-bold tracking-tight'>Dashboard</h1>
            <p className='text-sm text-muted-foreground'>
              Ringkasan toko ProAkses. Pesanan dihitung pada tanggal dibayar, hanya yang sudah lunas.
            </p>
          </div>
          <Select
            value={period}
            onValueChange={(value) => router.get('/dashboard', value === 'bulan-ini' ? {} : { periode: value }, { preserveState: true, preserveScroll: true, replace: true })}
          >
            <SelectTrigger className='w-full sm:w-48' aria-label='Periode'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {PERIOD_OPTIONS.map((p) => (
                <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <Tabs defaultValue='overview' className='space-y-4'>
          <div className='w-full overflow-x-auto pb-2'>
            <TabsList>
              <TabsTrigger value='overview'>Ringkasan</TabsTrigger>
              <TabsTrigger value='analytics'>Analitik</TabsTrigger>
            </TabsList>
          </div>
          <TabsContent value='overview' className='space-y-4'>
            <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-5'>
              <StatCard
                title='Revenue'
                value={formatRupiah(finance.revenue)}
                description={trend(finance.revenue, prev?.revenue) ?? 'Total yang dibayar pelanggan'}
                icon={BadgeDollarSign}
              />
              <StatCard
                title='Profit'
                value={formatRupiah(finance.profit)}
                description='Margin produk setelah voucher dan HPP'
                icon={TrendingUp}
              />
              <StatCard
                title='Biaya Admin'
                value={formatRupiah(finance.fee)}
                description='Biaya layanan dari pesanan'
                icon={Receipt}
              />
              <StatCard
                title='Garansi'
                value={formatRupiah(finance.warranty)}
                description='Pemasukan paket garansi'
                icon={ShieldCheck}
              />
              <StatCard
                title='Total Laba'
                value={formatRupiah(finance.total_profit)}
                description={`${trend(finance.total_profit, prev?.total_profit) ?? 'Revenue dikurangi HPP'} | ${hppNote}`}
                icon={Coins}
              />
            </div>
            <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>
              <StatCard
                title='Pesanan Lunas'
                value={formatNumber(finance.orders)}
                description={trend(finance.orders, prev?.orders) ?? periodLabel}
                icon={PackageCheck}
              />
              <StatCard
                title='Menunggu Verifikasi'
                value={formatNumber(queue.verifying)}
                description={`${formatNumber(queue.unpaid)} menunggu pembayaran`}
                icon={Clock}
                href='/admin/pesanan?status=queue'
                highlight={queue.verifying > 0}
              />
              <StatCard
                title='Kiriman Tertunda'
                value={formatNumber(queue.overdue)}
                description='Kiriman bulanan yang belum terkirim'
                icon={Truck}
                href='/admin/pesanan?status=overdue'
                highlight={queue.overdue > 0}
              />
              <StatCard
                title='Total User'
                value={formatNumber(users.total)}
                description={`${formatNumber(users.this_month)} baru bulan ini`}
                icon={Users}
                href='/admin/user'
              />
            </div>
            <div className='grid grid-cols-1 gap-4 lg:grid-cols-7'>
              <Card className='col-span-1 lg:col-span-4'>
                <CardHeader>
                  <CardTitle>Revenue dan Total Laba per Bulan</CardTitle>
                  <CardDescription>12 bulan terakhir. Laba memakai estimasi HPP untuk kiriman yang belum terkirim.</CardDescription>
                </CardHeader>
                <CardContent className='ps-2'>
                  <Overview data={monthly} />
                </CardContent>
              </Card>
              <Card className='col-span-1 lg:col-span-3'>
                <CardHeader>
                  <div className='flex items-center justify-between gap-2'>
                    <CardTitle>User Terbaru</CardTitle>
                    <UserPlus className='h-4 w-4 text-muted-foreground' aria-hidden='true' />
                  </div>
                  <CardDescription>
                    5 user yang paling baru mendaftar.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <LatestUsers users={users.latest} />
                </CardContent>
              </Card>
            </div>
          </TabsContent>
          <TabsContent value='analytics' className='space-y-4'>
            <Analytics daily={daily} topProducts={top_products} byStatus={by_status} periodLabel={periodLabel} />
          </TabsContent>
        </Tabs>
      </Main>
    </>
  )
}
