/* Единое модальное окно авторизации для всех страниц.
   Не переходит на главную: окно открывается поверх текущей страницы.
   Логика: вход по логину/телефону/email, восстановление (email-код и обратное SMS),
   регистрация с выбором страны и кнопкой с пузырьками. */
(function () {
  'use strict';
  if (window.__authUnified) return;
  window.__authUnified = true;

  var IS_IKS = /ikspirit/i.test(location.hostname);
  var FONTS = IS_IKS
    ? {
        url: 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap',
        heading: "'Manrope',system-ui,-apple-system,sans-serif",
        body: "'Inter',system-ui,-apple-system,sans-serif"
      }
    : {
        url: 'https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800;900&family=Nunito:wght@400;500;600;700;800&display=swap',
        heading: "'Rubik',system-ui,-apple-system,sans-serif",
        body: "'Nunito',system-ui,-apple-system,sans-serif"
      };

  var root = null;
  var state = { identifier: '', kind: '', email: '', token: '', challenge: '', mode: '', smsDestination: '', smsExpiresAt: 0 };
  var smsPoll = null;
  var smsTick = null;
  var smsDeadline = 0;
  var smsLeftPage = false;

  function el(id) { return document.getElementById(id); }

  function ensureFonts() {
    if (document.querySelector('link[data-au-fonts]')) return;
    var l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = FONTS.url;
    l.setAttribute('data-au-fonts', '1');
    document.head.appendChild(l);
  }

  function ensureStyles() {
    if (document.getElementById('auStyles')) return;
    var l = document.createElement('link');
    l.id = 'auStyles';
    l.rel = 'stylesheet';
    l.href = '/assets/css/auth-unified.css?v=20261026123443';
    document.head.appendChild(l);
  }

  var MARKUP = '' +
    '<div class="au-modal" id="authUnified" role="dialog" aria-modal="true" aria-label="Вход или регистрация">' +
      '<div class="au-box">' +
        '<button type="button" class="au-close" data-au-close aria-label="Закрыть">✕</button>' +
        '<h2 class="au-title" id="auTitle">Личный кабинет</h2>' +

        '<form id="auLogin" novalidate>' +
          '<div class="au-hint">Войдите по логину, телефону или email</div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auIdentifier" placeholder="Логин, телефон или email" autocomplete="username">' +
            '<div class="au-error" id="auLoginError"></div>' +
          '</div>' +
          '<button type="submit" class="au-btn" id="auLoginBtn">Продолжить</button>' +
          '<p class="au-links"><a href="#" id="auRecoveryLink">Напомнить логин/пароль</a></p>' +
          '<p class="au-links">Нет аккаунта? <a href="#" id="auRegisterLink">Создать</a></p>' +
        '</form>' +

        '<form id="auPassword" hidden novalidate>' +
          '<div class="au-greeting" id="auGreeting"></div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auPasswordInput" type="password" placeholder="Пароль" autocomplete="current-password">' +
            '<div class="au-error" id="auPasswordError"></div>' +
          '</div>' +
          '<button type="submit" class="au-btn" id="auPasswordBtn">Войти</button>' +
          '<p class="au-links"><a href="#" data-au-login>Назад</a> · <a href="#" id="auRecoveryLink2">Напомнить логин/пароль</a></p>' +
        '</form>' +

        '<form id="auRecovery" hidden novalidate>' +
          '<div class="au-hint">Введите телефон или email, указанный при регистрации</div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auRecoveryInput" placeholder="Телефон или email" autocomplete="username">' +
            '<div class="au-error" id="auRecoveryError"></div>' +
          '</div>' +
          '<button type="submit" class="au-btn" id="auRecoveryBtn">Продолжить</button>' +
          '<p class="au-links"><a href="#" data-au-login>Назад ко входу</a></p>' +
        '</form>' +

        '<form id="auEmailCode" hidden novalidate>' +
          '<div class="au-hint">Код отправлен на <span id="auSentTo"></span></div>' +
          '<div class="au-field">' +
            '<input class="au-input au-code" id="auCode" placeholder="Введите код" maxlength="4" inputmode="numeric" autocomplete="one-time-code">' +
            '<div class="au-error" id="auCodeError"></div>' +
          '</div>' +
          '<button type="submit" class="au-btn" id="auCodeBtn">Подтвердить</button>' +
          '<p class="au-links"><a href="#" id="auCodeBack">Назад</a></p>' +
        '</form>' +

        '<form id="auSms" hidden novalidate>' +
          '<p class="au-hint" style="margin-top:0;">Отправьте этот код с телефона <span id="auSmsPhone"></span> на номер:</p>' +
          '<p class="au-destination" id="auSmsDestination">—</p>' +
          '<p class="au-bigcode" id="auSmsCode">————</p>' +
          '<p class="au-small">Ждём ваше SMS… <span id="auSmsTimer"></span></p>' +
          '<div class="au-error" id="auSmsError" style="text-align:center;"></div>' +
          '<p class="au-links"><a href="#" data-au-login>Отмена</a></p>' +
          '<div class="au-sms-fallback" id="auSmsFallback" hidden>' +
            '<p class="au-sms-fallback-text">Если SMS не открывается автоматически, скопируйте номер и код и отправьте сообщение вручную.</p>' +
            '<div class="au-sms-fallback-actions">' +
              '<button type="button" class="au-copy-btn" id="auCopyNumber">Скопировать номер</button>' +
              '<button type="button" class="au-copy-btn" id="auCopyCode">Скопировать код</button>' +
            '</div>' +
          '</div>' +
          '<div class="au-sms-send-wrap"><a class="au-btn au-sms-send" id="auSmsSend" href="#" role="button">Отправить</a></div>' +
        '</form>' +

        '<form id="auReset" hidden novalidate>' +
          '<div class="au-greeting" id="auReminder"></div>' +
          '<div class="au-hint" style="margin-top:-6px;">Придумайте новый пароль</div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auNewPass" type="password" placeholder="Новый пароль" autocomplete="new-password">' +
            '<div class="au-error" id="auNewPassError"></div>' +
          '</div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auNewPass2" type="password" placeholder="Повторите пароль" autocomplete="new-password">' +
            '<div class="au-error" id="auResetError"></div>' +
          '</div>' +
          '<button type="submit" class="au-btn" id="auResetBtn">Сохранить пароль</button>' +
          '<p class="au-links"><a href="#" data-au-login>Назад ко входу</a></p>' +
        '</form>' +

        '<form id="auRegister" hidden novalidate>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auRegName" placeholder="Ваше имя *" minlength="2" autocomplete="name">' +
            '<div class="au-error" id="auRegNameError"></div>' +
          '</div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auRegPhone" placeholder="Номер телефона" type="tel" autocomplete="tel">' +
            '<div class="au-error" id="auRegPhoneError"></div>' +
          '</div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auRegEmail" placeholder="Email" type="email" autocomplete="email">' +
            '<div class="au-error" id="auRegEmailError"></div>' +
          '</div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auRegPassword" type="password" placeholder="Пароль *" minlength="8" autocomplete="new-password">' +
            '<div class="au-error" id="auRegPasswordError"></div>' +
          '</div>' +
          '<div class="au-field">' +
            '<input class="au-input" id="auRegConfirm" type="password" placeholder="Повторите пароль *" autocomplete="new-password">' +
            '<div class="au-error" id="auRegConfirmError"></div>' +
          '</div>' +
          '<button type="submit" class="bubbly-button" id="auRegBtn">Зарегистрироваться</button>' +
          '<p class="au-links">Уже есть аккаунт? <a href="#" data-au-login>Войти</a></p>' +
        '</form>' +
      '</div>' +
    '</div>';

  function ensureModal() {
    if (root) return root;
    ensureFonts();
    var host = document.createElement('div');
    host.innerHTML = MARKUP;
    root = host.firstChild;
    root.style.setProperty('--au-heading-font', FONTS.heading);
    root.style.setProperty('--au-body-font', FONTS.body);
    document.body.appendChild(root);
    bindEvents(root);
    return root;
  }

  function showErr(id, msg) {
    var e = el(id);
    if (e) { e.textContent = msg || ''; e.classList.toggle('show', !!msg); }
  }
  function clearErrs() {
    root.querySelectorAll('.au-error').forEach(function (e) { e.textContent = ''; e.classList.remove('show'); });
  }
  function setTitle(text) { var t = el('auTitle'); if (t) t.textContent = text; }

  var STEPS = ['auLogin', 'auPassword', 'auRecovery', 'auEmailCode', 'auSms', 'auReset', 'auRegister'];
  function showStep(id) {
    clearErrs();
    STEPS.forEach(function (s) {
      var e = el(s);
      if (e) e.hidden = (s !== id);
    });
    var target = el(id);
    if (!target) return;
    if (id === 'auSms') {
      updateSmsLink();
      hideSmsFallback();
      smsLeftPage = false;
      if (isLikelyInApp() && !smsBridgeAvailable() && smsWasTried()) showSmsFallback();
    }
    var focusMap = {
      auLogin: 'auIdentifier', auPassword: 'auPasswordInput', auRecovery: 'auRecoveryInput',
      auEmailCode: 'auCode', auReset: 'auNewPass', auRegister: 'auRegName'
    };
    var fid = focusMap[id];
    if (fid) setTimeout(function () { var f = el(fid); if (f) f.focus(); }, 60);
  }

  function stopSms() {
    if (smsPoll) { clearInterval(smsPoll); smsPoll = null; }
    if (smsTick) { clearInterval(smsTick); smsTick = null; }
  }

  function resetState() {
    stopSms();
    state.identifier = ''; state.kind = ''; state.email = '';
    state.token = ''; state.challenge = ''; state.mode = ''; state.smsDestination = ''; state.smsExpiresAt = 0;
    clearPersistedSms();
    clearSmsTried();
    ['auIdentifier', 'auPasswordInput', 'auRecoveryInput', 'auCode', 'auNewPass', 'auNewPass2',
     'auRegName', 'auRegPhone', 'auRegEmail', 'auRegPassword', 'auRegConfirm'].forEach(function (id) {
      var e = el(id); if (e && e.tagName === 'INPUT') e.value = '';
    });
    ['auGreeting', 'auReminder'].forEach(function (id) { var e = el(id); if (e) e.textContent = ''; });
    clearErrs();
  }

  function showLogin() {
    stopSms();
    showStep('auLogin');
    setTitle('Личный кабинет');
  }

  function showRecovery() {
    stopSms();
    showStep('auRecovery');
    setTitle('Восстановление доступа');
    var r = el('auRecoveryInput');
    if (r) r.value = state.identifier || '';
  }

  function showReset(name) {
    el('auReminder').textContent = name ? ('Ваш логин: ' + name) : '';
    el('auNewPass').value = '';
    el('auNewPass2').value = '';
    showStep('auReset');
    setTitle('Новый пароль');
  }

  function showRegister() {
    stopSms();
    showStep('auRegister');
    setTitle('Создать аккаунт');
    var p = el('auRegPhone');
    if (p && typeof window.initPhoneCountry === 'function' && !p.dataset.pcInit) {
      window.initPhoneCountry(p);
    }
  }

  function finish(name, email, delay) {
    try {
      localStorage.setItem('user', JSON.stringify({ name: name, email: email || '' }));
    } catch (e) {}
    if (typeof window.updateUI === 'function') { try { window.updateUI(); } catch (e) {} }
    if (typeof window.showToast === 'function') { try { window.showToast('Вход выполнен'); } catch (e) {} }
    setTimeout(function () {
      closeAuth();
      try { location.reload(); } catch (e) {}
    }, typeof delay === 'number' ? delay : 500);
  }

  function buttonBusy(btn, busy, busyText, idleText) {
    if (!btn) return;
    btn.disabled = busy;
    btn.textContent = busy ? busyText : idleText;
  }

  function bindEvents(scope) {
    scope.querySelector('[data-au-close]').addEventListener('click', function () { closeAuth(); });
    scope.addEventListener('click', function (e) {
      if (e.target === scope) closeAuth();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && root && root.classList.contains('active')) closeAuth();
    });

    scope.querySelectorAll('[data-au-login]').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); resetState(); showLogin(); });
    });
    el('auRecoveryLink').addEventListener('click', function (e) { e.preventDefault(); showRecovery(); });
    el('auRecoveryLink2').addEventListener('click', function (e) { e.preventDefault(); showRecovery(); });
    el('auRegisterLink').addEventListener('click', function (e) { e.preventDefault(); showRegister(); });
    el('auCodeBack').addEventListener('click', function (e) { e.preventDefault(); showRecovery(); });

    el('auLogin').addEventListener('submit', function (e) { e.preventDefault(); lookup(); });
    el('auPassword').addEventListener('submit', function (e) { e.preventDefault(); submitLogin(); });
    el('auRecovery').addEventListener('submit', function (e) { e.preventDefault(); submitRecovery(); });
    el('auEmailCode').addEventListener('submit', function (e) { e.preventDefault(); submitCode(); });
    el('auReset').addEventListener('submit', function (e) { e.preventDefault(); submitReset(); });
    el('auRegister').addEventListener('submit', function (e) { e.preventDefault(); submitRegister(); });
    el('auSmsSend').addEventListener('click', function (e) {
      updateSmsLink();
      var href = smsHref();
      if (!href) { e.preventDefault(); return; }
      if (smsBridgeOpen(href)) e.preventDefault();
      markSmsTried();
      smsLeftPage = false;
      setTimeout(function () {
        if (!smsLeftPage && document.visibilityState === 'visible') showSmsFallback();
      }, 1500);
    });
    el('auCopyNumber').addEventListener('click', function () {
      var d = el('auSmsDestination');
      copySmsField(this, state.smsDestination || (d ? d.textContent : ''));
    });
    el('auCopyCode').addEventListener('click', function () {
      var c = el('auSmsCode');
      copySmsField(this, c ? c.textContent : '');
    });
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        smsLeftPage = true;
        hideSmsFallback();
        clearSmsTried();
      }
    });

    var code = el('auCode');
    code.addEventListener('input', function () { code.value = code.value.replace(/\D/g, '').slice(0, 4); });
  }

  async function lookup() {
    var v = el('auIdentifier').value.trim();
    if (!v) return showErr('auLoginError', 'Введите логин, телефон или email');
    var btn = el('auLoginBtn');
    buttonBusy(btn, true, 'Проверка...');
    try {
      var res = await fetch('/api/identifier_lookup.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: v })
      });
      var data = await res.json();
      if (data.error) throw new Error(data.error);
      state.identifier = v;
      state.kind = data.kind;
      el('auGreeting').textContent = data.kind === 'login'
        ? ('👋 Привет, ' + data.greeting + '! Введи пароль от аккаунта')
        : '👋 Привет! Введи пароль от аккаунта';
      el('auPasswordInput').value = '';
      showStep('auPassword');
      setTitle('Вход');
    } catch (err) {
      showErr('auLoginError', err.message);
    } finally {
      buttonBusy(btn, false, '', 'Продолжить');
    }
  }

  async function submitLogin() {
    var p = el('auPasswordInput').value;
    if (!p) return showErr('auPasswordError', 'Введите пароль');
    var btn = el('auPasswordBtn');
    buttonBusy(btn, true, 'Вход...');
    try {
      var res = await fetch('/api/identifier_login.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: state.identifier, password: p })
      });
      var data = await res.json();
      if (data.error) throw new Error(data.error);
      finish(data.name, data.email, 500);
    } catch (err) {
      showErr('auPasswordError', err.message);
    } finally {
      buttonBusy(btn, false, '', 'Войти');
    }
  }

  async function submitRecovery() {
    var v = el('auRecoveryInput').value.trim();
    var isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    var isPhone = /^\+?[0-9\s\-()]{7,20}$/.test(v);
    if (!isEmail && !isPhone) return showErr('auRecoveryError', 'Введите email или телефон');
    var btn = el('auRecoveryBtn');
    buttonBusy(btn, true, 'Отправка...');
    try {
      var res = await fetch('/api/recovery_start.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: v })
      });
      var data = await res.json();
      if (data.error) throw new Error(data.error);
      if (data.channel === 'email') {
        state.mode = 'email';
        state.email = v.toLowerCase();
        state.challenge = '';
        el('auSentTo').textContent = data.email_masked || v;
        el('auCode').value = '';
        showStep('auEmailCode');
        setTitle('Проверка email');
      } else {
        state.mode = 'sms';
        state.challenge = data.challenge;
        state.smsDestination = data.destination || data.destination_display || '';
        state.smsExpiresAt = Date.now() + Number(data.expires_in || 600) * 1000;
        clearSmsTried();
        el('auSmsCode').textContent = data.code;
        el('auSmsDestination').textContent = data.destination_display || data.destination || '—';
        el('auSmsPhone').textContent = data.phone_masked || '';
        updateSmsLink();
        showStep('auSms');
        setTitle('Подтверждение по SMS');
        persistSms();
        startSmsPoll(data.expires_in);
      }
    } catch (err) {
      showErr('auRecoveryError', err.message);
    } finally {
      buttonBusy(btn, false, '', 'Продолжить');
    }
  }

  async function submitCode() {
    var code = el('auCode').value.trim();
    if (!/^[0-9]{4}$/.test(code)) return showErr('auCodeError', 'Введите 4-значный код');
    var btn = el('auCodeBtn');
    buttonBusy(btn, true, 'Проверка...');
    try {
      var res = await fetch('/api/recovery_verify.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: state.email, code: code })
      });
      var data = await res.json();
      if (data.error) throw new Error(data.error);
      state.mode = 'email';
      state.token = data.token;
      showReset(data.name);
    } catch (err) {
      showErr('auCodeError', err.message);
    } finally {
      buttonBusy(btn, false, '', 'Подтвердить');
    }
  }

  async function submitReset() {
    var p1 = el('auNewPass').value;
    var p2 = el('auNewPass2').value;
    if (p1.length < 8) return showErr('auNewPassError', 'Пароль минимум 8 символов');
    if (p1 !== p2) return showErr('auResetError', 'Пароли не совпадают');
    var btn = el('auResetBtn');
    buttonBusy(btn, true, 'Сохранение...');
    var url = state.mode === 'sms' ? '/api/recovery_sms_complete.php' : '/api/recovery_complete.php';
    var body = state.mode === 'sms'
      ? { challenge: state.challenge, password: p1, confirm: p2 }
      : { token: state.token, password: p1, confirm: p2 };
    try {
      var res = await fetch(url, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      var data = await res.json();
      if (data.error) throw new Error(data.error);
      finish(data.name, data.email, 500);
    } catch (err) {
      showErr('auResetError', err.message);
    } finally {
      buttonBusy(btn, false, '', 'Сохранить пароль');
    }
  }

  async function submitRegister() {
    var n = el('auRegName').value.trim();
    var phone = el('auRegPhone').value.trim();
    var email = el('auRegEmail').value.trim();
    var p = el('auRegPassword').value;
    var c = el('auRegConfirm').value;
    clearErrs();
    if (n.length < 2) return showErr('auRegNameError', 'Минимум 2 символа');
    if (!phone || phone.replace(/\D/g, '').length < 10) return showErr('auRegPhoneError', 'Укажите телефон');
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showErr('auRegEmailError', 'Некорректный email');
    if (p.length < 8) return showErr('auRegPasswordError', 'Минимум 8 символов');
    if (p !== c) return showErr('auRegConfirmError', 'Пароли не совпадают');

    var btn = el('auRegBtn');
    btn.disabled = true;
    btn.style.pointerEvents = 'none';
    btn.textContent = 'Регистрация...';
    try {
      var res = await fetch('/api/register.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: n, email: email, password: p, phone: phone })
      });
      var data = await res.json();
      if (data.error) throw new Error(data.error);
      try { localStorage.setItem('user', JSON.stringify({ name: data.name, email: data.email })); } catch (e) {}
      if (typeof window.updateUI === 'function') { try { window.updateUI(); } catch (e) {} }
      if (typeof window.showToast === 'function') { try { window.showToast('Добро пожаловать, ' + data.name + '!'); } catch (e) {} }
      btn.textContent = 'Готово!';
      if (typeof window.playBubblyBurst === 'function') {
        window.playBubblyBurst(btn, 3, 650);
      } else {
        btn.classList.add('animate');
        setTimeout(function () { btn.classList.remove('animate'); }, 700);
      }
      setTimeout(function () {
        closeAuth();
        try { location.reload(); } catch (e) {}
      }, 2000);
    } catch (err) {
      btn.disabled = false;
      btn.style.pointerEvents = '';
      btn.textContent = 'Зарегистрироваться';
      showErr('auRegNameError', err.message);
    }
  }

  var SMS_KEY = 'authSms';

  function isIOS() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function markSmsTried() {
    try { sessionStorage.setItem('authSmsTried', '1'); } catch (e) {}
  }

  function smsWasTried() {
    try { return sessionStorage.getItem('authSmsTried') === '1'; } catch (e) { return false; }
  }

  function clearSmsTried() {
    try { sessionStorage.removeItem('authSmsTried'); } catch (e) {}
  }

  function isLikelyInApp() {
    var ua = navigator.userAgent || '';
    if (/; wv\)/i.test(ua)) return true;
    if (/Android/i.test(ua) && /Version\/\d+\.\d+/i.test(ua) && /Chrome\//i.test(ua)) return true;
    if (/Cordova/i.test(ua)) return true;
    if (window.ReactNativeWebView || window.flutter_inappwebview) return true;
    if (/iPhone|iPad|iPod/i.test(ua) && !/Safari\//i.test(ua)) return true;
    return false;
  }

  function smsBridgeAvailable() {
    return !!((window.cordova && window.cordova.InAppBrowser) ||
      (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.AppLauncher));
  }

  function smsBridgeOpen(href) {
    try {
      if (window.cordova && window.cordova.InAppBrowser && typeof window.cordova.InAppBrowser.open === 'function') {
        window.cordova.InAppBrowser.open(href, '_system');
        return true;
      }
      if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.AppLauncher &&
          typeof window.Capacitor.Plugins.AppLauncher.openUrl === 'function') {
        var p = window.Capacitor.Plugins.AppLauncher.openUrl({ url: href });
        if (p && typeof p.catch === 'function') p.catch(function () {});
        return true;
      }
    } catch (err) {}
    return false;
  }

  function legacyCopy(text) {
    return new Promise(function (resolve, reject) {
      try {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        var ok = document.execCommand('copy');
        ta.remove();
        ok ? resolve() : reject(new Error('copy failed'));
      } catch (err) { reject(err); }
    });
  }

  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text).catch(function () { return legacyCopy(text); });
    }
    return legacyCopy(text);
  }

  function copySmsField(btn, text) {
    text = String(text || '').trim();
    if (!text) return;
    copyText(text).then(function () {
      var prev = btn.getAttribute('data-label') || btn.textContent;
      btn.setAttribute('data-label', prev);
      btn.textContent = 'Скопировано';
      btn.classList.add('is-copied');
      setTimeout(function () {
        btn.textContent = prev;
        btn.classList.remove('is-copied');
      }, 1600);
    }).catch(function () {});
  }

  function showSmsFallback() {
    var f = el('auSmsFallback');
    if (f) f.hidden = false;
  }

  function hideSmsFallback() {
    var f = el('auSmsFallback');
    if (f) f.hidden = true;
  }

  function smsHref() {
    var num = String(state.smsDestination || '').replace(/[^\d+]/g, '');
    var code = (el('auSmsCode') ? el('auSmsCode').textContent : '').trim();
    if (num.replace(/\D/g, '').length < 10 || !/^[0-9]{4}$/.test(code)) return '';
    return 'sms:' + num + (isIOS() ? '&' : '?') + 'body=' + encodeURIComponent(code);
  }

  function updateSmsLink() {
    var a = el('auSmsSend');
    if (!a) return;
    var href = smsHref();
    a.setAttribute('href', href || '#');
    if (href) a.removeAttribute('aria-disabled');
    else a.setAttribute('aria-disabled', 'true');
  }

  function persistSms() {
    if (state.mode !== 'sms' || !state.challenge) return;
    try {
      sessionStorage.setItem(SMS_KEY, JSON.stringify({
        ui: 'unified',
        challenge: state.challenge,
        destination: state.smsDestination || '',
        destinationDisplay: el('auSmsDestination') ? el('auSmsDestination').textContent : '',
        code: el('auSmsCode') ? el('auSmsCode').textContent : '',
        phone: el('auSmsPhone') ? el('auSmsPhone').textContent : '',
        identifier: state.identifier || '',
        kind: state.kind || '',
        expiresAt: state.smsExpiresAt || 0
      }));
    } catch (err) {}
  }

  function clearPersistedSms() {
    try { sessionStorage.removeItem(SMS_KEY); } catch (err) {}
  }

  function readPersistedSms() {
    try {
      var raw = sessionStorage.getItem(SMS_KEY);
      if (!raw) return null;
      var d = JSON.parse(raw);
      if (!d || d.ui !== 'unified' || !d.challenge || !d.code) return null;
      return d;
    } catch (err) { return null; }
  }

  function restoreSms(d) {
    state.mode = 'sms';
    state.challenge = d.challenge;
    state.smsDestination = d.destination || '';
    state.identifier = d.identifier || state.identifier;
    state.kind = d.kind || state.kind;
    state.smsExpiresAt = d.expiresAt || 0;
    el('auSmsCode').textContent = d.code;
    el('auSmsDestination').textContent = d.destinationDisplay || d.destination || '—';
    el('auSmsPhone').textContent = d.phone || '';
    showStep('auSms');
    setTitle('Подтверждение по SMS');
    updateSmsLink();
    persistSms();
    var left = d.expiresAt ? Math.max(0, Math.round((d.expiresAt - Date.now()) / 1000)) : 600;
    if (left <= 0) showErr('auSmsError', 'Код истёк. Начните заново.');
    startSmsPoll(left);
  }

  function startSmsPoll(expiresIn) {
    stopSms();
    var seconds = Number(expiresIn || 600);
    smsDeadline = Date.now() + seconds * 1000;
    smsPoll = setInterval(checkSms, 3000);
    smsTick = setInterval(function () {
      var left = Math.max(0, Math.round((smsDeadline - Date.now()) / 1000));
      var t = el('auSmsTimer');
      if (t) t.textContent = left > 0 ? ('осталось ' + Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')) : '';
      if (left <= 0) {
        stopSms();
        clearPersistedSms();
        showErr('auSmsError', 'Код истёк. Начните заново.');
      }
    }, 1000);
  }

  async function checkSms() {
    if (!state.challenge) return;
    try {
      var res = await fetch('/api/sms_status.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ challenge: state.challenge })
      });
      var data = await res.json();
      if (data.error) throw new Error(data.error);
      if (data.status === 'pending') return;
      stopSms();
      if (data.status === 'verified') {
        if (!data.name || data.mode === 'register') {
          showErr('auSmsError', 'Аккаунт с таким телефоном не найден');
          return;
        }
        clearPersistedSms();
        state.mode = 'sms';
        showReset(data.name);
      } else {
        clearPersistedSms();
        showErr('auSmsError', 'Код истёк. Начните заново.');
      }
    } catch (err) {
      stopSms();
      showErr('auSmsError', err.message);
    }
  }

  // Если пользователь не авторизован, ссылки на профиль открывают наше окно,
  // не уводя со страницы (профиль доступен только после входа).
  function bindProfileGuard() {
    document.addEventListener('click', function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
      if (!a) return;
      var href = (a.getAttribute('href') || '').split('?')[0].split('#')[0];
      if (!/(^|\/)profile\.html$/.test(href)) return;
      var user = null;
      try { user = JSON.parse(localStorage.getItem('user') || 'null'); } catch (err) {}
      if (user && user.name) return;
      e.preventDefault();
      window.openAuth();
    }, false);
  }

  window.openAuth = function (e) {
    if (e && e.preventDefault) e.preventDefault();
    ensureStyles();
    ensureModal();
    root.classList.add('active');
    document.documentElement.style.overflow = 'hidden';
    try {
      sessionStorage.setItem('authOpen', '1');
      sessionStorage.setItem('authPath', location.pathname);
    } catch (err) {}
    resetState();
    showLogin();
    return false;
  };

  window.closeAuth = function () {
    stopSms();
    clearPersistedSms();
    if (root) root.classList.remove('active');
    document.documentElement.style.overflow = '';
    try { sessionStorage.removeItem('authOpen'); } catch (err) {}
  };

  // Ссылки со старых страниц вида index.html?auth=login всё ещё работают.
  function handleAuthQuery() {
    var q;
    try { q = new URLSearchParams(location.search); } catch (e) { return; }
    var a = q.get('auth');
    if (!a) return;
    if (a === 'register' || a === 'signup') {
      window.openAuth();
      showRegister();
    } else {
      window.openAuth();
    }
    try { history.replaceState(null, '', location.pathname + location.hash); } catch (e) {}
  }

  function restoreIfNeeded() {
    var open = false;
    try {
      open = sessionStorage.getItem('authOpen') === '1' &&
             sessionStorage.getItem('authPath') === location.pathname;
    } catch (e) {}
    if (!open) return;
    var sms = readPersistedSms();
    window.openAuth();
    if (sms) restoreSms(sms);
  }

  function boot() {
    ensureStyles();
    bindProfileGuard();
    handleAuthQuery();
    if (!root || !root.classList.contains('active')) restoreIfNeeded();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
