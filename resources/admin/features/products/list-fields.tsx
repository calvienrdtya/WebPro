import { Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { FieldError } from './field-error'
import { type SpecForm } from './types'

type Errors = Record<string, string | undefined>

export function HighlightsField({
  value,
  onChange,
  errors,
  name = 'highlights',
  itemLabel = 'Poin unggulan',
  placeholder = 'Contoh: Kualitas 4K Ultra HD',
  addLabel = 'Tambah poin',
}: {
  value: string[]
  onChange: (next: string[]) => void
  errors: Errors
  /** Nama field di server, untuk membaca galat (mis. `descriptions.0`). */
  name?: string
  itemLabel?: string
  placeholder?: string
  addLabel?: string
}) {
  return (
    <div className='space-y-2'>
      {value.map((item, i) => (
        <div key={i}>
          <div className='flex gap-2'>
            <Input
              value={item}
              placeholder={placeholder}
              aria-label={`${itemLabel} ${i + 1}`}
              onChange={(e) =>
                onChange(value.map((v, idx) => (idx === i ? e.target.value : v)))
              }
            />
            <Button
              type='button'
              variant='ghost'
              size='icon'
              aria-label={`Hapus ${itemLabel.toLowerCase()} ${i + 1}`}
              onClick={() => onChange(value.filter((_, idx) => idx !== i))}
            >
              <Trash2 />
            </Button>
          </div>
          <FieldError message={errors[`${name}.${i}`]} />
        </div>
      ))}
      <FieldError message={errors[name]} />
      <Button
        type='button'
        variant='outline'
        size='sm'
        disabled={value.length >= 10}
        onClick={() => onChange([...value, ''])}
      >
        <Plus /> {addLabel}
      </Button>
    </div>
  )
}

export function SpecsField({
  value,
  onChange,
  errors,
}: {
  value: SpecForm[]
  onChange: (next: SpecForm[]) => void
  errors: Errors
}) {
  const set = (i: number, patch: Partial<SpecForm>) =>
    onChange(value.map((v, idx) => (idx === i ? { ...v, ...patch } : v)))

  return (
    <div className='space-y-2'>
      {value.map((spec, i) => (
        <div key={i}>
          <div className='flex gap-2'>
            <Input
              value={spec.label}
              placeholder='Nama (mis. Perangkat)'
              aria-label={`Nama spesifikasi ${i + 1}`}
              onChange={(e) => set(i, { label: e.target.value })}
            />
            <Input
              value={spec.value}
              placeholder='Isi (mis. Web, iOS, Android)'
              aria-label={`Isi spesifikasi ${i + 1}`}
              onChange={(e) => set(i, { value: e.target.value })}
            />
            <Button
              type='button'
              variant='ghost'
              size='icon'
              aria-label={`Hapus spesifikasi ${i + 1}`}
              onClick={() => onChange(value.filter((_, idx) => idx !== i))}
            >
              <Trash2 />
            </Button>
          </div>
          <FieldError message={errors[`specs.${i}.label`] ?? errors[`specs.${i}.value`]} />
        </div>
      ))}
      <FieldError message={errors.specs} />
      <Button
        type='button'
        variant='outline'
        size='sm'
        disabled={value.length >= 10}
        onClick={() => onChange([...value, { label: '', value: '' }])}
      >
        <Plus /> Tambah spesifikasi
      </Button>
    </div>
  )
}
