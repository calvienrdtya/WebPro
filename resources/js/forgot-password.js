(function () {
  var card = document.getElementById("reset-card");
  if (!card) return;

  var URLS = { send: card.dataset.sendUrl, verify: card.dataset.verifyUrl, update: card.dataset.updateUrl };
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || "";
  var codeTtl = 300, resendWait = 30;
  var state = { step: "email", email: "", code: ["", "", "", "", "", ""], password: "", confirmPassword: "", loading: false, countdown: 0, resendCountdown: 0 };
  var timers = {};

  var $ = function (id) { return document.getElementById(id); };
  var emailForm = $("email-form"), codeForm = $("code-form"), passwordForm = $("password-form");
  var emailInput = $("email"), boxes = Array.prototype.slice.call(document.querySelectorAll(".code-box"));
  var pwInput = $("password"), confirmInput = $("confirm");
  var sendBtn = $("send-btn"), verifyBtn = $("verify-btn"), saveBtn = $("save-btn"), resendBtn = $("resend-btn");
  var last = boxes.length - 1;

  var HEADINGS = {
    email: ["Reset Password", "Masukkan email yang terdaftar, kami akan kirim kode verifikasi 6 digit."],
    code: ["Masukkan Kode", "Cek email kamu, lalu masukkan kode verifikasi 6 digit."],
    password: ["Password Baru", "Buat password baru yang kuat untuk akun kamu."]
  };

  function pad(n) { return String(n).padStart(2, "0"); }
  function fmt(s) { return pad(Math.floor(s / 60)) + ":" + pad(s % 60); }

  // ---------- Server ----------
  function post(url, body) {
    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": CSRF, "X-Requested-With": "XMLHttpRequest" },
      body: JSON.stringify(body)
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) { return { status: res.status, data: data }; });
    }).catch(function () { return { status: 0, data: {} }; });
  }
  function fieldError(r, field) { return r.data.errors && r.data.errors[field] && r.data.errors[field][0]; }
  function failMessage(r, fallback) {
    if (r.status === 0) return "Tidak bisa terhubung ke server. Periksa koneksi lalu coba lagi.";
    if (r.status === 419) return "Sesi halaman berakhir. Muat ulang halaman lalu coba lagi.";
    return r.data.message || fallback;
  }

  // ---------- UI helpers ----------
  function showError(id, msg) {
    var el = $(id);
    el.lastElementChild.textContent = msg;
    el.classList.remove("hidden"); el.classList.add("flex");
  }
  function clearError(id) {
    var el = $(id);
    el.classList.add("hidden"); el.classList.remove("flex");
    el.lastElementChild.textContent = "";
  }
  function markInput(el, status) {
    el.classList.remove("!border-[#DC2626]", "!border-[#10B981]", "!bg-[#FEF2F2]", "!bg-[#ECFDF5]");
    el.removeAttribute("aria-invalid");
    if (status === "bad") { el.classList.add("!border-[#DC2626]"); el.setAttribute("aria-invalid", "true"); }
    if (status === "ok") el.classList.add("!border-[#10B981]", "!bg-[#ECFDF5]");
  }
  function setLoading(btn, on) {
    state.loading = on;
    var label = btn.querySelector(".label");
    btn.querySelector(".spinner").classList.toggle("hidden", !on);
    label.textContent = on ? label.dataset.loading : label.dataset.idle;
    btn.disabled = on || (btn === verifyBtn && !codeComplete()) || (btn === saveBtn && !passwordValid());
    btn.setAttribute("aria-busy", String(on));
  }

  function render() {
    var h = HEADINGS[state.step === "success" ? "password" : state.step];
    $("title").textContent = h[0];
    $("subtitle").textContent = h[1];
    $("flow").hidden = state.step === "success";
    $("success").hidden = state.step !== "success";
    emailForm.hidden = state.step === "password";
    codeForm.hidden = state.step !== "code";
    passwordForm.hidden = state.step !== "password";
    if (state.step === "success") $("success").focus({ preventScroll: true });
  }
  function goTo(step) { state.step = step; render(); }

  // ---------- Timers ----------
  function startTimers() {
    clearInterval(timers.main);
    state.countdown = codeTtl; state.resendCountdown = resendWait;
    paintTimers();
    timers.main = setInterval(function () {
      if (state.countdown > 0) state.countdown--;
      if (state.resendCountdown > 0) state.resendCountdown--;
      paintTimers();
      if (state.countdown === 0 && state.resendCountdown === 0) clearInterval(timers.main);
    }, 1000);
  }
  function paintTimers() {
    var expired = state.countdown === 0;
    $("timer").hidden = expired;
    $("expired").hidden = !expired;
    $("timer-value").textContent = fmt(state.countdown);
    var canResend = state.resendCountdown === 0 || expired;
    resendBtn.disabled = !canResend || state.loading;
    $("resend-label").textContent = canResend ? "Kirim Ulang Kode" : "Kirim ulang dalam " + fmt(state.resendCountdown);
    if (expired) verifyBtn.disabled = true; else if (!state.loading) verifyBtn.disabled = !codeComplete();
  }

  // ---------- Step 1: email ----------
  function validEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
  emailInput.addEventListener("input", function () { clearError("email-error"); markInput(emailInput, null); });
  emailForm.addEventListener("submit", function (e) {
    e.preventDefault();
    if (state.loading) return;
    var v = emailInput.value.trim();
    if (!v) { markInput(emailInput, "bad"); return showError("email-error", "Email wajib diisi."); }
    if (!validEmail(v)) { markInput(emailInput, "bad"); return showError("email-error", "Format email tidak valid."); }
    state.email = v; sendCode(sendBtn, false);
  });
  function sendCode(btn, isResend) {
    setLoading(btn, true);
    if (isResend) resendBtn.disabled = true;
    post(URLS.send, { email: state.email }).then(function (r) {
      setLoading(btn, false);
      if (r.status === 200) {
        codeTtl = r.data.expires_in || 300; resendWait = typeof r.data.resend_in === "number" ? r.data.resend_in : 30;
        $("sent-email").textContent = state.email;
        emailInput.readOnly = true;
        resetCode();
        startTimers();
        if (!isResend) { sendBtn.closest("div").hidden = true; goTo("code"); }
        boxes[0].focus();
        return;
      }
      var msg = fieldError(r, "email") || failMessage(r, "Gagal mengirim kode. Coba lagi.");
      if (isResend) { showError("code-error", msg); paintTimers(); }
      else { markInput(emailInput, "bad"); showError("email-error", msg); }
    });
  }
  $("change-email-btn").addEventListener("click", function () {
    clearInterval(timers.main);
    resetCode();
    emailInput.readOnly = false;
    sendBtn.closest("div").hidden = false;
    clearError("email-error"); markInput(emailInput, null);
    goTo("email");
    emailInput.focus(); emailInput.select();
  });

  // ---------- Step 2: kode ----------
  function codeComplete() { return state.code.every(Boolean); }
  function resetCode() {
    state.code = boxes.map(function () { return ""; });
    boxes.forEach(function (b) { b.value = ""; paintBox(b); markInput(b, null); });
    clearError("code-error");
    verifyBtn.disabled = true;
  }
  function paintBox(b) {
    var filled = !!b.value;
    b.classList.toggle("!border-[#FCD34D]", filled);
    b.classList.toggle("!bg-[#FFFBEB]", filled);
  }
  function onCodeChange() {
    state.code = boxes.map(function (b) { return b.value; });
    boxes.forEach(function (b) { paintBox(b); markInput(b, null); });
    clearError("code-error");
    verifyBtn.disabled = !codeComplete() || state.countdown === 0;
  }
  boxes.forEach(function (box, i) {
    box.addEventListener("input", function () {
      box.value = box.value.replace(/\D/g, "").slice(-1);
      onCodeChange();
      if (box.value && i < last) boxes[i + 1].focus();
    });
    box.addEventListener("keydown", function (e) {
      if (e.key === "Backspace" && !box.value && i > 0) { boxes[i - 1].value = ""; boxes[i - 1].focus(); onCodeChange(); e.preventDefault(); }
      else if (e.key === "ArrowLeft" && i > 0) boxes[i - 1].focus();
      else if (e.key === "ArrowRight" && i < last) boxes[i + 1].focus();
    });
    box.addEventListener("focus", function () { box.select(); });
    box.addEventListener("paste", function (e) {
      var digits = (e.clipboardData.getData("text") || "").replace(/\D/g, "").slice(0, boxes.length);
      if (!digits) return;
      e.preventDefault();
      digits.split("").forEach(function (d, k) { boxes[k].value = d; });
      onCodeChange();
      boxes[Math.min(digits.length, last)].focus();
    });
  });
  codeForm.addEventListener("submit", function (e) {
    e.preventDefault();
    if (state.loading || !codeComplete() || state.countdown === 0) return;
    setLoading(verifyBtn, true);
    post(URLS.verify, { email: state.email, code: state.code.join("") }).then(function (r) {
      setLoading(verifyBtn, false);
      if (r.status === 200) {
        boxes.forEach(function (b) { markInput(b, "ok"); });
        clearInterval(timers.main);
        setTimeout(function () { goTo("password"); pwInput.focus(); }, 400);
        return;
      }
      boxes.forEach(function (b) { markInput(b, "bad"); });
      showError("code-error", fieldError(r, "code") || failMessage(r, "Kode salah. Coba lagi."));
      if (r.data.code === "locked") verifyBtn.disabled = true;
      boxes[last].focus();
    });
  });
  resendBtn.addEventListener("click", function () {
    if (resendBtn.disabled || state.loading) return;
    sendCode(sendBtn, true);
  });

  // ---------- Step 3: password ----------
  function strength(p) {
    var s = 0;
    if (p.length >= 8) s++;
    if (/[A-Za-z]/.test(p) && /\d/.test(p)) s++;
    if (p.length >= 12 || (/[A-Z]/.test(p) && /[^A-Za-z0-9]/.test(p))) s++;
    return p ? Math.max(s, 1) : 0;
  }
  function passwordError(p) {
    if (!p) return "Password wajib diisi.";
    if (p.length < 8) return "Password minimal 8 karakter.";
    if (!/[A-Za-z]/.test(p) || !/\d/.test(p)) return "Password harus mengandung huruf dan angka.";
    return "";
  }
  function passwordValid() { return !passwordError(state.password) && state.password === state.confirmPassword; }
  function syncPassword() {
    state.password = pwInput.value; state.confirmPassword = confirmInput.value;
    var s = strength(state.password), bar = $("strength-bar"), txt = $("strength-text");
    var meta = [["0%", "", ""], ["33%", "#DC2626", "Lemah"], ["66%", "#F59E0B", "Sedang"], ["100%", "#10B981", "Kuat"]][s];
    bar.style.width = meta[0]; bar.style.background = meta[1]; txt.textContent = meta[2]; txt.style.color = meta[1] || "#6B7280";
    $("password-hint").classList.toggle("hidden", !s); $("password-hint").classList.toggle("flex", !!s);
    saveBtn.disabled = state.loading || !passwordValid();
  }
  pwInput.addEventListener("input", function () {
    clearError("password-error"); markInput(pwInput, null); syncPassword();
    checkMatch();
  });
  function checkMatch() {
    clearError("confirm-error"); markInput(confirmInput, null);
    if (state.confirmPassword && state.confirmPassword !== state.password) {
      markInput(confirmInput, "bad"); showError("confirm-error", "Konfirmasi password tidak sama.");
    }
  }
  confirmInput.addEventListener("input", function () { syncPassword(); checkMatch(); });
  pwInput.addEventListener("blur", function () {
    var m = state.password && passwordError(state.password);
    if (m) { markInput(pwInput, "bad"); showError("password-error", m); }
  });
  confirmInput.addEventListener("blur", function () {
    if (state.confirmPassword && state.confirmPassword !== state.password) { markInput(confirmInput, "bad"); showError("confirm-error", "Konfirmasi password tidak sama."); }
  });
  document.querySelectorAll("[data-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = $(btn.dataset.toggle), hide = input.type === "text";
      input.type = hide ? "password" : "text";
      btn.setAttribute("aria-label", hide ? "Tampilkan password" : "Sembunyikan password");
      btn.querySelector(".eye-icon").innerHTML = hide
        ? '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z" /><circle cx="12" cy="12" r="3" />'
        : '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.3 21.3 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a21.3 21.3 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" /><line x1="1" y1="1" x2="23" y2="23" />';
    });
  });
  passwordForm.addEventListener("submit", function (e) {
    e.preventDefault();
    if (state.loading) return;
    var m = passwordError(state.password);
    if (m) { markInput(pwInput, "bad"); return showError("password-error", m); }
    if (state.password !== state.confirmPassword) { markInput(confirmInput, "bad"); return showError("confirm-error", "Konfirmasi password tidak sama."); }
    setLoading(saveBtn, true);
    post(URLS.update, { password: state.password, password_confirmation: state.confirmPassword }).then(function (r) {
      setLoading(saveBtn, false);
      if (r.status === 200) return goTo("success");
      markInput(pwInput, "bad");
      showError("password-error", fieldError(r, "password") || failMessage(r, "Gagal menyimpan password. Coba lagi."));
      if (r.data.code === "session_expired") setTimeout(function () { window.location.reload(); }, 2500);
    });
  });

  render();
})();
