import { Marked, type Tokens } from 'marked'

const escape = (s: string) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string)

// Isi bisa berasal dari AI: HTML mentah dan gambar ditampilkan sebagai teks, tautan hanya http(s) dan mailto.
const md = new Marked({
  gfm: true,
  breaks: true,
  renderer: {
    html({ text }: Tokens.HTML | Tokens.Tag) {
      return escape(text)
    },
    image({ text }: Tokens.Image) {
      return escape(text)
    },
    link(this: { parser: { parseInline: (t: Tokens.Link['tokens']) => string } }, { href, tokens }: Tokens.Link) {
      const label = this.parser.parseInline(tokens)
      return /^(https?:|mailto:)/i.test(href) ? `<a href="${escape(href)}" target="_blank" rel="noopener noreferrer">${label}</a>` : label
    },
  },
})

export const renderMarkdown = (src: string): string => md.parse(src, { async: false }) as string

/** Gaya tampilan Markdown tanpa plugin typography. */
export const MARKDOWN_CLASS =
  'text-sm leading-relaxed [&_h1]:mt-4 [&_h1]:text-xl [&_h1]:font-bold [&_h2]:mt-4 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mt-3 [&_h3]:font-semibold [&_p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:ps-5 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:ps-5 [&_li]:my-0.5 [&_a]:text-primary [&_a]:underline [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_table]:my-2 [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:bg-muted/50 [&_th]:px-2 [&_th]:py-1 [&_th]:text-start [&_td]:border [&_td]:px-2 [&_td]:py-1 [&_hr]:my-3 [&>*:first-child]:mt-0'
