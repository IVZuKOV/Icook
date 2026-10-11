
// === АВТОМИГРАЦИЯ: обновляем данные пользователя при каждом заходе ===
(async function autoMigrate(){
    try {
        var res = await fetch('/api/profile.php', {credentials:'include'});
        var data = await res.json();
        if(data.success && data.user) {
            var stored = JSON.parse(localStorage.getItem('user')) || {};
            // Обновляем если есть расхождения
            if(stored.name !== data.user.name || !stored.is_seller) {
                stored.name = data.user.name;
                stored.email = data.user.email || stored.email;
                stored.is_seller = 1;
                localStorage.setItem('user', JSON.stringify(stored));
            }
        }
    } catch(e){}
})();

document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', function(e) {
  if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase())) || (e.ctrlKey && e.key.toLowerCase() === 'u')) {
    e.preventDefault(); e.stopPropagation(); return false;
  }
});
let cart=JSON.parse(localStorage.getItem('cart'))||[];
function updateCartBadge(){const c=cart.reduce((s,x)=>s+x.qty,0);const b=document.getElementById('cartBadge');if(b){b.textContent=c;b.style.display=c>0?'flex':'none';}}
function addToCart(id,n,c,p,i){const ex=cart.find(x=>x.id===id);if(ex)ex.qty++;else cart.push({id,name:n,chef:c,price:p,img:i,qty:1});localStorage.setItem('cart',JSON.stringify(cart));updateCartBadge();showToast(`✅ ${n} добавлен`);}
function toggleCatalog(){document.getElementById('catalogDrawer').classList.toggle('active');document.getElementById('catalogOverlay').classList.toggle('active');}
function updateCatalogVisibility() {
  const currentPath = window.location.pathname.replace(/\/+$/, '');
  const links = document.querySelectorAll('#catalogDrawer .catalog-link');
  links.forEach(link => {
    const href = link.getAttribute('href');
    if (!href || href === '#') return;
    const linkPath = href.replace(/\/+$/, '');
    link.style.display = currentPath === linkPath ? 'none' : '';
  });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', updateCatalogVisibility);
else updateCatalogVisibility();
function openAuth(){document.getElementById('authModal').classList.add('active');switchTab('login');setTimeout(()=>document.getElementById('loginName')?.focus(),100);}
function closeAuth(){document.getElementById('authModal').classList.remove('active');}
function switchTab(tab){document.getElementById('loginForm').style.display=tab==='login'?'block':'none';document.getElementById('registerForm').style.display=tab==='register'?'block':'none';document.getElementById('authTitle').textContent=tab==='login'?'Личный кабинет':'Создать аккаунт';}
function updateUI(){const u=JSON.parse(localStorage.getItem('user'));const b=document.getElementById('authBtnIcon');if(u){b.classList.add('logged');document.getElementById('userInfo').style.display='flex';document.getElementById('userName').textContent=u.name;}else{b.classList.remove('logged');document.getElementById('userInfo').style.display='none';}}
function showErr(id,msg){const el=document.getElementById(id);el.textContent=msg;el.classList.add('show');}
function clearErrors(){document.querySelectorAll('.error-msg').forEach(e=>{e.textContent='';e.classList.remove('show');});}
function setLoading(id, state){const b=document.getElementById(id);state?b.classList.add('loading'):b.classList.remove('loading');}
function showToast(msg){const t=document.createElement('div');t.textContent=msg;Object.assign(t.style,{position:'fixed',bottom:'24px',right:'24px',background:'var(--card-bg)',color:'var(--neon-dark)',padding:'14px 20px',borderRadius:'12px',border:'1px solid var(--border-color)',boxShadow:'0 10px 30px var(--neon-glow)',zIndex:'9999',fontWeight:'600',transition:'all 0.3s',transform:'translateY(20px)',opacity:'0'});document.body.appendChild(t);requestAnimationFrame(()=>{t.style.transform='translateY(0)';t.style.opacity='1';});setTimeout(()=>{t.style.transform='translateY(20px)';t.style.opacity='0';setTimeout(()=>t.remove(),300);},3000);}
async function login(){
const n = document.getElementById('loginName').value.trim();
const p = document.getElementById('loginPassword').value;
clearErrors();
if(n.length < 2) return showErr('loginNameError', 'Минимум 2 символа');
if(p.length < 8) return showErr('loginPasswordError', 'Минимум 8 символов');
setLoading('loginBtn', true);
try {
const res = await fetch('/webhook/92b13143-c5eb-4e1c-8c28-8ac1542f1d44', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ name: n, password: p }),
credentials: 'include'
});
const data = await res.json();
if (!res.ok) throw new Error(data.message || 'Ошибка входа');
localStorage.setItem('user', JSON.stringify(data.user));
closeAuth();
updateUI();
showToast('✅ Вход выполнен');
setTimeout(() => location.reload(), 800);
} catch(e) {
showErr('loginPasswordError', e.message);
} finally {
setLoading('loginBtn', false);
}
}
async function register(){const n=document.getElementById('regName').value.trim();const e=document.getElementById('regEmail').value.trim();const p=document.getElementById('regPassword').value;const c=document.getElementById('regConfirm').value;clearErrors();if(n.length<2)return showErr('regNameError','Минимум 2 символа');if(e&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e))return showErr('regEmailError','Некорректный email');if(p.length<8)return showErr('regPasswordError','Минимум 8 символов');if(p!==c)return showErr('regConfirmError','Пароли не совпадают');setLoading('registerBtn',true);try{const res=await fetch('/webhook/api/registr_user',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:n,email:e,password:p,phone:document.getElementById('regPhone')?document.getElementById('regPhone').value.trim():''}),credentials:'include'});const data=await res.json();if(!res.ok)throw new Error(data.message||'Ошибка регистрации');closeAuth();updateUI();showToast('✅ Аккаунт создан');}catch(err){showErr('regNameError',err.message);}finally{setLoading('registerBtn',false);}}
function toggleTheme(){const html = document.documentElement;const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';html.setAttribute('data-theme', next);localStorage.setItem('site-theme', next);updateQr();}
function updateQr(){const isDark = document.documentElement.getAttribute('data-theme') === 'dark';const fg = isDark ? 'ffffff' : '00b8a3';const bg = isDark ? '0f172a' : 'ffffff';const url = location.href;const qrPath = `/webhook/qr-gen?url=${encodeURIComponent(url)}&fg=${fg}&bg=${bg}`;document.getElementById('qrApp').src = qrPath;document.getElementById('qrHome').src = qrPath;}
function shareSite(){const u=location.href;if(navigator.share){navigator.share({title:'Я готовлю — ты ешь!',url:u}).catch(()=>{});}else{navigator.clipboard.writeText(u);showToast('📋 Ссылка скопирована');}}
window.onload = () => { updateCartBadge(); updateUI(); updateQr(); };