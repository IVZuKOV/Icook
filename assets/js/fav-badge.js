(function(){
  function favCount(){
    try{
      var a = JSON.parse(localStorage.getItem('favorites') || '[]');
      return Array.isArray(a) ? a.length : 0;
    }catch(e){ return 0; }
  }

  function ensureBadge(btn){
    var b = btn.querySelector('.fav-badge-count');
    if(!b){
      b = document.createElement('span');
      b.className = 'fav-badge-count';
      b.style.cssText = 'position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;'
        + 'font-size:9px;font-weight:bold;border-radius:50%;min-width:16px;height:16px;padding:0 4px;'
        + 'display:none;align-items:center;justify-content:center;pointer-events:none;line-height:1;';
      btn.appendChild(b);
      if(getComputedStyle(btn).position === 'static') btn.style.position = 'relative';
    }
    return b;
  }

  window.updateFavBadge = function(){
    var n = favCount();
    var btns = document.querySelectorAll(
      '.bottom-bar a[href="favorites.html"],'
      + '.header-right a[href="favorites.html"],'
      + 'a.icon-btn[aria-label="Избранное"]'
    );
    for(var i = 0; i < btns.length; i++){
      var b = ensureBadge(btns[i]);
      b.textContent = n > 99 ? '99+' : String(n);
      b.style.display = n > 0 ? 'flex' : 'none';
    }
  };

  function init(){ if(window.updateFavBadge) window.updateFavBadge(); }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  }else{
    init();
  }

  // обновление при изменении избранного в другой вкладке или по событию
  window.addEventListener('storage', function(e){
    if(e.key === 'favorites' && window.updateFavBadge) window.updateFavBadge();
  });
  document.addEventListener('favorites:changed', function(){
    if(window.updateFavBadge) window.updateFavBadge();
  });
})();
