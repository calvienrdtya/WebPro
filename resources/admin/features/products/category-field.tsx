import { useState } from 'react'
import { router } from '@inertiajs/react'
import { Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { FieldError } from './field-error'
import { type Option } from './types'

const NEW = '__new__'

// Sama dengan Str::slug di server, supaya pratinjau URL cocok dengan hasil simpan.
const slugify = (text: string) =>
  text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'kategori'

const clean = (text: string) => text.trim().replace(/\s+/g, ' ')

type Props = {
  categories: Option[]
  category: string
  newCategory: string
  onChange: (category: string, newCategory: string) => void
  error?: string
}

export function CategoryField({ categories, category, newCategory, onChange, error }: Props) {
  const [creating, setCreating] = useState(false)
  const [renameOpen, setRenameOpen] = useState(false)
  const [renameValue, setRenameValue] = useState('')
  const [renameError, setRenameError] = useState<string>()
  const [saving, setSaving] = useState(false)

  const selected = categories.find((o) => o.value === category)
  const typed = clean(newCategory)
  const existing = typed
    ? categories.find((o) => o.label.toLowerCase() === typed.toLowerCase() || o.value === slugify(typed))
    : undefined

  const openRename = () => {
    setRenameValue(selected?.label ?? '')
    setRenameError(undefined)
    setRenameOpen(true)
  }

  const saveRename = () => {
    if (!selected) return
    router.patch(
      `/admin/kategori-produk/${selected.value}`,
      { name: renameValue },
      {
        preserveScroll: true,
        preserveState: true,
        onStart: () => setSaving(true),
        onFinish: () => setSaving(false),
        onError: (errs) => setRenameError(errs.name),
        onSuccess: () => setRenameOpen(false),
      }
    )
  }

  return (
    <div className='space-y-2'>
      <Label htmlFor='category'>Kategori</Label>
      <div className='flex items-center gap-2'>
        <Select
          value={creating ? NEW : category}
          onValueChange={(v) => {
            setCreating(v === NEW)
            onChange(v === NEW ? '' : v, '')
          }}
        >
          <SelectTrigger id='category' className='w-full'>
            <SelectValue placeholder='Pilih kategori' />
          </SelectTrigger>
          <SelectContent>
            {categories.map((o) => (
              <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
            ))}
            <SelectSeparator />
            <SelectItem value={NEW}>+ Kategori baru</SelectItem>
          </SelectContent>
        </Select>
        {selected && (
          <Button type='button' variant='outline' size='icon' className='shrink-0' onClick={openRename} aria-label={`Ubah nama kategori ${selected.label}`}>
            <Pencil className='size-4' />
          </Button>
        )}
      </div>

      {creating && (
        <div className='space-y-2 pt-1'>
          <Label htmlFor='new_category'>Nama kategori baru</Label>
          <Input
            id='new_category'
            autoFocus
            maxLength={60}
            value={newCategory}
            onChange={(e) => onChange('', e.target.value)}
            placeholder='Streaming Musik'
          />
          {typed && (
            <p className='rounded-md bg-muted px-3 py-2 text-sm'>
              {existing ? (
                <>Sudah ada: produk akan masuk ke kategori <strong>{existing.label}</strong>.</>
              ) : (
                <>
                  Akan dibuat: <strong>{typed}</strong>
                  <br />
                  <span className='text-muted-foreground'>URL toko: /produk?kategori={slugify(typed)}</span>
                </>
              )}
            </p>
          )}
        </div>
      )}
      <FieldError message={error} />

      <Dialog open={renameOpen} onOpenChange={setRenameOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Ubah nama kategori</DialogTitle>
            <DialogDescription>
              Hanya nama tampilan yang berubah. URL toko (/produk?kategori={selected?.value}) tetap sama.
            </DialogDescription>
          </DialogHeader>
          <div className='space-y-2'>
            <Label htmlFor='rename_category'>Nama kategori</Label>
            <Input
              id='rename_category'
              maxLength={60}
              value={renameValue}
              onChange={(e) => setRenameValue(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault()
                  saveRename()
                }
              }}
            />
            <FieldError message={renameError} />
          </div>
          <DialogFooter>
            <Button type='button' variant='outline' onClick={() => setRenameOpen(false)}>Batal</Button>
            <Button type='button' onClick={saveRename} disabled={saving || !clean(renameValue)}>Simpan</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
