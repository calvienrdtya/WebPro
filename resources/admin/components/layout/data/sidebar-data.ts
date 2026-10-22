import {
  BadgeCheck,
  BookOpen,
  Bot,
  CircleHelp,
  DatabaseBackup,
  FileText,
  History,
  KeyRound,
  Layers,
  LayoutDashboard,
  MessageSquareWarning,
  Package,
  Palette,
  Plug,
  Receipt,
  Rocket,
  ScrollText,
  Settings,
  ShieldCheck,
  ShoppingCart,
  Star,
  TicketPercent,
  Users,
  Wrench, MessagesSquare } from 'lucide-react'
import { type StockMenuItem } from '@/types'
import { type SidebarData } from '../types'

/** Badge angka; kosong bila nol. 99+ agar tidak melebarkan menu. */
const count = (n: number | undefined) => (n ? (n > 99 ? '99+' : String(n)) : undefined)

export const buildSidebarData = (stockMenu: StockMenuItem[], badges: Record<string, number> = {}): SidebarData => ({
  navGroups: [
    {
      title: 'Umum',
      items: [{ title: 'Dashboard', url: '/dashboard', icon: LayoutDashboard }],
    },
    {
      title: 'Katalog',
      items: [
        { title: 'Produk', url: '/admin/produk', icon: Package },
        { title: 'Varian', url: '/admin/varian', icon: Layers },
        { title: 'Garansi', url: '/admin/garansi', icon: BadgeCheck },
        { title: 'Biaya Layanan', url: '/admin/biaya-layanan', icon: Receipt },
        {
          title: 'Stok Akun',
          icon: KeyRound,
          items: [
            ...stockMenu.map((p) => ({
              title: p.name,
              url: `/admin/akun/${p.slug}`,
            })),
          ],
        },
      ],
    },
    {
      title: 'Transaksi',
      items: [
        { title: 'Pesanan', url: '/admin/pesanan', icon: ShoppingCart },
        { title: 'Klaim Garansi', url: '/admin/klaim-garansi', icon: ShieldCheck },
        { title: 'Bengkel', url: '/admin/bengkel', icon: Wrench },
        { title: 'Laporan', url: '/admin/laporan', icon: MessageSquareWarning },
        { title: 'Voucher', url: '/admin/voucher', icon: TicketPercent },
      ],
    },
    {
      title: 'Pengguna',
      items: [
        { title: 'User', url: '/admin/user', icon: Users },
        { title: 'Ulasan', url: '/admin/ulasan', icon: Star },
      ],
    },
    {
      title: 'AI',
      items: [
        { title: 'Koneksi', url: '/admin/ai/koneksi', icon: Plug },
        { title: 'Perilaku', url: '/admin/ai/perilaku', icon: Bot },
        { title: 'Pengetahuan', url: '/admin/ai/pengetahuan', icon: BookOpen },
        { title: 'Percakapan', url: '/admin/ai/percakapan', icon: MessagesSquare },
      ],
    },
    {
      title: 'Log',
      items: [
        { title: 'Log Aktivitas', url: '/admin/log-aktivitas', icon: History },
        { title: 'Log Sistem', url: '/admin/log-sistem', icon: ScrollText, badge: count(badges.system_log) },
      ],
    },
    {
      title: 'Lainnya',
      items: [
        { title: 'FAQ', url: '/admin/faq', icon: CircleHelp },
        { title: 'Tampilan Toko', url: '/admin/tampilan', icon: Palette },
        { title: 'Halaman Legal', url: '/admin/halaman-legal', icon: FileText },
        { title: 'Backup', url: '/admin/backup', icon: DatabaseBackup },
        { title: 'Panduan Deploy', url: '/admin/panduan-deploy', icon: Rocket },
        { title: 'Pengaturan', url: '/admin/pengaturan', icon: Settings },
      ],
    },
  ]})
