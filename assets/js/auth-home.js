/* Общая оболочка авторизации; auth-email-flow.js использует сценарий главной. */
(function () {
    var modal = document.getElementById('authModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'authModal';
        document.body.appendChild(modal);
    }
    modal.className = 'modal';
    modal.setAttribute('data-home-auth', '');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'authTitle');
    modal.innerHTML = '<div class="modal-box"><button class="close" type="button" onclick="closeAuth()" aria-label="Закрыть">✕</button>' +
        '<h2 id="authTitle">Личный кабинет</h2>' +
        '<form id="loginForm" onsubmit="return false;"><input id="loginName" autocomplete="username"><input id="loginPassword" type="password" autocomplete="current-password"></form>' +
        '<form id="registerForm" style="display:none;" onsubmit="register();return false;">' +
        '<input id="regName" placeholder="Ваше имя *" minlength="2" autocomplete="username" required><div class="error-msg" id="regNameError"></div>' +
        '<input id="regPhone" placeholder="Номер телефона" type="tel" autocomplete="tel" required><div class="error-msg" id="regPhoneError"></div>' +
        '<input id="regEmail" placeholder="Email" type="email" autocomplete="email"><div class="error-msg" id="regEmailError"></div>' +
        '<input id="regPassword" type="password" placeholder="Пароль *" minlength="8" autocomplete="new-password" required><div class="error-msg" id="regPasswordError"></div>' +
        '<input id="regConfirm" type="password" placeholder="Повторите пароль *" autocomplete="new-password" required><div class="error-msg" id="regConfirmError"></div>' +
        '<button class="btn bubbly-button" id="registerBtn" type="submit">Зарегистрироваться</button>' +
        '<p class="auth-note">Уже есть аккаунт? <a href="#" onclick="switchTab(\'login\');return false;">Войти</a></p></form>' +
        '<form id="resetForm" style="display:none;" onsubmit="return false;"></form>' +
        '<form id="smsForm" style="display:none;" onsubmit="return false;"></form></div>';
    var previousFocus = null;
    window.showErr = function (id, message) {
        var node = document.getElementById(id);
        if (node) { node.textContent = message; node.classList.add('show'); }
    };
    window.clearErrors = function () {
        modal.querySelectorAll('.error-msg').forEach(function (node) { node.textContent = ''; node.classList.remove('show'); });
    };
    window.setLoading = function (id, loading) {
        var button = document.getElementById(id);
        if (button) { button.disabled = loading; button.classList.toggle('loading', loading); }
    };
    window.switchTab = function (tab) {
        try { sessionStorage.setItem('authTab', tab); } catch (e) {}
        ['login', 'register', 'reset', 'sms'].forEach(function (name) {
            document.getElementById(name + 'Form').style.display = name === tab ? 'block' : 'none';
        });
        document.getElementById('authTitle').textContent = tab === 'register' ? 'Создать аккаунт' : tab === 'login' ? 'Личный кабинет' : 'Восстановление доступа';
        if (tab === 'register' && typeof window.initPhoneCountry === 'function') {
            var phone = document.getElementById('regPhone');
            if (!phone.dataset.pcInit) window.initPhoneCountry(phone);
        }
    };
    window.openAuth = function (event) {
        if (event) event.preventDefault();
        previousFocus = document.activeElement;
        modal.classList.add('active');
        document.documentElement.classList.add('auth-home-open');
        try { sessionStorage.setItem('authOpen', '1'); sessionStorage.setItem('authPath', location.pathname); } catch (e) {}
        window.switchTab('login');
        return false;
    };
    window.closeAuth = function () {
        modal.classList.remove('active');
        document.documentElement.classList.remove('auth-home-open');
        try { sessionStorage.removeItem('authOpen'); } catch (e) {}
        if (previousFocus && previousFocus.isConnected) previousFocus.focus({preventScroll:true});
    };
    window.register = async function () {
        var name = document.getElementById('regName').value.trim();
        var email = document.getElementById('regEmail').value.trim();
        var phone = document.getElementById('regPhone').value.trim();
        var password = document.getElementById('regPassword').value;
        window.clearErrors();
        if (name.length < 2) return window.showErr('regNameError', 'Минимум 2 символа');
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return window.showErr('regEmailError', 'Некорректный email');
        if (!phone) return window.showErr('regPhoneError', 'Укажите телефон');
        if (password.length < 8) return window.showErr('regPasswordError', 'Минимум 8 символов');
        if (password !== document.getElementById('regConfirm').value) return window.showErr('regConfirmError', 'Пароли не совпадают');
        window.setLoading('registerBtn', true);
        try {
            var response = await fetch('/api/register.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({name: name, email: email, password: password, phone: phone})
            });
            var data = await response.json();
            if (!response.ok || data.error) throw new Error(data.error || 'Ошибка регистрации');
            localStorage.setItem('user', JSON.stringify({name: data.name, email: data.email}));
            if (typeof window.playBubblyBurst === 'function') window.playBubblyBurst(document.getElementById('registerBtn'), 3, 650);
            if (typeof window.updateUI === 'function') window.updateUI();
            if (typeof window.showToast === 'function') window.showToast('Добро пожаловать, ' + data.name + '!');
            setTimeout(function () { window.closeAuth(); location.reload(); }, 2000);
        } catch (error) {
            window.showErr('regNameError', error.message);
            window.setLoading('registerBtn', false);
        }
    };
    modal.addEventListener('click', function (event) { if (event.target === modal) window.closeAuth(); });
    document.addEventListener('keydown', function (event) {
        if (!modal.classList.contains('active')) return;
        if (event.key === 'Escape') { window.closeAuth(); return; }
        if (event.key !== 'Tab') return;
        var elements = Array.from(modal.querySelectorAll('a,button,input')).filter(function (el) { return !el.disabled && el.offsetParent !== null; });
        var first = elements[0], last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    document.addEventListener('DOMContentLoaded', function () {
        var query = new URLSearchParams(location.search).get('auth');
        var restore = false;
        try { restore = sessionStorage.getItem('authOpen') === '1' && sessionStorage.getItem('authPath') === location.pathname; } catch (e) {}
        if (!query && (!restore || modal.classList.contains('active'))) return;
        var tab = 'login';
        try { tab = sessionStorage.getItem('authTab') || 'login'; } catch (e) {}
        window.openAuth();
        if (query === 'register' || query === 'signup' || (!query && tab === 'register')) window.switchTab('register');
        if (!query && typeof window.afRestoreSms === 'function') window.afRestoreSms();
        if (query) history.replaceState(null, '', location.pathname + location.hash);
    });
})();
