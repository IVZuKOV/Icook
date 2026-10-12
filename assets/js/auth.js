function login() {
    const name = document.getElementById('loginName').value;
    if(name) {
        localStorage.setItem('user', JSON.stringify({ name: name }));
        closeAuth();
        updateUI();
        alert('Добро пожаловать, ' + name + '!');
    }
}

function updateUI() {
    const user = JSON.parse(localStorage.getItem('user'));
    const authBtn = document.getElementById('authBtnIcon');
    const nameBtn = document.getElementById('userNameBtn');
    if (user && user.name) {
        if (authBtn) authBtn.style.display = 'none';
        if (nameBtn) {
            nameBtn.classList.remove('expanded');
            nameBtn.style.display = 'flex';
            const letter = document.getElementById('userLetter');
            const marquee = document.getElementById('userMarquee');
            if (letter) letter.textContent = user.name.charAt(0).toUpperCase();
            if (marquee) marquee.textContent = user.name;
            setTimeout(function () { nameBtn.classList.add('expanded'); }, 5000);
        }
    } else {
        if (authBtn) authBtn.style.display = 'flex';
        if (nameBtn) { nameBtn.style.display = 'none'; nameBtn.classList.remove('expanded'); }
    }
}

// Проверка при загрузке любой страницы
document.addEventListener('DOMContentLoaded', updateUI);