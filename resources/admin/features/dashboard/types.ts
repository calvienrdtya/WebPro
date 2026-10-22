export type Finance = {
  orders: number
  revenue: number
  hpp: number
  hpp_estimate: number
  profit: number
  fee: number
  warranty: number
  total_profit: number
}

export type MonthlyPoint = { month: string; label: string; revenue: number; profit: number }
export type DailyPoint = { date: string; label: string; created: number; paid: number }
export type TopProduct = { name: string; orders: number; revenue: number }
export type StatusCount = { status: string; label: string; total: number }

export const PERIOD_OPTIONS = [
  { value: 'bulan-ini', label: 'Bulan ini' },
  { value: 'bulan-lalu', label: 'Bulan lalu' },
  { value: '7-hari', label: '7 hari terakhir' },
  { value: '30-hari', label: '30 hari terakhir' },
  { value: 'tahun-ini', label: 'Tahun ini' },
  { value: 'semua', label: 'Semua waktu' },
] as const

/** Perubahan dibanding periode sebelumnya, mis. "+12,4% dari periode sebelumnya"; undefined bila tidak bisa dibandingkan. */
export function trend(current: number, previous: number | undefined): string | undefined {
  if (previous === undefined) return undefined
  if (previous === 0) return current > 0 ? 'Naik dari 0 di periode sebelumnya' : undefined
  const pct = ((current - previous) / Math.abs(previous)) * 100
  const sign = pct > 0 ? '+' : ''
  return `${sign}${pct.toFixed(1).replace('.', ',')}% dari periode sebelumnya`
}
