const pad = (n: number) => String(n).padStart(2, '0')

const toIso = (d: Date) =>
  `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

const fromIso = (iso: string) => new Date(`${iso}T12:00:00`)

/** Tanggal hari ini menurut zona waktu browser, format YYYY-MM-DD. */
export const localToday = () => toIso(new Date())

export const addDays = (iso: string, days: number) => {
  const d = fromIso(iso)
  d.setDate(d.getDate() + days)
  return toIso(d)
}

/** Selisih hari (b - a) antar tanggal YYYY-MM-DD. */
export const diffDays = (a: string, b: string) =>
  Math.round((fromIso(b).getTime() - fromIso(a).getTime()) / 86_400_000)

/** Tanggal ringkas tanpa tahun, mis. "3 Okt". */
export const formatShortDate = (iso: string) =>
  fromIso(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })

export const formatDate = (iso: string) =>
  fromIso(iso).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
