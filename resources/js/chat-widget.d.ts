export type ChatDebug = {
  model?: string
  requested?: string
  ms?: number
  prompt_tokens?: number | null
  completion_tokens?: number | null
  error?: string
  fallback?: boolean
  asker?: string
} | null

export type ChatConfig = {
  aiEnabled?: boolean
  waEnabled?: boolean
  waNumber?: string
  waMessage?: string
  aiEndpoint?: string
  persistDismiss?: boolean
  autoOpen?: boolean
  historyEndpoint?: string
  assistantName?: string
  greeting?: string
  imagesEnabled?: boolean
  imageMaxMb?: number
  extraFields?: (() => Record<string, string>) | null
  onDebug?: ((debug: ChatDebug, question: string) => void) | null
  inline?: HTMLElement | null
}

export function mountChat(config?: ChatConfig): { destroy: () => void; open: () => void }
