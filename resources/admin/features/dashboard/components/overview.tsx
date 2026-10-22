import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { formatRupiah } from '@/lib/format'
import { type MonthlyPoint } from '../types'

/** Revenue dan total laba per bulan (12 bulan terakhir). */
export function Overview({ data }: { data: MonthlyPoint[] }) {
  return (
    <ResponsiveContainer width='100%' height={350}>
      <BarChart data={data} margin={{ left: 4, right: 4 }}>
        <CartesianGrid vertical={false} strokeDasharray='3 3' className='stroke-muted' />
        <XAxis dataKey='label' stroke='#888888' fontSize={12} tickLine={false} axisLine={false} />
        <YAxis
          direction='ltr'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
          width={48}
          tickFormatter={(v: number) => (Math.abs(v) >= 1_000_000 ? `${(v / 1_000_000).toFixed(1).replace('.0', '')}jt` : `${Math.round(v / 1000)}rb`)}
        />
        <Tooltip
          cursor={{ fill: 'currentColor', opacity: 0.06 }}
          formatter={(value, name) => [formatRupiah(Number(value)), name === 'revenue' ? 'Revenue' : 'Total laba']}
          contentStyle={{ borderRadius: 8, fontSize: 12 }}
        />
        <Legend formatter={(value) => (value === 'revenue' ? 'Revenue' : 'Total laba')} />
        <Bar dataKey='revenue' fill='currentColor' radius={[4, 4, 0, 0]} className='fill-primary' />
        <Bar dataKey='profit' fill='#10B981' radius={[4, 4, 0, 0]} />
      </BarChart>
    </ResponsiveContainer>
  )
}
