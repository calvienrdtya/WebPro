import { getDisplayNameInitials } from '@/lib/utils'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'

export type LatestUser = {
  id: number
  name: string
  email: string
  avatar: string | null
  joined: string
}

export function LatestUsers({ users }: { users: LatestUser[] }) {
  if (users.length === 0) {
    return (
      <p className='text-sm text-muted-foreground'>Belum ada user terdaftar.</p>
    )
  }

  return (
    <div className='space-y-8'>
      {users.map((user) => (
        <div key={user.id} className='flex items-center gap-4'>
          <Avatar className='h-9 w-9'>
            <AvatarImage src={user.avatar ?? undefined} alt={user.name} referrerPolicy='no-referrer' />
            <AvatarFallback>{getDisplayNameInitials(user.name)}</AvatarFallback>
          </Avatar>
          <div className='flex min-w-0 flex-1 flex-wrap items-center justify-between gap-x-2'>
            <div className='min-w-0 space-y-1'>
              <p className='truncate text-sm leading-none font-medium'>{user.name}</p>
              <p className='truncate text-sm text-muted-foreground'>{user.email}</p>
            </div>
            <div className='text-xs text-muted-foreground'>{user.joined}</div>
          </div>
        </div>
      ))}
    </div>
  )
}
