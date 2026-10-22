import { type FormEvent, useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { CheckCircle2, Loader2, XCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { AdminHeader } from '@/components/layout/admin-header'
import { Main } from '@/components/layout/main'
import { postJson } from '@/features/ai/api'
import { AdvancedForm, type AdvancedKey, type AdvancedRange } from '@/features/ai/advanced-form'
import { ChatSimulator, type SimulationProps } from '@/features/ai/chat-simulator'
import { ModelPicker } from '@/features/ai/model-picker'
import { FieldError } from '@/features/products/field-error'

type Props = {
  connection: {
    enabled: boolean
    base_url: string
    masked_key: string | null
    chat_model: string
    vision_model: string
    reasoning_effort: string
    popup: boolean
  }
  advanced: Record<AdvancedKey, number> & { strip_prefixes: string; whatsapp_triggers: string }
  advancedRanges: Record<AdvancedKey, AdvancedRange>
  advancedDefaults: { strip_prefixes: string; whatsapp_triggers: string }
  simulation: SimulationProps
}

type TestType = 'connection' | 'chat' | 'vision'
type TestResult = { ok: boolean; message: string; reply?: string; ms?: number; usage?: { prompt: number | null; completion: number | null } }

// Radix Select tidak menerima nilai kosong, jadi "Bawaan" memakai penanda sendiri dan dikirim sebagai kosong.
const DEFAULT_LEVEL = 'bawaan'
const LEVELS = [
  { value: DEFAULT_LEVEL, label: 'Bawaan (tidak dikirim)' },
  { value: 'none', label: 'Mati (none)' },
  { value: 'minimal', label: 'Minimal (minimal)' },
  { value: 'low', label: 'Rendah (low)' },
  { value: 'medium', label: 'Sedang (medium)' },
]

const TESTS: { type: TestType; label: string; hint: string }[] = [
  { type: 'connection', label: 'Tes koneksi', hint: 'Router bisa dihubungi dan API key diterima.' },
  { type: 'chat', label: 'Tes chat', hint: 'Model chat memperkenalkan diri memakai nama dan instruksi dari menu Perilaku.' },
  { type: 'vision', label: 'Tes vision', hint: 'Model vision membaca angka acak di gambar buatan server.' },
]

export default function Connection({ connection, advanced, advancedRanges, advancedDefaults, simulation }: Props) {
  const form = useForm({
    enabled: connection.enabled,
    base_url: connection.base_url,
    api_key: '',
    remove_key: false,
    chat_model: connection.chat_model,
    vision_model: connection.vision_model,
    reasoning_effort: connection.reasoning_effort || DEFAULT_LEVEL,
  })
  const errors = form.errors as Record<string, string | undefined>

  const [models, setModels] = useState<string[]>([])
  const [modelsLoading, setModelsLoading] = useState(false)
  const [modelsError, setModelsError] = useState<string | null>(null)

  const loadModels = async () => {
    setModelsLoading(true)
    setModelsError(null)
    const res = await postJson<{ models?: string[] }>('/admin/ai/model', {
      base_url: form.data.base_url,
      api_key: form.data.remove_key ? '' : form.data.api_key,
    })
    setModelsLoading(false)
    if (res.failed || !res.models) {
      setModelsError(res.message ?? 'Gagal memuat daftar model.')
      return
    }
    setModels(res.models)
  }

  const [testing, setTesting] = useState<TestType | null>(null)
  const [results, setResults] = useState<Partial<Record<TestType, TestResult>>>({})

  const runTest = async (type: TestType) => {
    setTesting(type)
    const res = await postJson<TestResult>('/admin/ai/tes', { type })
    setTesting(null)
    setResults((r) => ({ ...r, [type]: res.failed ? { ok: false, message: res.message ?? 'Gagal.' } : res }))
  }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    form.transform((d) => ({ ...d, reasoning_effort: d.reasoning_effort === DEFAULT_LEVEL ? '' : d.reasoning_effort }))
    form.put('/admin/ai/koneksi', {
      preserveScroll: true,
      onSuccess: () => form.setData((d) => ({ ...d, api_key: '', remove_key: false })),
    })
  }

  const hasKey = !form.data.remove_key && (form.data.api_key !== '' || connection.masked_key !== null)

  return (
    <>
      <Head title='Koneksi AI' />
      <AdminHeader />
      <Main>
        <div className='mb-6 space-y-1'>
          <h1 className='text-2xl font-bold tracking-tight'>Koneksi AI</h1>
          <p className='text-sm text-muted-foreground'>
            Router AI yang kompatibel OpenAI untuk menjawab Tanya AI di toko. API key disimpan terenkripsi di server dan tidak pernah dikirim ke browser pelanggan.
          </p>
        </div>

        <div className='grid items-start gap-6 xl:grid-cols-2'>
        <div className='min-w-0'>
        <form onSubmit={submit} noValidate className='space-y-5 rounded-lg border p-4'>
          <div className='flex items-center justify-between gap-3 rounded-lg border px-3 py-2'>
            <div>
              <Label htmlFor='enabled'>Gunakan AI</Label>
              <p className='text-xs text-muted-foreground'>
                Saat mati atau router gagal, Tanya AI kembali memakai jawaban kata kunci bawaan.
                {!connection.popup && ' Pop up Tanya AI sedang dimatikan di menu Pengaturan.'}
              </p>
            </div>
            <Switch
              id='enabled'
              checked={form.data.enabled}
              onCheckedChange={(v) => {
                form.clearErrors('enabled')
                form.setData('enabled', v)
              }}
            />
          </div>
          <FieldError message={errors.enabled} />

          <div className='space-y-2'>
            <Label htmlFor='base_url'>Base URL</Label>
            <Input
              id='base_url'
              value={form.data.base_url}
              onChange={(e) => {
                form.clearErrors('base_url')
                form.setData('base_url', e.target.value)
              }}
              placeholder='https://router.contoh.com/v1'
              autoComplete='off'
            />
            <FieldError message={errors.base_url} />
            {form.data.base_url.startsWith('http://') && (
              <p className='text-xs text-amber-600 dark:text-amber-500'>Alamat memakai http biasa: API key terkirim tanpa enkripsi. Pakai https bila router mendukung.</p>
            )}
          </div>

          <div className='space-y-2'>
            <Label htmlFor='api_key'>API key</Label>
            <Input
              id='api_key'
              type='password'
              value={form.data.api_key}
              onChange={(e) => {
                form.clearErrors('api_key')
                form.setData((d) => ({ ...d, api_key: e.target.value, remove_key: false }))
              }}
              placeholder={connection.masked_key && !form.data.remove_key ? `Tersimpan (${connection.masked_key}). Kosongkan untuk tetap memakai yang lama.` : 'Tempel API key'}
              autoComplete='new-password'
            />
            <FieldError message={errors.api_key} />
            {connection.masked_key && (
              <label className='flex items-center gap-2 text-xs text-muted-foreground'>
                <input
                  type='checkbox'
                  checked={form.data.remove_key}
                  onChange={(e) => form.setData((d) => ({ ...d, remove_key: e.target.checked, api_key: '' }))}
                />
                Hapus API key tersimpan
              </label>
            )}
          </div>

          <div className='space-y-2'>
            <div className='flex items-center justify-between gap-2'>
              <Label htmlFor='chat_model'>Model chat</Label>
              <Button type='button' variant='link' size='sm' className='h-auto p-0' disabled={modelsLoading || !hasKey} onClick={loadModels}>
                {modelsLoading ? 'Memuat...' : models.length ? `Muat ulang daftar (${models.length})` : 'Muat daftar model'}
              </Button>
            </div>
            <ModelPicker
              id='chat_model'
              value={form.data.chat_model}
              onChange={(v) => {
                form.clearErrors('chat_model', 'enabled')
                form.setData('chat_model', v)
              }}
              models={models}
              loading={modelsLoading}
              onOpenEmpty={() => hasKey && loadModels()}
              placeholder='Pilih model untuk menjawab pelanggan'
            />
            <FieldError message={errors.chat_model} />
            {modelsError && <p className='text-sm text-destructive'>{modelsError}</p>}
          </div>

          <div className='space-y-2'>
            <Label htmlFor='vision_model'>Model vision</Label>
            <ModelPicker
              id='vision_model'
              value={form.data.vision_model}
              onChange={(v) => form.setData('vision_model', v)}
              models={models}
              loading={modelsLoading}
              onOpenEmpty={() => hasKey && loadModels()}
              emptyLabel='Sama dengan model chat'
              placeholder='Pilih model'
            />
            <p className='text-xs text-muted-foreground'>Untuk membaca gambar dari pelanggan dan gambar pengetahuan. Pastikan lolos Tes vision.</p>
            <FieldError message={errors.vision_model} />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='reasoning_effort'>Tingkat berpikir</Label>
            <Select
              value={form.data.reasoning_effort}
              onValueChange={(v) => {
                form.clearErrors('reasoning_effort')
                form.setData('reasoning_effort', v)
              }}
            >
              <SelectTrigger id='reasoning_effort' className='w-full'>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {LEVELS.map((l) => (
                  <SelectItem key={l.value} value={l.value}>{l.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className='text-xs text-muted-foreground'>
              Dikirim sebagai <span>reasoning_effort</span> ke model chat dan vision. Makin rendah makin cepat dan hemat, tetapi tidak semua model atau router mengenalnya. Bandingkan waktu dan token di hasil tes; bila tes menjadi galat, kembalikan ke Bawaan.
            </p>
            <FieldError message={errors.reasoning_effort} />
          </div>

          <Button type='submit' disabled={form.processing}>
            {form.processing ? 'Menyimpan...' : 'Simpan'}
          </Button>
        </form>

        <section className='mt-6 space-y-4 rounded-lg border p-4'>
          <div>
            <h2 className='font-semibold'>Tes</h2>
            <p className='text-sm text-muted-foreground'>
              Tes memakai pengaturan yang sudah disimpan.{form.isDirty && ' Ada perubahan yang belum disimpan.'}
            </p>
          </div>

          <div className='space-y-3'>
            {TESTS.map((t) => {
              const r = results[t.type]
              return (
                <div key={t.type} className='rounded-lg border px-3 py-2'>
                  <div className='flex items-center justify-between gap-3'>
                    <div>
                      <p className='text-sm font-medium'>{t.label}</p>
                      <p className='text-xs text-muted-foreground'>{t.hint}</p>
                    </div>
                    <Button type='button' variant='outline' size='sm' disabled={testing !== null} onClick={() => runTest(t.type)}>
                      {testing === t.type ? <Loader2 className='size-4 animate-spin' /> : 'Jalankan'}
                    </Button>
                  </div>
                  {r && (
                    <div className={`mt-2 flex gap-2 text-sm ${r.ok ? 'text-emerald-600 dark:text-emerald-500' : 'text-destructive'}`}>
                      {r.ok ? <CheckCircle2 className='mt-0.5 size-4 shrink-0' /> : <XCircle className='mt-0.5 size-4 shrink-0' />}
                      <div className='min-w-0 space-y-1'>
                        <p>
                          {r.message}
                          {r.ms !== undefined && (
                            <span className='text-muted-foreground'>
                              {' '}({(r.ms / 1000).toFixed(1)} detik
                              {r.usage && (r.usage.prompt !== null || r.usage.completion !== null) && `, token masuk ${r.usage.prompt ?? '?'}, keluar ${r.usage.completion ?? '?'}`})
                            </span>
                          )}
                        </p>
                        {r.reply && <p className='rounded bg-muted/50 px-2 py-1 whitespace-pre-wrap text-foreground'>{r.reply}</p>}
                      </div>
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        </section>

        <AdvancedForm values={advanced} ranges={advancedRanges} defaults={advancedDefaults} />
        </div>

        <div className='min-w-0 xl:sticky xl:top-20'>
          <ChatSimulator {...simulation} />
        </div>
        </div>
      </Main>
    </>
  )
}
