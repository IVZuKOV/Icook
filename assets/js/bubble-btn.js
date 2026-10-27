(function(){
  function animate(btn){
    if(!btn) return;
    btn.classList.remove('animate');
    void btn.offsetWidth;
    btn.classList.add('animate');
    setTimeout(function(){ btn.classList.remove('animate'); }, 700);
  }

  window.playBubblyBurst = function(btn, times, interval){
    times = times || 3;
    interval = interval || 650;
    var i = 0;
    animate(btn);
    var t = setInterval(function(){
      i++;
      animate(btn);
      if(i >= times - 1) clearInterval(t);
    }, interval);
  };

  function bind(btn){
    if(!btn || btn.dataset.bubblyBound) return;
    btn.dataset.bubblyBound = '1';
    btn.addEventListener('click', function(){ animate(btn); });
  }

  function init(){
    document.querySelectorAll('.bubbly-button').forEach(bind);
  }

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  setTimeout(init, 500);
  setTimeout(init, 1500);
})();
