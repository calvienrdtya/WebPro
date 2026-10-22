import { getCookie } from '@/lib/cookies'

/** POST JSON ke rute admin (token CSRF dari cookie XSRF-TOKEN). Galat 422 dikembalikan sebagai pesan, bukan dilempar. */
export async function postJson<T>(url: string, body: Record<string, unknown>): Promise<T & { message?: string; failed?: boolean }> {
  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': decodeURIComponent(getCookie('XSRF-TOKEN') ?? ''),
      },
      body: JSON.stringify(body),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok) {
      const message = res.status === 429 ? 'Terlalu banyak percobaan. Tunggu sebentar.' : (data.message ?? `Galat ${res.status}.`)
      return { ...data, message, failed: true }
    }
    return data
  } catch {
    return { message: 'Tidak bisa menghubungi server.', failed: true } as T & { message: string; failed: boolean }
  }
}
