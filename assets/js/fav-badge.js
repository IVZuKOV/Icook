(function(){
  function getFavorites(){ try { return JSON.parse(localStorage.getItem('favorites')||'[]'); } catch(e){ return []; } }

  function injectFavBtn(){
    var bar = document.querySelector('.bottom-bar');
    if(!bar) return;
    var hr = bar.querySelector('.header-right');
    if(!hr || document.getElementById('favHeaderBtn')) return;
    var btn = document.createElement('a');
    btn.className = 'icon-btn';
    btn.id = 'favHeaderBtn';
    btn.href = 'favorites.html';
    btn.setAttribute('aria-label','Избранное');
    btn.style.position = 'relative';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg><span id="favHeaderBadge" style="position:absolute;top:-4px;right:-4px;background:#e60023;color:#fff;font-size:9px;font-weight:bold;border-radius:50%;width:16px;height:16px;display:none;align-items:center;justify-content:center;">0</span>';
    var themeBtn = hr.querySelector('.theme-btn');
    if(themeBtn){ hr.insertBefore(btn, themeBtn); }
    else { hr.appendChild(btn); }
    updateFavBadge();
  }

  function updateFavBadge(){
    var badge = document.getElementById('favHeaderBadge');
    if(!badge) return;
    var count = getFavorites().length;
    if(count > 0){ badge.style.display='flex'; badge.textContent = count; }
    else { badge.style.display='none'; }
  }

  window.updateFavBadge = updateFavBadge;

  window.addEventListener('storage', function(e){
    if(e.key === 'favorites') updateFavBadge();
  });

  function init(){
    injectFavBtn();
    updateFavBadge();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
