import * as React from 'react'
import { cn } from '@/lib/utils'

const DATE_TYPES = ['date', 'time', 'datetime-local', 'month', 'week']

function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
  return (
    <input
      type={type}
      data-slot='input'
      className={cn(
        'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30',
        'focus-visible:border-foreground',
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
        // Input tanggal/waktu: `flex` membuat ikon bawaan browser tidak menempel di kanan, dan di tema gelap ikonnya tak terlihat.
        type && DATE_TYPES.includes(type) && 'block dark:[color-scheme:dark]',
        className
      )}
      {...props}
    />
  )
}

export { Input }
