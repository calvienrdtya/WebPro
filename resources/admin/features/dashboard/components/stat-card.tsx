import { type ElementType } from 'react'
import { Link } from '@inertiajs/react'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'

type StatCardProps = {
  title: string
  value: string
  description?: string
  icon: ElementType
  /** Menjadikan kartu tautan ke halaman terkait. */
  href?: string
  /** Menyorot kartu (mis. ada yang perlu ditindak). */
  highlight?: boolean
}

export function StatCard({ title, value, description, icon: Icon, href, highlight = false }: StatCardProps) {
  const card = (
    <Card className={`h-full ${highlight ? 'border-amber-500/50 bg-amber-500/5' : ''} ${href ? 'transition hover:border-primary/50' : ''}`}>
      <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
        <CardTitle className='text-sm font-medium'>{title}</CardTitle>
        <Icon className='h-4 w-4 text-muted-foreground' aria-hidden='true' />
      </CardHeader>
      <CardContent>
        <div className={`text-2xl font-bold tabular-nums ${highlight ? 'text-amber-600' : ''}`}>{value}</div>
        {description && <p className='mt-1 text-xs text-muted-foreground'>{description}</p>}
      </CardContent>
    </Card>
  )

  return href ? <Link href={href} className='block rounded-xl focus-visible:outline-2 focus-visible:outline-ring'>{card}</Link> : card
}
