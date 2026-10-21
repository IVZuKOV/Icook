(function(){
  var footer = document.querySelector('footer');
  if(!footer) return;
  function updateFooter(){
    var scrollY = window.scrollY || window.pageYOffset;
    var winH = window.innerHeight || document.documentElement.clientHeight;
    var docH = document.documentElement.scrollHeight;
    if(scrollY + winH >= docH - 60 || docH <= winH + 100){
      footer.classList.add('footer-visible');
    } else {
      footer.classList.remove('footer-visible');
    }
  }
  window.addEventListener('scroll', updateFooter, {passive:true});
  window.addEventListener('resize', updateFooter);
  updateFooter();
})();
