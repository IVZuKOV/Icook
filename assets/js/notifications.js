(function(){
  function injectBell(){
    var bar = document.querySelector('.bottom-bar');
    if(!bar) return;
    var hr = bar.querySelector('.header-right');
    if(!hr || document.getElementById('notifBellBtn')) return;
    var btn = document.createElement('button');
    btn.className = 'icon-btn';
    btn.id = 'notifBellBtn';
    btn.setAttribute('aria-label','Уведомления');
    btn.style.position = 'relative';
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span id="notifBadgeMain" style="position:absolute;top:-4px;right:-4px;background:#e74c3c;color:#fff;font-size:9px;font-weight:bold;border-radius:50%;width:16px;height:16px;display:none;align-items:center;justify-content:center;">0</span>';
    btn.addEventListener('click', function(ev){ ev.stopPropagation(); toggleNotifPanel(); });
    var themeBtn = hr.querySelector('.theme-btn');
    if(themeBtn){ hr.insertBefore(btn, themeBtn); }
    else { hr.appendChild(btn); }
  }

  function ensurePanel(){
    if(document.getElementById('notifPanelMain')) return;
    var p = document.createElement('div');
    p.id = 'notifPanelMain';
    p.style.cssText = 'position:fixed;bottom:70px;right:16px;width:320px;max-width:calc(100vw-32px);background:var(--card-bg,#f8fafc);border:2px solid var(--border-color,rgba(30,64,175,0.12));border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,0.2);z-index:99999;display:none;max-height:400px;overflow:hidden;';
    p.innerHTML = '<div style="padding:10px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color,#ddd);font-weight:600;font-size:14px;"><span>🔔 Уведомления</span><span style="font-size:11px;color:var(--neon,#1e40af);cursor:pointer;text-decoration:underline;" onclick="markAllReadMain()">Прочитать все</span></div><div id="notifListMain" style="max-height:340px;overflow-y:auto;padding:8px 12px;"></div>';
    document.body.appendChild(p);
  }

  async function loadNotifMain(){
    var bell = document.getElementById('notifBellBtn');
    var user = null;
    try { user = JSON.parse(localStorage.getItem('user')||'null'); } catch(e){}
    if(bell) bell.style.display = (user && user.name) ? '' : 'none';
    if(!user) return;
    try {
      var res = await fetch('/api/notifications.php', {credentials:'include'});
      var data = await res.json();
      if(!data.success) return;
      var badge = document.getElementById('notifBadgeMain');
      if(data.unread_count > 0){ badge.style.display='flex'; badge.textContent = data.unread_count; }
      else { badge.style.display='none'; }
      var list = data.notifications || [];
      var box = document.getElementById('notifListMain');
      if(!box) return;
      if(!list.length){ box.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:20px;">Нет уведомлений</p>'; return; }
      var html = '';
      list.forEach(function(n){
        var unread = n.is_read == 0 ? 'background:rgba(30,64,175,0.06);' : '';
        var time = new Date(n.created_at).toLocaleString('ru-RU',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
        html += '<div style="padding:12px 16px;border-bottom:1px solid var(--border-color);cursor:pointer;'+unread+'" onclick="handleNotifClick('+n.id+')">';
        html += '<div style="font-weight:600;font-size:13px;margin-bottom:4px;">'+(n.title||'Уведомление')+'</div>';
        html += '<div style="font-size:12px;color:var(--text-muted);line-height:1.4;">'+(n.message||'')+'</div>';
        html += '<div style="font-size:10px;color:var(--text-muted);margin-top:4px;">'+time+'</div>';
        html += '</div>';
      });
      box.innerHTML = html;
    } catch(e){}
  }

  window.toggleNotifPanel = function(){
    ensurePanel();
    var p = document.getElementById('notifPanelMain');
    if(!p) return;
    var show = p.style.display !== 'block';
    p.style.display = show ? 'block' : 'none';
    if(show) loadNotifMain();
  };

  window.handleNotifClick = function(id){
    fetch('/api/mark_notification.php', {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({id:id})})
      .then(function(){ loadNotifMain(); }).catch(function(){});
  };

  window.markAllReadMain = function(){
    fetch('/api/mark_notification.php', {method:'POST', headers:{'Content-Type':'application/json'}, credentials:'include', body: JSON.stringify({mark_all:true})})
      .then(function(){ loadNotifMain(); }).catch(function(){});
  };

  document.addEventListener('click', function(e){
    var p = document.getElementById('notifPanelMain');
    var b = document.getElementById('notifBellBtn');
    if(p && p.style.display==='block' && b && !p.contains(e.target) && !b.contains(e.target)){
      p.style.display = 'none';
    }
  });

  function init(){
    injectBell();
    ensurePanel();
    loadNotifMain();
    setInterval(loadNotifMain, 30000);
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
