(function () {
  const modal = document.getElementById('authModal');
  if (!modal) return;

  const nativeLogin = document.getElementById('loginForm');
  const nativeReset = document.getElementById('resetForm');
  const nativeRegister = document.getElementById('registerForm');
  const nativeSms = document.getElementById('smsForm');
  const titleEl = document.getElementById('authTitle');
  const container = nativeLogin ? nativeLogin.parentElement : modal.querySelector('.modal-box');
  if (!container) return;

  const STEPS = ['afLogin', 'afPassword', 'afRecovery', 'afEmailCode', 'afSms', 'afReset'];
  const inputStyle = 'width:100%;padding:12px 16px;border-radius:12px;border:2px solid var(--border-color);background:var(--bg);color:var(--text-main);font-size:15px;box-sizing:border-box;';
  const primaryStyle = 'width:100%;margin-top:8px;';
  const linkStyle = 'color:var(--neon-dark);font-weight:600;';

  const wrap = document.createElement('div');
  wrap.id = 'afWrap';
  wrap.style.display = 'none';
  wrap.innerHTML = `
    <form id="afLogin" onsubmit="return false;">
      <div style="text-align:center;margin-bottom:10px;font-size:14px;color:var(--text-muted);">Войдите по логину, телефону или email</div>
      <input id="afIdentifier" placeholder="Логин, телефон или email" autocomplete="username" style="${inputStyle}">
      <div class="error-msg" id="afLoginError"></div>
      <button type="button" class="btn" style="${primaryStyle}" id="afLoginBtn" onclick="afLookup()">Продолжить</button>
      <p style="text-align:center;margin-top:12px;font-size:14px;"><a href="#" onclick="afRecoveryOpen();return false;" style="${linkStyle}">Напомнить логин/пароль</a></p>
      <p style="text-align:center;margin-top:8px;font-size:14px;color:var(--text-muted);">Нет аккаунта? <a href="#" onclick="afRegister();return false;" style="${linkStyle}">Создать</a></p>
    </form>

    <form id="afPassword" style="display:none;" onsubmit="return false;">
      <div id="afGreeting" style="text-align:center;margin-bottom:12px;font-size:15px;font-weight:600;"></div>
      <input id="afPasswordInput" type="password" placeholder="Пароль" autocomplete="current-password" style="${inputStyle}">
      <div class="error-msg" id="afPasswordError"></div>
      <button type="button" class="btn" style="${primaryStyle}" id="afPasswordBtn" onclick="afLogin()">Войти</button>
      <p style="text-align:center;margin-top:12px;font-size:14px;">
        <a href="#" onclick="afBackToLogin();return false;" style="color:var(--text-muted);">Назад</a>
        <span style="color:var(--text-muted);"> · </span>
        <a href="#" onclick="afRecoveryOpen();return false;" style="${linkStyle}">Напомнить логин/пароль</a>
      </p>
    </form>

    <form id="afRecovery" style="display:none;" onsubmit="return false;">
      <div style="text-align:center;margin-bottom:10px;font-size:14px;color:var(--text-muted);">Введите телефон или email, указанный при регистрации</div>
      <input id="afRecoveryInput" placeholder="Телефон или email" autocomplete="username" style="${inputStyle}">
      <div class="error-msg" id="afRecoveryError"></div>
      <button type="button" class="btn" style="${primaryStyle}" id="afRecoveryBtn" onclick="afRecoverySubmit()">Продолжить</button>
      <p style="text-align:center;margin-top:12px;font-size:14px;"><a href="#" onclick="afBackToLogin();return false;" style="color:var(--text-muted);">Назад ко входу</a></p>
    </form>

    <form id="afEmailCode" style="display:none;" onsubmit="return false;">
      <div style="text-align:center;margin-bottom:10px;font-size:14px;color:var(--text-muted);">Код отправлен на <span id="afSentTo"></span></div>
      <input id="afCode" placeholder="Введите код" maxlength="4" inputmode="numeric" pattern="[0-9]{4}" style="${inputStyle}font-size:22px;text-align:center;letter-spacing:12px;">
      <div class="error-msg" id="afCodeError"></div>
      <button type="button" class="btn" style="${primaryStyle}" id="afCodeBtn" onclick="afVerifyCode()">Подтвердить</button>
      <p style="text-align:center;margin-top:12px;font-size:14px;"><a href="#" onclick="afRecoveryOpen();return false;" style="color:var(--text-muted);">Назад</a></p>
    </form>

    <form id="afSms" style="display:none;" onsubmit="return false;">
      <p style="text-align:center;margin-top:4px;font-size:14px;color:var(--text-muted);">Отправьте этот код с телефона <span id="afSmsPhone"></span> на номер:</p>
      <p style="text-align:center;margin:4px 0 2px;font-size:20px;font-weight:800;letter-spacing:.04em;" id="afSmsDestination">—</p>
      <p style="text-align:center;margin:2px 0 6px;font-size:34px;font-weight:800;color:var(--neon-dark);letter-spacing:.14em;" id="afSmsCode">————</p>
      <p style="text-align:center;font-size:13px;color:var(--text-muted);">Ждём ваше SMS… <span id="afSmsTimer"></span></p>
      <div class="error-msg" id="afSmsError"></div>
      <p style="text-align:center;margin-top:14px;font-size:14px;"><a href="#" onclick="afBackToLogin();return false;" style="color:var(--text-muted);">Отмена</a></p>
      <div id="afSmsFallback" style="display:none;margin-top:14px;padding:12px 14px;border:1px dashed var(--border-color);border-radius:12px;background:var(--neon-glow);">
        <p style="margin:0 0 10px;font-size:12.5px;line-height:1.45;color:var(--text-muted);text-align:center;">Если SMS не открывается автоматически, скопируйте номер и код и отправьте сообщение вручную.</p>
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
          <button type="button" class="af-copy-btn" id="afCopyNumber">Скопировать номер</button>
          <button type="button" class="af-copy-btn" id="afCopyCode">Скопировать код</button>
        </div>
      </div>
      <div style="display:flex;justify-content:center;margin-top:12px;">
        <a class="btn" id="afSmsSend" href="#" role="button">Отправить</a>
      </div>
    </form>

    <form id="afReset" style="display:none;" onsubmit="return false;">
      <div id="afReminder" style="text-align:center;margin-bottom:10px;font-size:15px;font-weight:600;"></div>
      <div style="text-align:center;margin-bottom:10px;font-size:14px;color:var(--text-muted);">Придумайте новый пароль</div>
      <input id="afNewPass" type="password" placeholder="Новый пароль" autocomplete="new-password" style="${inputStyle}margin-bottom:8px;">
      <input id="afNewPass2" type="password" placeholder="Повторите пароль" autocomplete="new-password" style="${inputStyle}">
      <div class="error-msg" id="afResetError"></div>
      <button type="button" class="btn" style="${primaryStyle}" id="afResetBtn" onclick="afResetSubmit()">Сохранить пароль</button>
      <p style="text-align:center;margin-top:12px;font-size:14px;"><a href="#" onclick="afBackToLogin();return false;" style="color:var(--text-muted);">Назад ко входу</a></p>
    </form>
  `;
  const smsBtnStyle = document.createElement('style');
  smsBtnStyle.textContent = '#afSmsSend{display:inline-flex;border-color:var(--neon)!important;background:var(--neon)!important;color:#fff!important;font-weight:800;box-shadow:0 2px 15px var(--neon-glow);text-decoration:none;cursor:pointer;}#afSmsSend:hover{transform:none;opacity:.92;}#afSmsSend[aria-disabled="true"]{opacity:.6;pointer-events:none;}[data-theme="dark"] #afSmsSend{color:#0f172a!important;}.af-copy-btn{padding:8px 12px;border:1px solid var(--border-color);border-radius:10px;background:transparent;color:var(--text-main);font:inherit;font-size:12.5px;font-weight:700;cursor:pointer;transition:border-color .2s ease,color .2s ease;}.af-copy-btn:hover,.af-copy-btn.is-copied{border-color:var(--neon);color:var(--neon-dark);}';
  document.head.appendChild(smsBtnStyle);

  container.insertBefore(wrap, nativeLogin || container.firstChild);
  const afSmsLink = el('afSmsSend');
  if (afSmsLink) {
    afSmsLink.addEventListener('click', function (e) {
      afUpdateSmsLink();
      const href = afSmsHref();
      if (!href) { e.preventDefault(); return; }
      if (afBridgeOpen(href)) e.preventDefault();
      afMarkSmsTried();
      smsLeftPage = false;
      setTimeout(function () {
        if (!smsLeftPage && document.visibilityState === 'visible') afShowSmsFallback();
      }, 1500);
    });
  }
  if (el('afCopyNumber')) {
    el('afCopyNumber').addEventListener('click', function () {
      afCopyField(this, state.smsDestination || (el('afSmsDestination') ? el('afSmsDestination').textContent : ''));
    });
  }
  if (el('afCopyCode')) {
    el('afCopyCode').addEventListener('click', function () {
      afCopyField(this, el('afSmsCode') ? el('afSmsCode').textContent : '');
    });
  }
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      smsLeftPage = true;
      afHideSmsFallback();
      afClearSmsTried();
    }
  });

  const SMS_KEY = 'authSms';
  let pendingSmsRestore = null;
  try {
    if (sessionStorage.getItem('authOpen') === '1' && sessionStorage.getItem('authPath') === location.pathname) {
      const raw = sessionStorage.getItem(SMS_KEY);
      if (raw) {
        const d = JSON.parse(raw);
        if (d && d.ui === 'index' && d.challenge && d.code) pendingSmsRestore = d;
      }
    }
  } catch (e) {}
  const state = { identifier: '', kind: '', email: '', token: '', challenge: '', mode: '', smsDestination: '', smsExpiresAt: 0 };
  let smsPoll = null;
  let smsTick = null;
  let smsDeadline = 0;
  let smsLeftPage = false;

  function el(id) { return document.getElementById(id); }
  function showErr(id, msg) {
    if (typeof window.showErr === 'function') { window.showErr(id, msg); return; }
    const e = el(id);
    if (e) { e.textContent = msg; e.classList.add('show'); }
  }
  function clearErrs() {
    if (typeof window.clearErrors === 'function') { window.clearErrors(); return; }
    document.querySelectorAll('.error-msg').forEach(function (e) { e.textContent = ''; e.classList.remove('show'); });
  }
  function hideNative() {
    [nativeLogin, nativeReset, nativeSms].forEach(function (f) { if (f) f.style.display = 'none'; });
  }
  function showStep(id) {
    hideNative();
    wrap.style.display = 'block';
    STEPS.forEach(function (s) { const e = el(s); if (e) e.style.display = 'none'; });
    const target = el(id);
    if (target) target.style.display = 'block';
    if (id === 'afSms') {
      afUpdateSmsLink();
      afHideSmsFallback();
      smsLeftPage = false;
      if (afIsInApp() && !afBridgeAvailable() && afSmsWasTried()) afShowSmsFallback();
    }
    clearErrs();
  }
  function hideWrap() { wrap.style.display = 'none'; }
  function setTitle(text) { if (titleEl) titleEl.textContent = text; }

  function stopSms() {
    if (smsPoll) { clearInterval(smsPoll); smsPoll = null; }
    if (smsTick) { clearInterval(smsTick); smsTick = null; }
  }

  function resetState() {
    stopSms();
    state.identifier = ''; state.kind = ''; state.email = '';
    state.token = ''; state.challenge = ''; state.mode = ''; state.smsDestination = ''; state.smsExpiresAt = 0;
    clearPersistedSms();
    afClearSmsTried();
    ['afIdentifier', 'afPasswordInput', 'afRecoveryInput', 'afCode', 'afNewPass', 'afNewPass2'].forEach(function (id) {
      const e = el(id); if (e) e.value = '';
    });
    ['afGreeting', 'afReminder'].forEach(function (id) {
      const e = el(id); if (e) e.textContent = '';
    });
    clearErrs();
  }

  function finish(name, email) {
    localStorage.setItem('user', JSON.stringify({ name: name, email: email || '' }));
    hideWrap();
    if (typeof window.closeAuth === 'function') window.closeAuth();
    if (typeof window.updateUI === 'function') window.updateUI();
    if (typeof window.showToast === 'function') window.showToast('Вход выполнен');
    setTimeout(function () { location.reload(); }, 700);
  }

  function showLogin() {
    resetState();
    showStep('afLogin');
    setTitle('Личный кабинет');
    setTimeout(function () { if (el('afIdentifier')) el('afIdentifier').focus(); }, 80);
  }

  function showRecovery() {
    resetState();
    showStep('afRecovery');
    setTitle('Восстановление доступа');
    setTimeout(function () { if (el('afRecoveryInput')) el('afRecoveryInput').focus(); }, 80);
  }

  function showReset(name) {
    state.mode = state.mode || 'email';
    el('afReminder').textContent = name ? ('Ваш логин: ' + name) : '';
    el('afNewPass').value = '';
    el('afNewPass2').value = '';
    showStep('afReset');
    setTitle('Новый пароль');
    setTimeout(function () { if (el('afNewPass')) el('afNewPass').focus(); }, 80);
  }

  function smsIsIOS() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function afMarkSmsTried() {
    try { sessionStorage.setItem('authSmsTried', '1'); } catch (e) {}
  }

  function afSmsWasTried() {
    try { return sessionStorage.getItem('authSmsTried') === '1'; } catch (e) { return false; }
  }

  function afClearSmsTried() {
    try { sessionStorage.removeItem('authSmsTried'); } catch (e) {}
  }

  function afIsInApp() {
    const ua = navigator.userAgent || '';
    if (/; wv\)/i.test(ua)) return true;
    if (/Android/i.test(ua) && /Version\/\d+\.\d+/i.test(ua) && /Chrome\//i.test(ua)) return true;
    if (/Cordova/i.test(ua)) return true;
    if (window.ReactNativeWebView || window.flutter_inappwebview) return true;
    if (/iPhone|iPad|iPod/i.test(ua) && !/Safari\//i.test(ua)) return true;
    return false;
  }

  function afBridgeAvailable() {
    return !!((window.cordova && window.cordova.InAppBrowser) ||
      (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.AppLauncher));
  }

  function afBridgeOpen(href) {
    try {
      if (window.cordova && window.cordova.InAppBrowser && typeof window.cordova.InAppBrowser.open === 'function') {
        window.cordova.InAppBrowser.open(href, '_system');
        return true;
      }
      if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.AppLauncher &&
          typeof window.Capacitor.Plugins.AppLauncher.openUrl === 'function') {
        const p = window.Capacitor.Plugins.AppLauncher.openUrl({ url: href });
        if (p && typeof p.catch === 'function') p.catch(function () {});
        return true;
      }
    } catch (e) {}
    return false;
  }

  function afLegacyCopy(text) {
    return new Promise(function (resolve, reject) {
      try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        ta.remove();
        ok ? resolve() : reject(new Error('copy failed'));
      } catch (e) { reject(e); }
    });
  }

  function afCopyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text).catch(function () { return afLegacyCopy(text); });
    }
    return afLegacyCopy(text);
  }

  function afCopyField(btn, text) {
    text = String(text || '').trim();
    if (!text) return;
    afCopyText(text).then(function () {
      const prev = btn.getAttribute('data-label') || btn.textContent;
      btn.setAttribute('data-label', prev);
      btn.textContent = 'Скопировано';
      btn.classList.add('is-copied');
      setTimeout(function () {
        btn.textContent = prev;
        btn.classList.remove('is-copied');
      }, 1600);
    }).catch(function () {});
  }

  function afShowSmsFallback() {
    const f = el('afSmsFallback');
    if (f) f.style.display = 'block';
  }

  function afHideSmsFallback() {
    const f = el('afSmsFallback');
    if (f) f.style.display = 'none';
  }

  function afSmsHref() {
    const num = String(state.smsDestination || '').replace(/[^\d+]/g, '');
    const code = (el('afSmsCode') ? el('afSmsCode').textContent : '').trim();
    if (num.replace(/\D/g, '').length < 10 || !/^[0-9]{4}$/.test(code)) return '';
    return 'sms:' + num + (smsIsIOS() ? '&' : '?') + 'body=' + encodeURIComponent(code);
  }

  function afUpdateSmsLink() {
    const a = el('afSmsSend');
    if (!a) return;
    const href = afSmsHref();
    a.setAttribute('href', href || '#');
    if (href) a.removeAttribute('aria-disabled');
    else a.setAttribute('aria-disabled', 'true');
  }

  function persistSms() {
    if (state.mode !== 'sms' || !state.challenge) return;
    try {
      sessionStorage.setItem(SMS_KEY, JSON.stringify({
        ui: 'index',
        challenge: state.challenge,
        destination: state.smsDestination || '',
        destinationDisplay: el('afSmsDestination') ? el('afSmsDestination').textContent : '',
        code: el('afSmsCode') ? el('afSmsCode').textContent : '',
        phone: el('afSmsPhone') ? el('afSmsPhone').textContent : '',
        identifier: state.identifier || '',
        kind: state.kind || '',
        expiresAt: state.smsExpiresAt || 0
      }));
    } catch (e) {}
  }

  function clearPersistedSms() {
    try { sessionStorage.removeItem(SMS_KEY); } catch (e) {}
  }

  window.afRestoreSms = function () {
    const d = pendingSmsRestore;
    pendingSmsRestore = null;
    if (!d || !/^[0-9]{4}$/.test(String(d.code || '').trim())) return;
    state.mode = 'sms';
    state.challenge = d.challenge;
    state.smsDestination = d.destination || '';
    state.identifier = d.identifier || '';
    state.kind = d.kind || '';
    state.smsExpiresAt = d.expiresAt || 0;
    el('afSmsCode').textContent = d.code;
    el('afSmsDestination').textContent = d.destinationDisplay || d.destination || '—';
    el('afSmsPhone').textContent = d.phone || '';
    const m = document.getElementById('authModal');
    if (m) m.classList.add('active');
    showStep('afSms');
    setTitle('Подтверждение по SMS');
    afUpdateSmsLink();
    persistSms();
    const left = d.expiresAt ? Math.max(0, Math.round((d.expiresAt - Date.now()) / 1000)) : 600;
    startSmsPoll(left);
    if (left <= 0) showErr('afSmsError', 'Код истёк. Начните заново.');
  };

  window.afBackToLogin = function () { showLogin(); };
  window.afRecoveryOpen = function () { showRecovery(); };
  window.afRegister = function () {
    if (typeof window.switchTab === 'function') window.switchTab('register');
  };

  window.afLookup = async function () {
    const v = el('afIdentifier').value.trim();
    if (!v) return showErr('afLoginError', 'Введите логин, телефон или email');
    const btn = el('afLoginBtn');
    btn.disabled = true; btn.textContent = 'Проверка...';
    try {
      const res = await fetch('/api/identifier_lookup.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: v })
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      state.identifier = v;
      state.kind = data.kind;
      el('afGreeting').textContent = data.kind === 'login'
        ? ('👋 Привет, ' + data.greeting + '! Введи пароль от аккаунта')
        : '👋 Привет! Введи пароль от аккаунта';
      el('afPasswordInput').value = '';
      showStep('afPassword');
      setTimeout(function () { if (el('afPasswordInput')) el('afPasswordInput').focus(); }, 50);
    } catch (e) {
      showErr('afLoginError', e.message);
    } finally {
      btn.disabled = false; btn.textContent = 'Продолжить';
    }
  };

  window.afLogin = async function () {
    const p = el('afPasswordInput').value;
    if (!p) return showErr('afPasswordError', 'Введите пароль');
    const btn = el('afPasswordBtn');
    btn.disabled = true; btn.textContent = 'Вход...';
    try {
      const res = await fetch('/api/identifier_login.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: state.identifier, password: p })
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      finish(data.name, data.email);
    } catch (e) {
      showErr('afPasswordError', e.message);
    } finally {
      btn.disabled = false; btn.textContent = 'Войти';
    }
  };

  window.afRecoverySubmit = async function () {
    const v = el('afRecoveryInput').value.trim();
    const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    const isPhone = /^\+?[0-9\s\-()]{7,20}$/.test(v);
    if (!isEmail && !isPhone) return showErr('afRecoveryError', 'Введите email или телефон');
    const btn = el('afRecoveryBtn');
    btn.disabled = true; btn.textContent = 'Отправка...';
    try {
      const res = await fetch('/api/recovery_start.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: v })
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      if (data.channel === 'email') {
        state.mode = 'email';
        state.email = v.toLowerCase();
        state.challenge = '';
        el('afSentTo').textContent = data.email_masked || v;
        el('afCode').value = '';
        showStep('afEmailCode');
        setTimeout(function () { if (el('afCode')) el('afCode').focus(); }, 50);
      } else {
        state.mode = 'sms';
        state.challenge = data.challenge;
        state.smsDestination = data.destination || data.destination_display || '';
        state.smsExpiresAt = Date.now() + Number(data.expires_in || 600) * 1000;
        afClearSmsTried();
        el('afSmsCode').textContent = data.code;
        el('afSmsDestination').textContent = data.destination_display || data.destination || '—';
        el('afSmsPhone').textContent = data.phone_masked || '';
        afUpdateSmsLink();
        showStep('afSms');
        persistSms();
        startSmsPoll(data.expires_in);
      }
    } catch (e) {
      showErr('afRecoveryError', e.message);
    } finally {
      btn.disabled = false; btn.textContent = 'Продолжить';
    }
  };

  window.afVerifyCode = async function () {
    const code = el('afCode').value.trim();
    if (!/^[0-9]{4}$/.test(code)) return showErr('afCodeError', 'Введите 4-значный код');
    const btn = el('afCodeBtn');
    btn.disabled = true; btn.textContent = 'Проверка...';
    try {
      const res = await fetch('/api/recovery_verify.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: state.email, code: code })
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      state.mode = 'email';
      state.token = data.token;
      showReset(data.name);
    } catch (e) {
      showErr('afCodeError', e.message);
    } finally {
      btn.disabled = false; btn.textContent = 'Подтвердить';
    }
  };

  window.afResetSubmit = async function () {
    const p1 = el('afNewPass').value;
    const p2 = el('afNewPass2').value;
    if (p1.length < 8) return showErr('afResetError', 'Пароль минимум 8 символов');
    if (p1 !== p2) return showErr('afResetError', 'Пароли не совпадают');
    const btn = el('afResetBtn');
    btn.disabled = true; btn.textContent = 'Сохранение...';
    const url = state.mode === 'sms' ? '/api/recovery_sms_complete.php' : '/api/recovery_complete.php';
    const body = state.mode === 'sms'
      ? { challenge: state.challenge, password: p1, confirm: p2 }
      : { token: state.token, password: p1, confirm: p2 };
    try {
      const res = await fetch(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      finish(data.name, data.email);
    } catch (e) {
      showErr('afResetError', e.message);
    } finally {
      btn.disabled = false; btn.textContent = 'Сохранить пароль';
    }
  };

  function startSmsPoll(expiresIn) {
    stopSms();
    const seconds = Number(expiresIn || 600);
    smsDeadline = Date.now() + seconds * 1000;
    smsPoll = setInterval(checkSms, 3000);
    smsTick = setInterval(function () {
      const left = Math.max(0, Math.round((smsDeadline - Date.now()) / 1000));
      const t = el('afSmsTimer');
      if (t) t.textContent = left > 0 ? ('осталось ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')) : '';
      if (left <= 0) {
        stopSms();
        clearPersistedSms();
        showErr('afSmsError', 'Код истёк. Начните заново.');
      }
    }, 1000);
  }

  async function checkSms() {
    if (!state.challenge) return;
    try {
      const res = await fetch('/api/sms_status.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ challenge: state.challenge })
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      if (data.status === 'pending') return;
      stopSms();
      if (data.status === 'verified') {
        if (!data.name || data.mode === 'register') {
          showErr('afSmsError', 'Аккаунт с таким телефоном не найден');
          return;
        }
        clearPersistedSms();
        state.mode = 'sms';
        showReset(data.name);
      } else {
        clearPersistedSms();
        showErr('afSmsError', 'Код истёк. Начните заново.');
      }
    } catch (e) {
      stopSms();
      showErr('afSmsError', e.message);
    }
  }

  // Новый сценарий становится основным экраном входа.
  const origSwitchTab = window.switchTab;
  window.switchTab = function (tab) {
    if (typeof origSwitchTab === 'function') origSwitchTab(tab);
    if (tab === 'login') {
      showLogin();
    } else if (tab === 'reset' || tab === 'sms') {
      showRecovery();
    } else {
      stopSms();
      hideWrap();
    }
  };

  // Старые ссылки «Забыли пароль?» ведут в новый сценарий восстановления.
  document.querySelectorAll('a[onclick*="switchTab(\'reset\')"]').forEach(function (a) {
    a.setAttribute('onclick', 'afRecoveryOpen();return false;');
  });

  if (typeof window.closeAuth === 'function') {
    const origCloseAuth = window.closeAuth;
    window.closeAuth = function () {
      clearPersistedSms();
      return origCloseAuth.apply(this, arguments);
    };
  }

  // На всякий случай прячем нативные формы до первого открытия.
  [nativeLogin, nativeReset, nativeSms].forEach(function (f) { if (f) f.style.display = 'none'; });
})();
