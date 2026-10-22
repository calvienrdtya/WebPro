import { useState } from 'react'
import { Check, ChevronsUpDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { cn } from '@/lib/utils'

type Props = {
  id: string
  value: string
  onChange: (value: string) => void
  models: string[]
  /** Dipanggil saat daftar dibuka tetapi model belum dimuat. */
  onOpenEmpty: () => void
  loading: boolean
  /** Bila diisi, ada pilihan "kosong" dengan label ini (mis. "Sama dengan model chat"). */
  emptyLabel?: string
  placeholder: string
}

/** Pemilih model dengan pencarian (router bisa punya ratusan model). */
export function ModelPicker({ id, value, onChange, models, onOpenEmpty, loading, emptyLabel, placeholder }: Props) {
  const [open, setOpen] = useState(false)

  const pick = (model: string) => {
    onChange(model)
    setOpen(false)
  }

  return (
    <>
      <Button
        id={id}
        type='button'
        variant='outline'
        className='w-full justify-between font-normal'
        onClick={() => {
          if (models.length === 0) onOpenEmpty()
          setOpen(true)
        }}
      >
        <span className={cn('truncate', !value && 'text-muted-foreground')}>{value || (emptyLabel ?? placeholder)}</span>
        <ChevronsUpDown className='size-4 opacity-50' />
      </Button>

      <CommandDialog open={open} onOpenChange={setOpen} title='Pilih model' description='Cari model dari router'>
        <CommandInput placeholder='Cari model...' />
        <CommandList className='max-h-[60vh]'>
          {loading && <p className='px-3 py-2 text-sm text-muted-foreground'>Memuat daftar model...</p>}
          {!loading && <CommandEmpty>{models.length ? 'Model tidak ditemukan.' : 'Daftar model belum dimuat. Periksa base URL dan API key.'}</CommandEmpty>}
          {emptyLabel && (
            <CommandGroup>
              <CommandItem value='__kosong__' onSelect={() => pick('')}>
                <Check className={cn('size-4', value ? 'opacity-0' : 'opacity-100')} />
                {emptyLabel}
              </CommandItem>
            </CommandGroup>
          )}
          {models.length > 0 && (
            <CommandGroup heading={`${models.length} model`}>
              {models.map((m) => (
                <CommandItem key={m} value={m} onSelect={() => pick(m)}>
                  <Check className={cn('size-4', value === m ? 'opacity-100' : 'opacity-0')} />
                  <span className='truncate text-xs'>{m}</span>
                </CommandItem>
              ))}
            </CommandGroup>
          )}
        </CommandList>
      </CommandDialog>
    </>
  )
}
