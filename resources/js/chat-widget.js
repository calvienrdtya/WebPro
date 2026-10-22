/*
 * ProAkses: widget chat mengambang (Tanya AI + Chat WhatsApp). Modul ini dipakai dua tempat dengan kode yang sama:
 * - toko: resources/js/store.js memanggil mountChat(window.PROAKSES_CHAT) bila layout menyetelnya
 *   (lihat App\Support\Whatsapp::chatConfig dan Pengaturan > Pop up chat);
 * - admin: simulasi chat di menu AI > Koneksi memasangnya dengan aiEndpoint simulasi (features/ai/chat-simulator.tsx).
 *
 *   mountChat({ aiEnabled: true, waEnabled: true, waNumber: "628...", waMessage: "...", aiEndpoint: "" })
 *
 * aiEnabled dan waEnabled adalah saklar terpisah di Pengaturan > Pop up chat: yang mati tidak dirender sama sekali.
 *
 * Tanya AI memakai aiEndpoint bila diisi server (AI menyala di menu AI > Koneksi): POST multipart {message, image?, history}
 * -> {reply, showWa, actions} | {fallback: true} | {limited: true, reply}. Tanpa endpoint atau saat AI gagal, dipakai
 * jawaban kata kunci di bawah. Kunci API AI HARUS tetap di server, bukan di sini.
 */
import { renderReply } from "./chat-format.js";

const DEFAULTS = {
  aiEnabled: true,
  waEnabled: true,
  waNumber: "",
  waMessage: "Halo ProAkses, saya mau tanya tentang produk.",
  aiEndpoint: "",
  // GET -> {messages: [{role, content, image, showWa}]}: percakapan yang sedang berjalan (user login: lintas perangkat).
  historyEndpoint: "",
  assistantName: "Asisten",
  greeting: "",
  // Tombol lampirkan gambar (dibaca model vision) dan batas ukurannya.
  imagesEnabled: false,
  imageMaxMb: 5,
  // Data tambahan yang ikut dikirim ke aiEndpoint (simulasi admin: penanya sebagai tamu atau akun admin).
  extraFields: null,
  // false: tombol tutup tidak diingat di sessionStorage (simulasi admin).
  persistDismiss: true,
  // true: panel chat langsung terbuka tanpa jeda kartu (simulasi admin).
  autoOpen: false,
  // Dipanggil dengan objek `debug` dari balasan endpoint (simulasi admin menampilkannya).
  onDebug: null,
  // Elemen tujuan: bila diisi, hanya panel chat yang dipasang di dalamnya (tidak mengambang, tanpa kartu dan
  // tombol tutup). Dipakai simulasi di menu AI > Koneksi.
  inline: null
};

/**
 * Memasang widget dan mengembalikan { destroy, open }. Satu widget per halaman; memasang ulang melepas yang lama.
 */
export function mountChat(options) {
  "use strict";
  if (window.__paChat) window.__paChat.destroy();

  var CONFIG = {};
  Object.keys(DEFAULTS).forEach(function (k) { CONFIG[k] = DEFAULTS[k]; });
  Object.keys(options || {}).forEach(function (k) { CONFIG[k] = options[k]; });
  var cleanups = [];
  function on(target, type, fn, opts) { target.addEventListener(type, fn, opts); cleanups.push(function () { target.removeEventListener(type, fn, opts); }); }
  var timers = [];

  var WA_URL = "https://wa.me/" + CONFIG.waNumber + "?text=" + encodeURIComponent(CONFIG.waMessage);
  var SHOW_DELAY = 1500;                 // jeda sebelum card muncul (ms)
  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Ikon ---------- */
  var I = {
    chat: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>',
    close: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    sparkle: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4M22 5h-4M4 17v2M5 18H3"/></svg>',
    wa: '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
    chev: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>',
    clip: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
    send: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>'
  };

  /* ---------- CSS (di-scope ke #pa-chat) ---------- */
  var CSS = '\
#pa-chat{--pa-navy:#0A0F1F;--pa-navy-2:#131A33;--pa-gold:#F59E0B;--pa-gold-2:#FBBF24;--pa-gold-100:#FEF3C7;--pa-gray-100:#F3F4F6;--pa-gray-200:#E5E7EB;--pa-gray-300:#D1D5DB;--pa-gray-500:#6B7280;--pa-wa:#25D366;--pa-wa-2:#128C7E;--pa-off:0px;\
position:fixed;right:24px;bottom:calc(24px + var(--pa-off));z-index:60;display:flex;flex-direction:column;align-items:flex-end;gap:12px;width:260px;font-family:"Space Grotesk",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-size:14px;line-height:1.5;color:var(--pa-navy);-webkit-font-smoothing:antialiased;pointer-events:none}\
#pa-chat *,#pa-chat *::before,#pa-chat *::after{box-sizing:border-box}\
:where(#pa-chat) button{font:inherit;cursor:pointer;border:0;background:none;color:inherit;padding:0}\
#pa-chat :focus-visible{outline:2px solid var(--pa-gold);outline-offset:2px}\
#pa-chat [hidden]{display:none!important}\
/* elemen yang muncul/menghilang: slide + fade, berurutan */\
.pa-rise{opacity:0;transform:translateY(16px);visibility:hidden;pointer-events:none;transition:opacity .3s ease,transform .3s cubic-bezier(.2,.7,.2,1),box-shadow .2s ease,border-color .2s ease,background-color .2s ease,visibility 0s linear .3s}\
#pa-chat[data-state="cards"] .pa-rise{opacity:1;transform:none;visibility:visible;pointer-events:auto;transition:opacity .35s ease var(--pa-d,0s),transform .4s cubic-bezier(.2,.7,.2,1) var(--pa-d,0s),box-shadow .2s ease,border-color .2s ease,background-color .2s ease,visibility 0s}\
#pa-chat[data-state="cards"] #pa-ai{--pa-d:0s}\
#pa-chat[data-state="cards"] #pa-wa{--pa-d:.1s}\
#pa-chat[data-state="cards"] #pa-hide{--pa-d:.2s}\
/* tombol tutup */\
#pa-hide{display:flex;width:34px;height:34px;align-items:center;justify-content:center;border:1px solid var(--pa-gray-200)!important;border-radius:9999px;background:#fff!important;color:var(--pa-navy);box-shadow:0 4px 12px rgba(0,0,0,.08)}\
#pa-hide svg{transition:transform .2s ease}\
#pa-hide:hover{background:var(--pa-gray-100)!important}\
#pa-hide:hover svg{transform:rotate(90deg)}\
/* card opsi */\
.pa-card{position:relative;display:flex;width:100%;align-items:center;gap:12px;padding:13px 14px;border:1px solid var(--pa-gray-200);border-radius:18px;background:linear-gradient(135deg,#fff 55%,#F9FAFB);text-align:left;text-decoration:none;color:var(--pa-navy);box-shadow:0 8px 24px rgba(0,0,0,.08),0 2px 6px rgba(0,0,0,.04)}\
.pa-card:hover{transform:scale(1.02)!important;box-shadow:0 14px 34px rgba(0,0,0,.14),0 3px 8px rgba(0,0,0,.06)}\
.pa-card:active{transform:scale(.98)!important}\
.pa-card-ai{--pa-accent:var(--pa-gold);border-color:rgba(245,158,11,.4)}\
.pa-card-ai:hover{border-color:var(--pa-gold)}\
.pa-card-wa{--pa-accent:var(--pa-wa);border-color:rgba(37,211,102,.4)}\
.pa-card-wa:hover{border-color:var(--pa-wa)}\
.pa-ic{display:flex;width:46px;height:46px;flex-shrink:0;align-items:center;justify-content:center;border-radius:9999px}\
.pa-card-ai .pa-ic{background:linear-gradient(135deg,var(--pa-gold-2),var(--pa-gold));color:var(--pa-navy);box-shadow:0 4px 12px rgba(245,158,11,.4)}\
.pa-card-wa .pa-ic{background:linear-gradient(135deg,var(--pa-wa),var(--pa-wa-2));color:#fff;box-shadow:0 4px 12px rgba(37,211,102,.4)}\
.pa-txt{min-width:0;flex:1}\
.pa-txt b{display:block;font-size:15px;font-weight:700;line-height:1.2;color:var(--pa-navy)}\
.pa-txt small{display:block;margin-top:3px;font-size:12px;line-height:1.3;color:var(--pa-gray-500)}\
.pa-chev{flex-shrink:0;opacity:.5;transition:transform .2s ease,opacity .2s ease}\
.pa-card-ai .pa-chev{color:var(--pa-gold)}\
.pa-card-wa .pa-chev{color:var(--pa-wa-2)}\
.pa-card:hover .pa-chev{opacity:.9;transform:translateX(2px)}\
.pa-tag{position:absolute;top:-9px;right:14px;display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;line-height:1.5;letter-spacing:.04em}\
.pa-tag-ai{background:var(--pa-gold);color:var(--pa-navy)}\
.pa-tag-on{background:#DCFCE7;color:#166534;border:1px solid rgba(22,101,52,.15)}\
.pa-tag-on i{width:6px;height:6px;border-radius:9999px;background:#22C55E;animation:pa-live 1.8s ease-out infinite}\
@keyframes pa-live{0%{box-shadow:0 0 0 0 rgba(34,197,94,.6)}70%,100%{box-shadow:0 0 0 6px rgba(34,197,94,0)}}\
/* panel chat (menggantikan card) */\
#pa-panel{position:absolute;right:0;bottom:0;display:flex;flex-direction:column;width:390px;height:min(560px,calc(100vh - 48px));overflow:hidden;border:1px solid var(--pa-gray-200);border-radius:20px;background:#fff;box-shadow:0 12px 40px rgba(0,0,0,.15);opacity:0;transform:scale(.94);transform-origin:bottom right;visibility:hidden;pointer-events:none;transition:opacity .2s ease,transform .2s ease,visibility 0s linear .2s}\
#pa-chat[data-state="chat"] #pa-panel{opacity:1;transform:none;visibility:visible;pointer-events:auto;transition:opacity .2s ease,transform .2s ease,visibility 0s}\
.pa-head{display:flex;align-items:center;gap:12px;padding:14px 16px;background:linear-gradient(135deg,var(--pa-navy-2),var(--pa-navy));color:#fff}\
.pa-avatar{display:flex;width:40px;height:40px;flex-shrink:0;align-items:center;justify-content:center;border-radius:9999px;background:linear-gradient(135deg,var(--pa-gold-2),var(--pa-gold));color:var(--pa-navy)}\
.pa-head-t{min-width:0;flex:1}\
.pa-head-t b{display:block;font-size:15px;font-weight:600;line-height:1.2}\
.pa-status{display:flex;align-items:center;gap:6px;margin-top:2px;font-size:12px;color:rgba(255,255,255,.8)}\
.pa-status i{width:8px;height:8px;border-radius:9999px;background:#34D399}\
.pa-x{display:flex;width:36px;height:36px;align-items:center;justify-content:center;border-radius:9999px;color:#fff;transition:background .2s ease}\
.pa-x:hover{background:rgba(255,255,255,.14)}\
#pa-log{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#fff;overscroll-behavior:contain}\
.pa-msg{max-width:85%;padding:10px 12px;border-radius:12px;font-size:14px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere;animation:pa-in .2s ease}\
.pa-msg.ai{align-self:flex-start;background:var(--pa-gray-100);color:var(--pa-navy);border-bottom-left-radius:4px}\
.pa-msg.me{align-self:flex-end;background:var(--pa-gold);color:var(--pa-navy);border-bottom-right-radius:4px;font-weight:500}\
.pa-rich{white-space:normal}\
#pa-chat .pa-rich p{margin:0}\
#pa-chat .pa-rich p+p,#pa-chat .pa-rich p+ul,#pa-chat .pa-rich p+ol,#pa-chat .pa-rich ul+p,#pa-chat .pa-rich ol+p,#pa-chat .pa-rich ul+ul,#pa-chat .pa-rich ol+ol{margin-top:8px}\
#pa-chat .pa-rich ul,#pa-chat .pa-rich ol{margin:0;padding-left:20px}\
#pa-chat .pa-rich ul{list-style:disc}#pa-chat .pa-rich ol{list-style:decimal}\
#pa-chat .pa-rich li{margin:2px 0}#pa-chat .pa-rich li::marker{color:var(--pa-gray-500)}\
#pa-chat .pa-rich strong{font-weight:700}#pa-chat .pa-rich em{font-style:italic}\
#pa-chat .pa-rich a:not(.pa-act){color:var(--pa-navy);text-decoration:underline;text-underline-offset:2px;word-break:break-all}\
#pa-chat .pa-rich code{padding:1px 4px;border-radius:4px;background:var(--pa-gray-200);font-size:13px}\
#pa-chat .pa-rich .pa-h{font-size:15px}\
.pa-act{display:inline-block;margin-top:8px;padding:6px 12px;border-radius:9999px;background:var(--pa-navy);color:var(--pa-gold-2);font-size:12px;font-weight:600;text-decoration:none}\
.pa-act:hover{opacity:.85}\
.pa-typing{display:flex;gap:4px;align-self:flex-start;padding:12px 14px;border-radius:12px;border-bottom-left-radius:4px;background:var(--pa-gray-100)}\
.pa-typing i{width:6px;height:6px;border-radius:9999px;background:var(--pa-gray-500);animation:pa-blink 1s ease-in-out infinite}\
.pa-typing i:nth-child(2){animation-delay:.15s}.pa-typing i:nth-child(3){animation-delay:.3s}\
@keyframes pa-blink{0%,80%,100%{opacity:.3}40%{opacity:1}}\
@keyframes pa-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}\
.pa-quick{display:flex;gap:8px;overflow-x:auto;padding:8px 16px 10px;border-top:1px solid var(--pa-gray-100);background:#fff;scrollbar-width:none}\
.pa-quick::-webkit-scrollbar{display:none}\
.pa-chip{flex-shrink:0;min-height:36px;padding:0 12px;border:1px solid var(--pa-gold)!important;border-radius:9999px;background:var(--pa-gold-100)!important;color:var(--pa-navy);font-size:13px;font-weight:600;white-space:nowrap;transition:background .2s ease}\
.pa-chip:hover{background:#FDE68A!important}\
.pa-foot{display:flex;align-items:center;gap:8px;padding:10px 12px 12px;border-top:1px solid var(--pa-gray-100);background:#fff}\
.pa-input{flex:1;min-width:0;height:44px;padding:0 14px;border:1px solid transparent;border-radius:9999px;background:var(--pa-gray-100);color:var(--pa-navy);font:inherit;font-size:14px;transition:border-color .2s ease,background .2s ease}\
.pa-input::placeholder{color:var(--pa-gray-500)}\
#pa-chat .pa-input:focus,#pa-chat .pa-input:focus-visible{outline:none;box-shadow:none;border-color:var(--pa-gold);background:#fff}\
.pa-send{display:flex;width:44px;height:44px;flex-shrink:0;align-items:center;justify-content:center;border-radius:9999px;background:linear-gradient(135deg,var(--pa-gold-2),var(--pa-gold))!important;color:var(--pa-navy);transition:filter .2s ease,transform .2s ease}\
.pa-send:hover{filter:brightness(1.05)}.pa-send:active{transform:scale(.94)}\
.pa-send:disabled{opacity:.5;cursor:not-allowed}\
.pa-attach{display:flex;width:40px;height:40px;flex-shrink:0;align-items:center;justify-content:center;border-radius:9999px;color:var(--pa-gray-500);transition:background .2s ease,color .2s ease}\
.pa-attach:hover{background:var(--pa-gray-100);color:var(--pa-navy)}\
.pa-attach:disabled{opacity:.5;cursor:not-allowed}\
.pa-pending{display:flex;align-items:center;gap:8px;padding:8px 16px 0;background:#fff}\
.pa-pending img{width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid var(--pa-gray-200)}\
.pa-pending span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--pa-gray-500)}\
.pa-pending button{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:9999px;color:var(--pa-gray-500)}\
.pa-pending button:hover{background:var(--pa-gray-100);color:var(--pa-navy)}\
.pa-msg img.pa-img{display:block;max-width:100%;max-height:180px;margin-bottom:6px;border-radius:8px}\
.pa-msg .pa-imgtag{display:block;margin-bottom:4px;font-size:12px;opacity:.75}\
.pa-acts{display:flex;flex-wrap:wrap;gap:6px}\
.pa-acts .pa-act{margin-top:8px}\
#pa-chat[data-state="chat"] .pa-rise{opacity:0;visibility:hidden;pointer-events:none}\
@media (max-width:1023px){#pa-chat{right:20px;bottom:calc(20px + var(--pa-off));width:240px}#pa-panel{width:340px}}\
@media (max-width:640px){\
#pa-chat{right:16px;bottom:calc(16px + var(--pa-off));width:220px;gap:10px}\
.pa-card{padding:11px 12px;gap:10px}\
.pa-ic{width:42px;height:42px}\
.pa-txt b{font-size:14px}.pa-txt small{font-size:12px}\
#pa-panel{position:fixed;inset:0;width:100%;height:100%;height:100dvh;border:0;border-radius:0;transform-origin:bottom right}\
.pa-head{padding-top:max(14px,env(safe-area-inset-top))}\
.pa-foot{padding-bottom:max(12px,env(safe-area-inset-bottom))}\
.pa-input{font-size:16px}\
}\
/* mode inline (simulasi admin): panel diam di dalam kotak tujuan, mengikuti tinggi kotak */\
#pa-chat[data-inline]{position:relative;right:auto;bottom:auto;z-index:auto;width:100%;height:100%;pointer-events:auto}\
#pa-chat[data-inline] #pa-panel{position:relative;inset:auto;width:100%;height:100%;border:1px solid var(--pa-gray-200);border-radius:20px;opacity:1;transform:none;visibility:visible;pointer-events:auto;box-shadow:none}\
#pa-chat[data-inline] .pa-x{display:none}\
@media (prefers-reduced-motion:reduce){\
.pa-rise,#pa-chat[data-state="cards"] .pa-rise,#pa-panel,.pa-msg{transition:none!important;animation:none}\
.pa-tag-on i,.pa-typing i{animation:none}\
#pa-hide:hover svg{transform:none}\
}';

  /* ---------- Jawaban statis (dummy) ---------- */
  var QUICK = ["Cara order?", "Metode pembayaran?", "Garansi akun?"].concat(CONFIG.waEnabled ? ["Hubungi CS"] : []);
  var RULES = [
    { re: /(hubungi|\bcs\b|admin|manusia|whatsapp|\bwa\b)/, reply: "Baik, tim CS kami siap membantu langsung lewat WhatsApp. Klik tombol di bawah ya.", wa: true },
    { re: /(cara|langkah).*(order|beli|pesan)|(order|beli|pesan).*(gimana|bagaimana|cara)/, reply: "Cara ordernya 4 langkah:\n1. Pilih produk, durasi, dan paket garansi, lalu klik Beli Sekarang (perlu masuk akun).\n2. Bayar dengan scan QRIS, lalu klik “Saya Sudah Bayar”.\n3. Admin memverifikasi pembayaranmu.\n4. Detail login dikirim ke email kamu dan tampil di halaman pesanan.", act: { t: "Lihat produk", href: "/produk" } },
    { re: /(metode|bayar|pembayaran|qris|gopay|ovo|dana|shopeepay|transfer)/, reply: "Saat ini kami menerima pembayaran via QRIS, yang bisa di-scan dari aplikasi e-wallet atau m-banking apa pun (GoPay, OVO, Dana, ShopeePay, dan lainnya). Pembayaran diverifikasi manual oleh admin." },
    { re: /(garansi|klaim|ganti|bermasalah|diblokir|tidak bisa login)/, reply: "Garansi adalah paket pilihan yang wajib dipilih saat memesan; nama, harga, dan masa garansinya tertera di halaman produk. Jika akun bermasalah (tidak bisa login, diblokir, dll.) selama masa garansi, kirim laporan dari halaman Lapor Masalah atau hubungi CS, dan kami akan mengganti akunnya.", act: { t: "Lapor Masalah", href: "/lapor-masalah" } },
    { re: /(lama|kirim|dikirim|sampai|proses|nunggu|menunggu)/, reply: "Akun dikirim ke email kamu setelah pembayaran terverifikasi admin. Untuk paket 2 bulan atau lebih, kamu mendapat satu akun per bulan: akun bulan pertama setelah pembayaran terverifikasi, bulan berikutnya otomatis ke email kamu sekitar 28 hari setelah akun sebelumnya." },
    { re: /(aman|legal|terpercaya|resmi|penipuan)/, reply: "Akun kami siapkan sebelum dijual, pembayaran diverifikasi admin, dan ada paket garansi penggantian jika terjadi masalah selama masa garansi." },
    { re: /(produk|harga|chatgpt|claude|netflix|spotify|disney|hbo|youtube|canva|capcut|langganan)/, reply: "Kami menyediakan akun AI premium (ChatGPT Plus, Claude Pro), streaming (Netflix, Disney+ Hotstar, HBO Max, Spotify, YouTube Premium), dan tools kreatif (Canva Pro, CapCut Pro). Harga lengkap ada di daftar produk.", act: { t: "Lihat produk", href: "/produk" } },
    { re: /\b(halo|hai|hi|hello|selamat)\b/, reply: "Halo! Ada yang bisa saya bantu soal produk, cara order, atau garansi?" }
  ];
  var FALLBACK = CONFIG.waEnabled
    ? "Maaf, saya belum menemukan jawaban untuk itu. Coba pilih pertanyaan cepat di bawah, atau hubungi CS kami lewat WhatsApp."
    : "Maaf, saya belum menemukan jawaban untuk itu. Coba pilih pertanyaan cepat di bawah, atau cek halaman FAQ.";
  var NO_WA = { reply: "Untuk bantuan langsung, kirim laporan dari halaman Lapor Masalah atau cek halaman FAQ.", act: { t: "Lapor Masalah", href: "/lapor-masalah" } };

  function localAnswer(text) {
    var q = text.toLowerCase();
    for (var i = 0; i < RULES.length; i++) {
      if (RULES[i].re.test(q)) return RULES[i].wa && !CONFIG.waEnabled ? NO_WA : RULES[i];
    }
    return CONFIG.waEnabled ? { reply: FALLBACK, wa: false, showWa: true } : { reply: FALLBACK, act: NO_WA.act };
  }

  /* ---------- Bangun UI ---------- */
  var root, aiBtn, waBtn, hideBtn, panel, log, input, sendBtn, quick, attachBtn, fileInput, pendingBox;
  var pendingImage = null;           // File gambar yang akan dikirim bersama pesan berikutnya
  var state = "hidden";              // hidden | cards | chat
  var chatBuilt = false;
  var busy = false;
  var history = [];

  function escapeHtml(t) { return String(t).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }

  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }

  // Ditutup hanya berlaku selama sesi browser ini (sessionStorage). Tab/kunjungan baru: widget tampil lagi.
  function recentlyClosed() {
    if (!CONFIG.persistDismiss) return false;
    try { return sessionStorage.getItem("pa_chat_closed") === "1"; } catch (e) { return false; }
  }

  function init() {
    if (recentlyClosed()) return;                       // sudah ditutup di sesi ini: jangan tampilkan

    if (!document.getElementById("pa-chat-css")) {
      var style = document.createElement("style");
      style.id = "pa-chat-css";
      style.textContent = CSS;
      document.head.appendChild(style);
    }

    root = el("div");
    root.id = "pa-chat";
    root.setAttribute("data-state", "hidden");
    root.setAttribute("role", "region");
    root.setAttribute("aria-label", "Bantuan chat");
    if (CONFIG.inline) {
      root.setAttribute("data-inline", "");
      root.innerHTML = '<section id="pa-panel" role="region" aria-label="Simulasi chat dengan ' + escapeHtml(CONFIG.assistantName) + '"></section>';
      CONFIG.inline.appendChild(root);
      panel = root.querySelector("#pa-panel");
      setState("chat");
      return;
    }

    root.innerHTML =
      '<button type="button" id="pa-hide" class="pa-rise" aria-label="Tutup chat">' + I.close.replace('width="24" height="24"', 'width="16" height="16"') + '</button>' +
      (CONFIG.aiEnabled ?
      '<button type="button" id="pa-ai" class="pa-card pa-card-ai pa-rise" aria-label="Tanya AI, jawaban instan 24 jam">' +
        '<span class="pa-tag pa-tag-ai" aria-hidden="true">AI</span>' +
        '<span class="pa-ic">' + I.sparkle + '</span>' +
        '<span class="pa-txt"><b>Tanya AI</b><small>Jawaban instan 24/7</small></span>' +
        '<span class="pa-chev">' + I.chev + '</span>' +
      '</button>' : '') +
      (CONFIG.waEnabled ?
      '<a id="pa-wa" class="pa-card pa-card-wa pa-rise" href="' + WA_URL + '" target="_blank" rel="noopener noreferrer" aria-label="Chat WhatsApp, bantuan langsung dari admin">' +
        '<span class="pa-tag pa-tag-on" aria-hidden="true"><i></i>Online</span>' +
        '<span class="pa-ic">' + I.wa + '</span>' +
        '<span class="pa-txt"><b>Chat WhatsApp</b><small>Bantuan langsung dari admin</small></span>' +
        '<span class="pa-chev">' + I.chev + '</span>' +
      '</a>' : '') +
      (CONFIG.aiEnabled ? '<section id="pa-panel" role="dialog" aria-modal="false" aria-label="Chat dengan ' + escapeHtml(CONFIG.assistantName) + '" aria-hidden="true"></section>' : '');
    document.body.appendChild(root);

    aiBtn = root.querySelector("#pa-ai");
    waBtn = root.querySelector("#pa-wa");
    hideBtn = root.querySelector("#pa-hide");
    panel = root.querySelector("#pa-panel");

    if (aiBtn) aiBtn.addEventListener("click", function () { setState("chat"); });
    hideBtn.addEventListener("click", dismiss);
    on(document, "keydown", function (e) {
      if (e.key === "Escape" && state === "chat") { setState("cards"); aiBtn && aiBtn.focus(); }
    });

    syncOffset();
    on(window, "resize", syncOffset);
    on(window, "scroll", syncOffset, { passive: true });
    var bar = document.getElementById("buy-bar");
    if (bar && "MutationObserver" in window) {
      var mo = new MutationObserver(syncOffset);
      mo.observe(bar, { attributes: true });
      cleanups.push(function () { mo.disconnect(); });
    }

    // tampil otomatis setelah jeda (slide-up + fade-in, berurutan); simulasi admin langsung membuka panel chat
    if (CONFIG.autoOpen && CONFIG.aiEnabled) timers.push(setTimeout(function () { setState("chat"); }, 50));
    else timers.push(setTimeout(function () { setState("cards"); }, reduceMotion ? 300 : SHOW_DELAY));
  }

  /* Naikkan widget jika ada sticky bar bawah (halaman detail produk, mobile) */
  function syncOffset() {
    var bar = document.getElementById("buy-bar");
    var off = 0;
    if (bar && !bar.hidden && window.innerWidth < 768) {
      var cs = getComputedStyle(bar);
      if (cs.display !== "none" && cs.position === "fixed") off = bar.offsetHeight;
    }
    root.style.setProperty("--pa-off", off + "px");
  }

  function setState(next) {
    if (!root) return;
    if (next === "chat" && !chatBuilt) buildChat();          // lazy: isi panel dibuat saat pertama dibuka
    state = next;
    root.setAttribute("data-state", next);
    if (panel) panel.setAttribute("aria-hidden", String(next !== "chat"));
    if (next === "chat" && !CONFIG.inline) setTimeout(function () { input && input.focus(); }, reduceMotion ? 0 : 220);
  }

  /* Tutup: slide-down + fade-out, ingat 24 jam */
  function dismiss() {
    setState("hidden");
    if (!CONFIG.persistDismiss) return;
    try { sessionStorage.setItem("pa_chat_closed", "1"); } catch (e) {}
  }

  function buildChat() {
    chatBuilt = true;
    panel.innerHTML =
      '<header class="pa-head">' +
        '<span class="pa-avatar">' + I.sparkle + '</span>' +
        '<div class="pa-head-t"><b>' + escapeHtml(CONFIG.assistantName) + '</b><span class="pa-status"><i></i>Online</span></div>' +
        '<button type="button" class="pa-x" aria-label="Tutup chat">' + I.close + '</button>' +
      '</header>' +
      '<div id="pa-log" role="log" aria-live="polite" tabindex="0"></div>' +
      '<div class="pa-quick" id="pa-quick"></div>' +
      '<div class="pa-pending" id="pa-pending" hidden><img alt=""><span></span><button type="button" aria-label="Hapus gambar">' + I.close.replace('width="24" height="24"', 'width="14" height="14"') + '</button></div>' +
      '<form class="pa-foot" id="pa-form" autocomplete="off">' +
        (useAttach() ? '<button type="button" class="pa-attach" id="pa-attach" aria-label="Lampirkan gambar" title="Lampirkan gambar">' + I.clip + '</button>' +
          '<input type="file" id="pa-file" accept="image/jpeg,image/png,image/webp" hidden>' : '') +
        '<input class="pa-input" id="pa-input" type="text" maxlength="500" placeholder="Tanya apa saja tentang produk, cara order, atau garansi..." aria-label="Tulis pesan">' +
        '<button type="submit" class="pa-send" aria-label="Kirim pesan">' + I.send + '</button>' +
      '</form>';

    log = panel.querySelector("#pa-log");
    input = panel.querySelector("#pa-input");
    sendBtn = panel.querySelector(".pa-send");
    quick = panel.querySelector("#pa-quick");
    pendingBox = panel.querySelector("#pa-pending");
    attachBtn = panel.querySelector("#pa-attach");
    fileInput = panel.querySelector("#pa-file");
    if (attachBtn) {
      attachBtn.addEventListener("click", function () { fileInput.click(); });
      fileInput.addEventListener("change", function () { pickImage(fileInput.files && fileInput.files[0]); fileInput.value = ""; });
      pendingBox.querySelector("button").addEventListener("click", function () { pickImage(null); });
    }

    panel.querySelector(".pa-x").addEventListener("click", function () { setState("cards"); aiBtn && aiBtn.focus(); });
    panel.querySelector("#pa-form").addEventListener("submit", function (e) {
      e.preventDefault();
      var v = input.value.trim();
      if ((!v && !pendingImage) || busy) return;   // cegah kirim ganda saat menunggu jawaban
      input.value = "";
      ask(v, pendingImage);
      pickImage(null);
    });
    QUICK.forEach(function (q) {
      var b = el("button", "pa-chip"); b.type = "button"; b.textContent = q;
      b.addEventListener("click", function () { if (!busy) ask(q); });
      quick.appendChild(b);
    });

    var hello = CONFIG.greeting || "Halo! Tanyakan soal produk, cara order, pembayaran, atau garansi ya.";
    if (!CONFIG.historyEndpoint) { addMsg("ai", hello); return; }

    // Lanjutkan percakapan yang sedang berjalan (tersimpan di server).
    fetch(CONFIG.historyEndpoint, { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } })
      .then(function (r) { return r.ok ? r.json() : { messages: [] }; })
      .catch(function () { return { messages: [] }; })
      .then(function (d) {
        addMsg("ai", hello);
        (d.messages || []).forEach(function (m) {
          if (m.role === "user") { addMsg("me", m.content, null, m.image ? { label: "Gambar terlampir" } : null); history.push({ role: "user", content: m.content }); }
          else if (m.content) { addMsg("ai", m.content, m.showWa && CONFIG.waEnabled ? { t: "Chat WhatsApp", blank: true, href: WA_URL } : null); history.push({ role: "assistant", content: m.content }); }
        });
      });
  }

  function useAttach() { return !!(CONFIG.imagesEnabled && CONFIG.aiEndpoint); }

  /* Gambar terpilih menunggu dikirim bersama pesan berikutnya; dicek ukuran dan jenisnya di sini, diperiksa ulang di server. */
  function pickImage(file) {
    if (pendingBox.dataset.url) { URL.revokeObjectURL(pendingBox.dataset.url); delete pendingBox.dataset.url; }
    pendingImage = null;
    pendingBox.hidden = true;
    if (!file) return;
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { addMsg("ai", "Gambar harus berformat JPG, PNG, atau WebP."); return; }
    if (file.size > CONFIG.imageMaxMb * 1024 * 1024) { addMsg("ai", "Ukuran gambar maksimal " + CONFIG.imageMaxMb + " MB."); return; }
    pendingImage = file;
    pendingBox.dataset.url = URL.createObjectURL(file);
    pendingBox.querySelector("img").src = pendingBox.dataset.url;
    pendingBox.querySelector("span").textContent = file.name;
    pendingBox.hidden = false;
    input && input.focus();
  }

  /* act: satu tombol {t, href, blank} atau daftar tombol. image: {url} pratinjau lokal atau {label} untuk riwayat. */
  function addMsg(who, text, act, image) {
    var m = el("div", "pa-msg " + who);
    if (image && image.url) { var img = el("img", "pa-img"); img.src = image.url; img.alt = "Gambar terlampir"; m.appendChild(img); }
    else if (image && image.label) m.appendChild(el("span", "pa-imgtag")).textContent = image.label;
    // Jawaban AI boleh berformat ringan (tebal, daftar, tautan); pesan pelanggan selalu teks polos.
    if (who === "ai") { m.classList.add("pa-rich"); m.insertAdjacentHTML("beforeend", renderReply(text)); }
    else if (text) m.appendChild(document.createTextNode(text));
    var acts = act ? (Array.isArray(act) ? act : [act]) : [];
    if (acts.length) {
      var wrap = el("div", "pa-acts");
      acts.forEach(function (x) {
        var a = el("a", "pa-act"); a.textContent = x.t; a.href = x.href;
        if (x.blank) { a.target = "_blank"; a.rel = "noopener noreferrer"; }
        wrap.appendChild(a);
      });
      m.appendChild(wrap);
    }
    log.appendChild(m);
    log.scrollTop = log.scrollHeight;
    return m;
  }

  function typing(on) {
    var t = log.querySelector(".pa-typing");
    if (on && !t) { t = el("div", "pa-typing", "<i></i><i></i><i></i>"); t.setAttribute("aria-label", "AI sedang mengetik"); log.appendChild(t); log.scrollTop = log.scrollHeight; }
    if (!on && t) t.remove();
  }

  function setBusy(v) { busy = v; if (sendBtn) sendBtn.disabled = v; if (attachBtn) attachBtn.disabled = v; }

  /*
   * askAI: hubungkan ke backend di sini.
   * Jika CONFIG.aiEndpoint diisi, kirim POST {message, history} dan harapkan {reply}; {fallback: true} berarti
   * AI sedang tidak bisa menjawab sehingga dipakai jawaban kata kunci. Jika kosong / gagal, pakai jawaban statis.
   * Jangan menaruh API key di client.
   */
  function xsrf() {
    var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : "";
  }

  function askAI(text, image) {
    if (CONFIG.aiEndpoint) {
      var body = new FormData();
      body.append("message", text);
      if (image) body.append("image", image);
      body.append("history", JSON.stringify(history.slice(0, -1).slice(-50)));
      var extra = CONFIG.extraFields && CONFIG.extraFields();
      if (extra) Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
      return fetch(CONFIG.aiEndpoint, {
        method: "POST",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", "X-XSRF-TOKEN": xsrf() },
        body: body
      }).then(function (r) {
        // 422 = isian ditolak (mis. gambar terlalu besar), 429 = terlalu banyak pesan: tampilkan pesannya apa adanya.
        if (r.status === 422) return r.json().then(function (d) { var e = d.errors || {}; var k = Object.keys(e)[0]; return { limited: true, reply: k ? e[k][0] : (d.message || "Pesan ditolak.") }; });
        if (!r.ok && r.status !== 429) throw new Error("http " + r.status);
        return r.json();
      })
        .then(function (d) {
          if (typeof CONFIG.onDebug === "function") CONFIG.onDebug(d.debug || null, text);
          if (d.limited) return { reply: String(d.reply), showWa: !!d.showWa, actions: d.actions || [], fromAi: true };
          if (d.fallback || !d.reply) return localAnswer(text || "gambar");
          return { reply: String(d.reply), showWa: !!d.showWa, actions: d.actions || [], fromAi: true };
        })
        .catch(function (e) {
          if (typeof CONFIG.onDebug === "function") CONFIG.onDebug({ error: "Permintaan gagal (" + e.message + ")" }, text);
          return localAnswer(text);
        });
    }
    return new Promise(function (resolve) { setTimeout(function () { resolve(localAnswer(text)); }, reduceMotion ? 200 : 650 + Math.random() * 400); });
  }

  function ask(text, image) {
    addMsg("me", text, null, image ? { url: URL.createObjectURL(image) } : null);
    history.push({ role: "user", content: text });
    setBusy(true);
    typing(true);
    askAI(text, image).then(function (res) {
      typing(false);
      var act = res.act ? [res.act] : [];
      (res.actions || []).forEach(function (a) { act.push({ t: a.t, href: a.href }); });
      // Jawaban AI yang mengarahkan ke admin: pesan WhatsApp sudah berisi pertanyaan pelanggan supaya admin paham konteksnya.
      if ((res.wa || res.showWa) && CONFIG.waEnabled) {
        act.push({ t: "Chat WhatsApp", blank: true, href: res.fromAi && text
          ? "https://wa.me/" + CONFIG.waNumber + "?text=" + encodeURIComponent(CONFIG.waMessage + "\n\nPertanyaan saya: " + text)
          : WA_URL });
      }
      addMsg("ai", res.reply, act);
      history.push({ role: "assistant", content: res.reply });
    }).then(function () { setBusy(false); input && input.focus(); });
  }

  function destroy() {
    timers.forEach(clearTimeout);
    cleanups.forEach(function (fn) { fn(); });
    if (root) root.remove();
    root = null;
    if (window.__paChat === api) window.__paChat = null;
  }

  var api = { destroy: destroy, open: function () { if (root) setState(CONFIG.aiEnabled ? "chat" : "cards"); } };
  window.__paChat = api;
  init();
  return api;
}
