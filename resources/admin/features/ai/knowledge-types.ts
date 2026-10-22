export type KnowledgeStatus = 'processing' | 'review' | 'ready' | 'failed'

export type KnowledgeAttachment = { id: number; kind: 'image' | 'pdf'; name: string; size: number; pages: number | null; url: string }

export type KnowledgeEntry = {
  id: number
  title: string
  status: KnowledgeStatus
  status_label: string
  is_active: boolean
  is_builtin: boolean
  use_ai: boolean
  with_qa: boolean
  raw_text: string
  body: string
  error: string | null
  tokens: number
  products: { id: number; name: string }[]
  attachments: KnowledgeAttachment[]
  updated_at: string | null
}

export type KnowledgeLimits = { max_images: number; image_mb: number; pdf_mb: number; pdf_pages: number }

export const formatSize = (bytes: number) =>
  bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`
