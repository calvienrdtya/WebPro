// Alpine.js mandiri (sebelumnya ikut Livewire, yang tidak dipakai toko). Dimulai di akhir file; fungsi x-data dari
// <script> inline di halaman (mis. reportForm) sudah ada karena modul ini berjalan setelah HTML selesai dibaca.
import Alpine from 'alpinejs'

const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches

/** Kartu muncul bergantian (fade-in + slide-up) saat masuk layar. Tanpa JS kartu tetap terlihat. */
function revealOnScroll(sectionId, jsClass, cardSelector) {
  const section = document.getElementById(sectionId)
  if (!section || !('IntersectionObserver' in window) || reduceMotion) return
  section.classList.add(jsClass)
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        e.target.classList.add('in')
        io.unobserve(e.target)
      }
    })
  }, { threshold: 0.15 })
  section.querySelectorAll(cardSelector).forEach((c) => io.observe(c))
}

/** Efek sambutan: partikel emas jatuh halus, sekali saat halaman dibuka. */
function heroParticles() {
  const hero = document.getElementById('hero')
  const fx = document.getElementById('hh-fx')
  if (!hero || !fx || reduceMotion) return

  const count = window.innerWidth < 640 ? 32 : 80
  const colors = ['#F59E0B', '#FBBF24', '#FCD34D', '#FDE68A']
  const fall = hero.offsetHeight + 30
  let maxEnd = 0

  for (let i = 0; i < count; i++) {
    const el = document.createElement('span')
    el.className = 'hh-p'
    const size = 4 + Math.random() * 6
    const color = colors[i % colors.length]
    const kind = i % 4 // bintang, lingkaran, diamond, garis tipis
    el.style.left = Math.random() * 100 + '%'
    el.style.width = size + 'px'
    el.style.height = size + 'px'
    if (kind === 0) {
      el.style.background = color
      el.style.clipPath = 'polygon(50% 0,61% 35%,98% 35%,68% 57%,79% 91%,50% 70%,21% 91%,32% 57%,2% 35%,39% 35%)'
      el.style.width = el.style.height = size + 3 + 'px'
    } else if (kind === 1) {
      el.style.background = color
      el.style.borderRadius = '50%'
    } else if (kind === 2) {
      el.style.background = color
      el.style.transform = 'rotate(45deg)'
    } else {
      el.style.background = 'linear-gradient(180deg, transparent, ' + color + ')'
      el.style.width = '2px'
      el.style.height = 10 + Math.random() * 10 + 'px'
      el.style.borderRadius = '2px'
    }
    const dur = 2 + Math.random() * 3
    const dl = Math.random() * 2.5
    el.style.setProperty('--dur', dur.toFixed(2) + 's')
    el.style.setProperty('--dl', dl.toFixed(2) + 's')
    el.style.setProperty('--dx', ((Math.random() - 0.5) * 120).toFixed(0) + 'px')
    el.style.setProperty('--rot', ((Math.random() - 0.5) * 720).toFixed(0) + 'deg')
    el.style.setProperty('--fall', fall + 'px')
    el.style.setProperty('--op', (0.55 + Math.random() * 0.4).toFixed(2))
    fx.appendChild(el)
    maxEnd = Math.max(maxEnd, dur + dl)
  }
  setTimeout(() => fx.remove(), (maxEnd + 0.5) * 1000)
}

/** FAQ: accordion halus berbasis <details>; tanpa JS semua jawaban tetap terbaca. */
function faqAccordion() {
  const section = document.getElementById('faq')
  if (!section) return
  section.classList.add('faq-js')
  const items = section.querySelectorAll('.faq-item')

  items.forEach((d) => {
    const summary = d.querySelector('summary')
    const body = d.querySelector('.faq-body')
    summary.addEventListener('click', (e) => {
      e.preventDefault()
      if (d.open && d.classList.contains('is-open')) {
        d.classList.remove('is-open')
        if (reduceMotion) { d.open = false; return }
        const done = (ev) => {
          if (ev && ev.propertyName !== 'grid-template-rows') return
          body.removeEventListener('transitionend', done)
          d.open = false
        }
        body.addEventListener('transitionend', done)
        setTimeout(() => { if (!d.classList.contains('is-open')) d.open = false }, 400)
      } else {
        d.open = true
        requestAnimationFrame(() => requestAnimationFrame(() => d.classList.add('is-open')))
      }
    })
  })

  if ('IntersectionObserver' in window && !reduceMotion) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) {
          e.target.classList.add('in')
          io.unobserve(e.target)
        }
      })
    }, { threshold: 0.15 })
    items.forEach((it) => io.observe(it))
  } else {
    items.forEach((it) => it.classList.add('in'))
  }
}

document.addEventListener('DOMContentLoaded', () => {
  heroParticles()
  revealOnScroll('cara-order', 'cr-js', '.cr-card')
  revealOnScroll('keunggulan', 'kw-js', '.kw-card')
  faqAccordion()
})

/**
 * Salin teks ke clipboard. Mengembalikan true bila berhasil. Tidak melempar galat:
 * bila izin clipboard ditolak, dicoba lewat textarea sementara.
 */
window.copyText = async function (text) {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch (e) {
    try {
      const area = document.createElement('textarea')
      area.value = text
      area.setAttribute('readonly', '')
      area.style.position = 'fixed'
      area.style.opacity = '0'
      document.body.appendChild(area)
      area.select()
      const done = document.execCommand('copy')
      area.remove()
      return done
    } catch (e2) {
      return false
    }
  }
}

// Widget chat (Tanya AI + WhatsApp): kode dimuat hanya bila layout menyetel konfigurasinya,
// yaitu saat dinyalakan di Pengaturan dan ADMIN_WHATSAPP_NUMBER terisi.
// Dibangun setelah halaman idle supaya tidak menghambat render awal.
if (window.PROAKSES_CHAT) {
  import('./chat-widget.js').then(({ mountChat }) => {
    const start = () => document.getElementById('pa-chat') || mountChat(window.PROAKSES_CHAT)
    const whenIdle = () => ('requestIdleCallback' in window ? requestIdleCallback(start, { timeout: 1500 }) : setTimeout(start, 300))
    if (document.readyState === 'complete') whenIdle()
    else window.addEventListener('load', whenIdle)
  })
}

window.Alpine = Alpine
Alpine.start()
