const LANGS = [
  {code:'ru', label:"🇷🇺 Русский"},
  {code:'en', label:"🇬🇧 English"},
  {code:'de', label:"🇩🇪 Deutsch"},
  {code:'fr', label:"🇫🇷 Français"},
  {code:'es', label:"🇪🇸 Español"},
  {code:'pt', label:"🇵🇹 Português"},
  {code:'it', label:"🇮🇹 Italiano"},
  {code:'nl', label:"🇳🇱 Nederlands"},
  {code:'pl', label:"🇵🇱 Polski"},
  {code:'cs', label:"🇨🇿 Čeština"},
  {code:'sk', label:"🇸🇰 Slovenčina"},
  {code:'hu', label:"🇭🇺 Magyar"},
  {code:'ro', label:"🇷🇴 Română"},
  {code:'bg', label:"🇧🇬 Български"},
  {code:'sr', label:"🇷🇸 Српски"},
  {code:'hr', label:"🇭🇷 Hrvatski"},
  {code:'sl', label:"🇸🇮 Slovenščina"},
  {code:'uk', label:"🇺🇦 Українська"},
  {code:'be', label:"🇧🇾 Беларуская"},
  {code:'ka', label:"🇬🇪 ქართული"},
  {code:'hy', label:"🇦🇲 Հայերեն"},
  {code:'az', label:"🇦🇿 Azərbaycan"},
  {code:'kk', label:"🇰🇿 Қазақша"},
  {code:'uz', label:"🇺🇿 Oʻzbekcha"},
  {code:'tg', label:"🇹🇯 Тоҷикӣ"},
  {code:'ky', label:"🇰🇬 Кыргызча"},
  {code:'tk', label:"🇹🇲 Türkmençe"},
  {code:'mn', label:"🇲🇳 Монгол"},
  {code:'zh', label:"🇨🇳 中文"},
  {code:'ja', label:"🇯🇵 日本語"},
  {code:'ko', label:"🇰🇷 한국어"},
  {code:'vi', label:"🇻🇳 Tiếng Việt"},
  {code:'th', label:"🇹🇭 ไทย"},
  {code:'id', label:"🇮🇩 Bahasa Indonesia"},
  {code:'ms', label:"🇲🇾 Bahasa Melayu"},
  {code:'tl', label:"🇵🇭 Filipino"},
  {code:'hi', label:"🇮🇳 हिन्दी"},
  {code:'bn', label:"🇧🇩 বাংলা"},
  {code:'ta', label:"🇱🇰 தமிழ்"},
  {code:'te', label:"🇮🇳 తెలుగు"},
  {code:'mr', label:"🇮🇳 मराठी"},
  {code:'gu', label:"🇮🇳 ગુજરાતી"},
  {code:'kn', label:"🇮🇳 ಕನ್ನಡ"},
  {code:'ml', label:"🇮🇳 മലയാളം"},
  {code:'pa', label:"🇮🇳 ਪੰਜਾਬੀ"},
  {code:'ur', label:"🇵🇰 اردو"},
  {code:'ne', label:"🇳🇵 नेपाली"},
  {code:'si', label:"🇱🇰 සිංහල"},
  {code:'my', label:"🇲🇲 မြန်မာ"},
  {code:'km', label:"🇰🇭 ខ្មែរ"},
  {code:'lo', label:"🇱🇦 ລາວ"},
  {code:'ar', label:"🇸🇦 العربية"},
  {code:'fa', label:"🇮🇷 فارسی"},
  {code:'he', label:"🇮🇱 עברית"},
  {code:'tr', label:"🇹🇷 Türkçe"},
  {code:'el', label:"🇬🇷 Ελληνικά"},
  {code:'fi', label:"🇫🇮 Suomi"},
  {code:'sv', label:"🇸🇪 Svenska"},
  {code:'no', label:"🇳🇴 Norsk"},
  {code:'da', label:"🇩🇰 Dansk"},
  {code:'is', label:"🇮🇸 Íslenska"},
  {code:'et', label:"🇪🇪 Eesti"},
  {code:'lv', label:"🇱🇻 Latviešu"},
  {code:'lt', label:"🇱🇹 Lietuvių"},
  {code:'ga', label:"🇮🇪 Gaeilge"},
  {code:'cy', label:"🏴󠁧󠁢󠁷󠁬󠁳󠁿 Cymraeg"},
  {code:'eu', label:"🇪🇸 Euskara"},
  {code:'ca', label:"🇪🇸 Català"},
  {code:'gl', label:"🇪🇸 Galego"},
  {code:'af', label:"🇿🇦 Afrikaans"},
  {code:'sw', label:"🇰🇪 Kiswahili"},
  {code:'am', label:"🇪🇹 አማርኛ"},
  {code:'ha', label:"🇳🇬 Hausa"},
  {code:'yo', label:"🇳🇬 Yorùbá"},
  {code:'zu', label:"🇿🇦 isiZulu"}
];

(function(){
  var STORAGE_KEY = 'site_lang';

  function savedLang(){ try { return localStorage.getItem(STORAGE_KEY) || ''; } catch(e){ return ''; } }

  function toggleCatalogLangDropdown(){
    var d = document.getElementById('catalogLangDropdown');
    if(d) d.classList.toggle('active');
  }

  function syncCatalogLang(lang){
    var btn = document.getElementById('catalogLangCurrent');
    var d = document.getElementById('catalogLangDropdown');
    if(!d) return;
    var opts = d.querySelectorAll('.lang-option');
    for(var i=0;i<opts.length;i++){
      if(opts[i].getAttribute('data-lang') === lang){
        if(btn) btn.textContent = opts[i].textContent;
        break;
      }
    }
  }

  function setLanguage(lang){
    localStorage.setItem(STORAGE_KEY, lang);
    var combo = document.querySelector('.goog-te-combo');
    if(combo && Array.from(combo.options).some(function(o){return o.value===lang;})){
      combo.value = lang;
      combo.dispatchEvent(new Event('change'));
    }
    location.reload();
  }

  function injectLangWrapper(){
    var drawer = document.getElementById('catalogDrawer');
    if(!drawer || document.getElementById('catalogLangWrapper')) return;
    var wrapper = document.createElement('div');
    wrapper.id = 'catalogLangWrapper';
    wrapper.className = 'lang-wrapper';
    var btn = document.createElement('button');
    btn.className = 'lang-btn';
    btn.id = 'catalogLangBtn';
    btn.setAttribute('onclick','toggleCatalogLangDropdown()');
    btn.style.cssText = 'width:100%;justify-content:space-between;font-size:14px;gap:8px;';
    var span = document.createElement('span');
    span.id = 'catalogLangCurrent';
    span.textContent = (LANGS[0] ? LANGS[0].label : 'Русский');
    btn.appendChild(span);
    var svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    svg.setAttribute('viewBox','0 0 24 24');
    svg.setAttribute('width','16');
    svg.setAttribute('height','16');
    svg.setAttribute('fill','none');
    svg.setAttribute('stroke','currentColor');
    svg.setAttribute('stroke-width','2');
    var poly = document.createElementNS('http://www.w3.org/2000/svg','polyline');
    poly.setAttribute('points','6 9 12 15 18 9');
    svg.appendChild(poly);
    btn.appendChild(svg);
    wrapper.appendChild(btn);
    var dropdown = document.createElement('div');
    dropdown.className = 'lang-dropdown';
    dropdown.id = 'catalogLangDropdown';
    dropdown.style.cssText = 'left:16px;right:16px;width:auto;position:absolute;';
    LANGS.forEach(function(l){
      var opt = document.createElement('div');
      opt.className = 'lang-option';
      opt.setAttribute('data-lang', l.code);
      opt.setAttribute('onclick', "setLanguage('"+l.code+"')");
      opt.textContent = l.label;
      dropdown.appendChild(opt);
    });
    wrapper.appendChild(dropdown);
    var h3 = drawer.querySelector('h3');
    if(h3 && h3.nextSibling){
      drawer.insertBefore(wrapper, h3.nextSibling);
    } else {
      drawer.insertBefore(wrapper, drawer.firstChild);
    }
    var divider = document.createElement('div');
    divider.className = 'catalog-divider thick';
    drawer.insertBefore(divider, wrapper.nextSibling);
  }

  function initGoogleTranslate(){
    if(document.getElementById('google_translate_element')) return;
    var host = document.createElement('div');
    host.id = 'google_translate_element';
    host.hidden = true;
    document.body.appendChild(host);
    window.googleTranslateElementInit = function(){
      new google.translate.TranslateElement({
        pageLanguage: 'ru',
        includedLanguages: LANGS.map(function(l){return l.code;}).join(','),
        autoDisplay: false
      }, 'google_translate_element');
    };
    var script = document.createElement('script');
    script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    document.head.appendChild(script);
  }

  function applyGoogleTranslate(lang){
    if(!lang || lang === 'ru') return;
    var tries = 0;
    var iv = setInterval(function(){
      var combo = document.querySelector('.goog-te-combo');
      if(combo){
        clearInterval(iv);
        if(Array.from(combo.options).some(function(o){return o.value===lang;})){
          combo.value = lang;
          combo.dispatchEvent(new Event('change'));
        }
      }
      if(++tries > 60) clearInterval(iv);
    }, 200);
  }

  function init(){
    injectLangWrapper();
    initGoogleTranslate();
    var lang = savedLang() || 'ru';
    try { document.documentElement.lang = lang; } catch(e){}
    setTimeout(function(){ syncCatalogLang(lang); }, 150);
    applyGoogleTranslate(lang);
  }

  window.toggleCatalogLangDropdown = toggleCatalogLangDropdown;
  window.setLanguage = setLanguage;

  // inject CSS
  var style = document.createElement('style');
  style.textContent = `
    .skiptranslate,.goog-te-banner-frame{display:none!important}
    html,body{top:0!important}
    .lang-wrapper{position:relative;display:block;}
    .lang-btn{display:flex;align-items:center;justify-content:center;gap:4px;padding:6px 10px;border-radius:12px;border:2px solid var(--neon,#1e40af);background:rgba(255,255,255,0.9);color:var(--neon-dark,#1e3a8a);cursor:pointer;font-size:14px;transition:0.3s;flex-shrink:0;}
    .lang-btn:hover{background:rgba(30,64,175,0.15);transform:scale(1.05);}
    .lang-dropdown{position:absolute;top:100%;right:0;margin-top:6px;background:var(--card-bg,#f8fafc);border:2px solid var(--border-color,rgba(30,64,175,0.12));border-radius:14px;max-height:350px;overflow-y:auto;width:220px;z-index:9999;display:none;box-shadow:0 10px 40px rgba(0,0,0,0.2);}
    .lang-dropdown.active{display:block;}
    .lang-option{padding:8px 14px;cursor:pointer;font-size:14px;transition:0.2s;color:var(--text-main,#1e293b);display:flex;align-items:center;gap:8px;}
    .lang-option:hover{background:var(--neon-glow,rgba(30,64,175,0.12));color:var(--neon-dark,#1e3a8a);}
    [data-theme="dark"] .lang-btn{background:var(--card-bg,#1e293b);color:var(--neon-dark,#fff);border-color:var(--border-color,rgba(255,255,255,0.15));}
    [data-theme="dark"] .lang-dropdown{background:#1e293b;border-color:rgba(255,255,255,0.15);}
    [data-theme="dark"] .lang-option{color:#f8fafc;}
  `;
  document.head.appendChild(style);

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
