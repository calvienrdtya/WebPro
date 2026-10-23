import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { formatRupiah } from '@/lib/format'
import { type ChartPoint } from '../types'

type Series = { key: keyof Omit<ChartPoint, 'key' | 'label'>; label: string; stroke: string; className?: string }

/** Garis grafik: Revenue (warna utama tema), HPP akun terkirim (merah), dan total laba (hijau). */
const SERIES: Series[] = [
  { key: 'revenue', label: 'Revenue', stroke: 'currentColor', className: 'stroke-primary' },
  { key: 'hpp', label: 'HPP', stroke: '#EF4444' },
  { key: 'profit', label: 'Total laba', stroke: '#10B981' },
]

const LABELS: Record<string, string> = Object.fromEntries(SERIES.map((s) => [s.key, s.label]))
const labelOf = (name: unknown) => LABELS[String(name)] ?? String(name)

/** Titik data hanya digambar saat sedikit (bulanan, 7 hari); untuk 30 hari ke atas muncul saat di-hover. */
const MAX_POINTS_WITH_DOTS = 12

const axisTick = (v: number) =>
  Math.abs(v) >= 1_000_000 ? `${(v / 1_000_000).toFixed(1).replace('.0', '')}jt` : `${Math.round(v / 1000)}rb`

/** Revenue, HPP, dan total laba per hari atau per bulan, sesuai filter periode. */
export function Overview({ data }: { data: ChartPoint[] }) {
  const showDots = data.length <= MAX_POINTS_WITH_DOTS

  return (
    <ResponsiveContainer width='100%' height={350}>
      <LineChart data={data} margin={{ left: 4, right: 12, top: 8 }}>
        <CartesianGrid vertical={false} strokeDasharray='3 3' className='stroke-muted' />
        <XAxis dataKey='label' stroke='#888888' fontSize={12} tickLine={false} axisLine={false} minTickGap={12} />
        <YAxis direction='ltr' stroke='#888888' fontSize={12} tickLine={false} axisLine={false} width={48} tickFormatter={axisTick} />
        <Tooltip
          cursor={{ stroke: 'currentColor', strokeOpacity: 0.2 }}
          formatter={(value, name) => [formatRupiah(Number(value)), labelOf(name)]}
          contentStyle={{ borderRadius: 8, fontSize: 12 }}
        />
        <Legend formatter={labelOf} />
        {SERIES.map(({ key, stroke, className }) => (
          <Line
            key={key}
            type='monotone'
            dataKey={key}
            stroke={stroke}
            className={className}
            strokeWidth={2.5}
            dot={showDots ? { r: 3 } : false}
            activeDot={{ r: 5 }}
            isAnimationActive={false}
          />
        ))}
      </LineChart>
    </ResponsiveContainer>
  )
}
