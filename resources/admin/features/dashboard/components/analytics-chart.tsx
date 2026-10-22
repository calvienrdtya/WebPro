import { Area, AreaChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { type DailyPoint } from '../types'

/** Pesanan dibuat dan pesanan lunas per hari (30 hari terakhir). */
export function AnalyticsChart({ data }: { data: DailyPoint[] }) {
  return (
    <ResponsiveContainer width='100%' height={300}>
      <AreaChart data={data} margin={{ left: 0, right: 8 }}>
        <CartesianGrid vertical={false} strokeDasharray='3 3' className='stroke-muted' />
        <XAxis dataKey='label' stroke='#888888' fontSize={12} tickLine={false} axisLine={false} interval='preserveStartEnd' minTickGap={24} />
        <YAxis stroke='#888888' fontSize={12} tickLine={false} axisLine={false} allowDecimals={false} width={32} />
        <Tooltip
          formatter={(value, name) => [String(value), name === 'created' ? 'Pesanan dibuat' : 'Pesanan lunas']}
          contentStyle={{ borderRadius: 8, fontSize: 12 }}
        />
        <Legend formatter={(value) => (value === 'created' ? 'Pesanan dibuat' : 'Pesanan lunas')} />
        <Area type='monotone' dataKey='created' stroke='currentColor' className='text-muted-foreground' fill='currentColor' fillOpacity={0.1} />
        <Area type='monotone' dataKey='paid' stroke='currentColor' className='text-primary' fill='currentColor' fillOpacity={0.18} />
      </AreaChart>
    </ResponsiveContainer>
  )
}
