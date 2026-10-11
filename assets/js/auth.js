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
    const btn = document.getElementById('authBtnIcon');
    const info = document.getElementById('userInfo');
    const nameSpan = document.getElementById('userName');
    
    if (user && btn) {
        btn.classList.add('logged');
        if(info) info.style.display = 'flex';
        if(nameSpan) nameSpan.textContent = user.name;
    }
}

// Проверка при загрузке любой страницы
document.addEventListener('DOMContentLoaded', updateUI);