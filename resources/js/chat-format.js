/*
 * Format ringan untuk jawaban AI di widget chat: **tebal**, *miring*, `kode`, judul (#), daftar berbutir dan
 * bernomor (baris menjorok ikut butir sebelumnya), tautan [teks](url) dan URL polos (hanya http/https), tabel
 * Markdown jadi baris teks biasa. Semua teks di-escape lebih dulu, jadi HTML dari AI tidak pernah dijalankan.
 */
var ESC = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };

function esc(s) {
  return s.replace(/[&<>"']/g, function (c) { return ESC[c]; });
}

function link(href, label) {
  return '<a href="' + href + '" target="_blank" rel="noopener noreferrer">' + label + "</a>";
}

/** Format di dalam satu baris; masukan sudah di-escape. */
function inline(s) {
  var codes = [];
  s = s.replace(/`([^`]+)`/g, function (_, c) { codes.push(c); return "\u0000" + (codes.length - 1) + "\u0000"; });
  s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, function (_, t, u) { return link(u, t); });
  s = s.replace(/(^|[\s(])(https?:\/\/[^\s<]*[^\s<.,;:!?)])/g, function (_, p, u) { return p + link(u, u); });
  s = s.replace(/\*\*(?=\S)([\s\S]*?\S)\*\*/g, "<strong>$1</strong>").replace(/__(?=\S)([\s\S]*?\S)__/g, "<strong>$1</strong>");
  s = s.replace(/(^|[^*\w])\*(?=\S)([^*]*?\S)\*(?!\*)/g, "$1<em>$2</em>");
  return s.replace(/\u0000(\d+)\u0000/g, function (_, i) { return "<code>" + codes[+i] + "</code>"; });
}

export function renderReply(text) {
  var lines = esc(String(text).replace(/\r\n?/g, "\n").trim()).split("\n");
  var out = [];
  var list = null;       // "ul" | "ol"
  var para = [];

  function flushPara() { if (para.length) { out.push("<p>" + para.map(inline).join("<br>") + "</p>"); para = []; } }
  function closeList() { if (list) { out.push("</li></" + list + ">"); list = null; } }

  lines.forEach(function (raw) {
    var line = raw.replace(/\s+$/, "");
    var m;

    if (line.trim() === "" ) { flushPara(); closeList(); return; }
    if (/^\s*([-*_])\s*(\1\s*){2,}$/.test(line)) { flushPara(); closeList(); return; }       // garis pemisah
    if (/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(line)) return;              // baris pemisah tabel

    if ((m = line.match(/^\s*#{1,6}\s+(.*)$/))) { flushPara(); closeList(); out.push('<p class="pa-h"><strong>' + inline(m[1].replace(/\*\*/g, "")) + "</strong></p>"); return; }

    var ul = line.match(/^\s{0,3}[-*•+]\s+(.*)$/);
    var ol = line.match(/^\s{0,3}(\d+)[.)]\s+(.*)$/);
    if (ul || ol) {
      flushPara();
      var kind = ul ? "ul" : "ol";
      if (list !== kind) {
        closeList();
        list = kind;
        out.push(ol && ol[1] !== "1" ? '<ol start="' + ol[1] + '"><li>' : "<" + kind + "><li>");
      } else {
        out.push("</li><li>");
      }
      out.push(inline(ul ? ul[1] : ol[2]));
      return;
    }

    if (/^\s*\|.*\|\s*$/.test(line)) {                                                         // baris tabel jadi teks
      line = line.trim().replace(/^\||\|$/g, "").split("|").map(function (c) { return c.trim(); }).filter(Boolean).join(" · ");
    }

    if (list && /^\s{2,}\S/.test(raw)) { out.push("<br>" + inline(line.trim())); return; }      // lanjutan butir
    closeList();
    para.push(line.trim());
  });

  flushPara();
  closeList();
  return out.join("");
}
