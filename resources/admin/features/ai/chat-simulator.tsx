import { useCallback, useEffect, useRef, useState } from 'react'
import { AlertTriangle, CheckCircle2, RotateCcw, XCircle } from 'lucide-react'
import { type ChatDebug, mountChat } from '../../../js/chat-widget.js'
import { Button } from '@/components/ui/button'

export type SimulationProps = {
  widget: { waEnabled: boolean; waNumber: string; waMessage: string; assistantName: string; greeting: string; imagesEnabled: boolean; imageMaxMb: number }
  status: { enabled: boolean; popup: boolean; max_tokens: number; history_messages: number }
}

type LogItem = { id: number; question: string; debug: ChatDebug; at: Date }

// Font widget toko (Space Grotesk), supaya tampilannya sama persis dengan di toko.
const FONT_URL = 'https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&display=swap'

/**
 * Panel Tanya AI toko (file widget yang sama, resources/js/chat-widget.js) dalam mode inline, dijawab AI dengan
 * pengaturan tersimpan lewat /admin/ai/simulasi. Tanpa batas harian dan tidak dicatat.
 */
export function ChatSimulator({ widget, status }: SimulationProps) {
  const box = useRef<HTMLDivElement>(null)
  const chat = useRef<ReturnType<typeof mountChat> | null>(null)
  const counter = useRef(0)
  const [log, setLog] = useState<LogItem[]>([])
  // Penanya yang disimulasikan: tamu (jawaban "silakan login") atau akun admin ini (jawaban memakai pesanan admin).
  const [asker, setAsker] = useState<'guest' | 'me'>('guest')
  const askerRef = useRef(asker)
  askerRef.current = asker

  const start = useCallback(() => {
    if (!box.current) return
    chat.current = mountChat({
      aiEnabled: true,
      ...widget,
      aiEndpoint: '/admin/ai/simulasi',
      persistDismiss: false,
      inline: box.current,
      extraFields: () => ({ as: askerRef.current }),
      onDebug: (debug, question) => setLog((prev) => [{ id: ++counter.current, question, debug, at: new Date() }, ...prev]),
    })
  }, [widget])

  useEffect(() => {
    if (!document.querySelector(`link[href="${FONT_URL}"]`)) {
      const link = document.createElement('link')
      link.rel = 'stylesheet'
      link.href = FONT_URL
      document.head.appendChild(link)
    }
    start()
    return () => chat.current?.destroy()
  }, [start])

  const restart = () => {
    chat.current?.destroy()
    setLog([])
    start()
  }

  return (
    <section className='space-y-3 rounded-lg border p-4'>
      <div className='flex items-start justify-between gap-3'>
        <div>
          <h2 className='font-semibold'>Simulasi chat</h2>
          <p className='text-sm text-muted-foreground'>Panel Tanya AI yang sama dengan di toko, dijawab AI memakai pengaturan tersimpan. Tanpa batas harian dan tidak dicatat.</p>
        </div>
        <Button type='button' variant='outline' size='sm' className='shrink-0' onClick={restart} title='Kosongkan percakapan. Pakai setelah mengubah pengaturan.'>
          <RotateCcw className='size-4' /> Mulai ulang
        </Button>
      </div>

      {!status.enabled && (
        <Notice text='Saklar Gunakan AI mati. Simulasi tetap memakai AI; di toko pelanggan mendapat jawaban kata kunci.' />
      )}
      {!status.popup && <Notice text='Pop up Tanya AI sedang dimatikan di Pengaturan (simulasi tetap jalan).' />}

      <div className='flex flex-wrap items-center gap-2 text-sm'>
        <span className='text-muted-foreground'>Bertanya sebagai:</span>
        {(['guest', 'me'] as const).map((v) => (
          <Button key={v} type='button' size='sm' variant={asker === v ? 'default' : 'outline'} onClick={() => setAsker(v)}>
            {v === 'guest' ? 'Tamu (belum login)' : 'Akun saya (dengan pesanan saya)'}
          </Button>
        ))}
      </div>

      <div ref={box} className='h-[560px]' />

      <p className='text-xs text-muted-foreground'>
        Batas jawaban {status.max_tokens} token, riwayat dikirim {status.history_messages} pesan terakhir. Gambar di simulasi tidak disimpan.
      </p>

      <div>
        <h3 className='text-sm font-medium'>Log debug</h3>
        {log.length === 0 ? (
          <p className='py-4 text-center text-sm text-muted-foreground'>Belum ada pertanyaan.</p>
        ) : (
          <ul className='mt-2 max-h-72 space-y-2 overflow-y-auto'>
            {log.map((item) => (
              <li key={item.id} className='rounded-md border px-3 py-2 text-sm'>
                <div className='flex items-start justify-between gap-2'>
                  <p className='min-w-0 font-medium break-words'>{item.question}</p>
                  <span className='shrink-0 text-xs text-muted-foreground'>{item.at.toLocaleTimeString('id-ID')}</span>
                </div>
                {item.debug?.error ? (
                  <p className='mt-1 flex gap-1.5 text-destructive'>
                    <XCircle className='mt-0.5 size-4 shrink-0' />
                    <span>{item.debug.error} Widget memakai jawaban kata kunci.</span>
                  </p>
                ) : (
                  <p className='mt-1 flex gap-1.5 text-emerald-600 dark:text-emerald-500'>
                    <CheckCircle2 className='mt-0.5 size-4 shrink-0' />
                    <span>
                      {item.debug?.asker && <span className='text-muted-foreground'>[{item.debug.asker}] </span>}
                      {item.debug?.model}
                      {item.debug?.requested && item.debug.model && !item.debug.requested.endsWith(item.debug.model) && ` (lewat ${item.debug.requested})`}
                      <span className='text-muted-foreground'>
                        {item.debug?.ms !== undefined && `, ${(item.debug.ms / 1000).toFixed(1)} detik`}
                        {item.debug?.prompt_tokens != null && `, token masuk ${item.debug.prompt_tokens}, keluar ${item.debug.completion_tokens ?? '?'}`}
                      </span>
                    </span>
                  </p>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  )
}

function Notice({ text }: { text: string }) {
  return (
    <p className='flex gap-2 rounded-md border border-amber-500/40 bg-amber-500/5 px-3 py-2 text-sm text-amber-700 dark:text-amber-400'>
      <AlertTriangle className='mt-0.5 size-4 shrink-0' />
      <span>{text}</span>
    </p>
  )
}
