export type AuthUser = {
  name: string
  email: string
  avatar: string | null
}

export type StockMenuItem = { name: string; slug: string }

export type SharedProps = {
  app: { name: string }
  flash: { success: string | null; error: string | null; warning: string | null }
  stockMenu: StockMenuItem[]
  /** Angka badge sidebar per menu (mis. system_log = error baru di Log Sistem). */
  sidebarBadges: Record<string, number>
  auth: { user: AuthUser }
}
