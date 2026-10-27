(function(){
  const COUNTRIES = [
    {code:'RU', name:'Россия', dial:'7', flag:'🇷🇺'},
    {code:'UA', name:'Украина', dial:'380', flag:'🇺🇦'},
    {code:'BY', name:'Беларусь', dial:'375', flag:'🇧🇾'},
    {code:'KZ', name:'Казахстан', dial:'77', flag:'🇰🇿'},
    {code:'KG', name:'Кыргызстан', dial:'996', flag:'🇰🇬'},
    {code:'UZ', name:'Узбекистан', dial:'998', flag:'🇺🇿'},
    {code:'TJ', name:'Таджикистан', dial:'992', flag:'🇹🇯'},
    {code:'AM', name:'Армения', dial:'374', flag:'🇦🇲'},
    {code:'AZ', name:'Азербайджан', dial:'994', flag:'🇦🇿'},
    {code:'GE', name:'Грузия', dial:'995', flag:'🇬🇪'},
    {code:'MD', name:'Молдова', dial:'373', flag:'🇲🇩'},
    {code:'TR', name:'Турция', dial:'90', flag:'🇹🇷'},
    {code:'CN', name:'Китай', dial:'86', flag:'🇨🇳'},
    {code:'IN', name:'Индия', dial:'91', flag:'🇮🇳'},
    {code:'US', name:'США', dial:'1', flag:'🇺🇸'},
    {code:'CA', name:'Канада', dial:'1', flag:'🇨🇦'},
    {code:'GB', name:'Великобритания', dial:'44', flag:'🇬🇧'},
    {code:'DE', name:'Германия', dial:'49', flag:'🇩🇪'},
    {code:'FR', name:'Франция', dial:'33', flag:'🇫🇷'},
    {code:'IT', name:'Италия', dial:'39', flag:'🇮🇹'},
    {code:'ES', name:'Испания', dial:'34', flag:'🇪🇸'},
    {code:'PL', name:'Польша', dial:'48', flag:'🇵🇱'},
    {code:'NL', name:'Нидерланды', dial:'31', flag:'🇳🇱'},
    {code:'BE', name:'Бельгия', dial:'32', flag:'🇧🇪'},
    {code:'CZ', name:'Чехия', dial:'420', flag:'🇨🇿'},
    {code:'SK', name:'Словакия', dial:'421', flag:'🇸🇰'},
    {code:'HU', name:'Венгрия', dial:'36', flag:'🇭🇺'},
    {code:'RO', name:'Румыния', dial:'40', flag:'🇷🇴'},
    {code:'BG', name:'Болгария', dial:'359', flag:'🇧🇬'},
    {code:'RS', name:'Сербия', dial:'381', flag:'🇷🇸'},
    {code:'HR', name:'Хорватия', dial:'385', flag:'🇭🇷'},
    {code:'SI', name:'Словения', dial:'386', flag:'🇸🇮'},
    {code:'LT', name:'Литва', dial:'370', flag:'🇱🇹'},
    {code:'LV', name:'Латвия', dial:'371', flag:'🇱🇻'},
    {code:'EE', name:'Эстония', dial:'372', flag:'🇪🇪'},
    {code:'FI', name:'Финляндия', dial:'358', flag:'🇫🇮'},
    {code:'SE', name:'Швеция', dial:'46', flag:'🇸🇪'},
    {code:'NO', name:'Норвегия', dial:'47', flag:'🇳🇴'},
    {code:'DK', name:'Дания', dial:'45', flag:'🇩🇰'},
    {code:'AE', name:'ОАЭ', dial:'971', flag:'🇦🇪'},
    {code:'IL', name:'Израиль', dial:'972', flag:'🇮🇱'},
    {code:'JP', name:'Япония', dial:'81', flag:'🇯🇵'},
    {code:'KR', name:'Южная Корея', dial:'82', flag:'🇰🇷'},
    {code:'VN', name:'Вьетнам', dial:'84', flag:'🇻🇳'},
    {code:'TH', name:'Таиланд', dial:'66', flag:'🇹🇭'},
    {code:'ID', name:'Индонезия', dial:'62', flag:'🇮🇩'},
    {code:'MY', name:'Малайзия', dial:'60', flag:'🇲🇾'},
    {code:'SG', name:'Сингапур', dial:'65', flag:'🇸🇬'},
    {code:'AU', name:'Австралия', dial:'61', flag:'🇦🇺'},
    {code:'NZ', name:'Новая Зеландия', dial:'64', flag:'🇳🇿'},
    {code:'BR', name:'Бразилия', dial:'55', flag:'🇧🇷'},
    {code:'MX', name:'Мексика', dial:'52', flag:'🇲🇽'},
    {code:'AR', name:'Аргентина', dial:'54', flag:'🇦🇷'},
    {code:'CL', name:'Чили', dial:'56', flag:'🇨🇱'},
    {code:'CO', name:'Колумбия', dial:'57', flag:'🇨🇴'}
  ];

  function buildStyle(){
    if(document.getElementById('pcStyle')) return;
    const s=document.createElement('style');
    s.id='pcStyle';
    s.textContent=`
      .pc-wrap{display:flex;align-items:stretch;gap:0;border:2px solid var(--border-color,#e5e7eb);border-radius:12px;background:var(--card-bg,#fff);overflow:visible;position:relative;margin-top:0;margin-bottom:12px;height:48px;box-sizing:border-box}
      .pc-wrap:focus-within{border-color:var(--neon,#2563eb)}
      .pc-btn{display:flex;align-items:center;justify-content:center;gap:4px;padding:0 10px;border:none;background:transparent;color:var(--text-main,#1f2937);cursor:pointer;white-space:nowrap;font-size:14px;flex-shrink:0;height:100%;box-sizing:border-box}
      .pc-flag{font-size:16px;line-height:1}
      .pc-caret{font-size:10px;color:var(--text-muted,#6b7280)}
      .pc-input{flex:1 1 auto;min-width:0;width:100%;border:none;background:transparent;color:var(--text-main,#1f2937);padding:0 14px 0 8px;font-size:15px;outline:none;font-family:inherit;height:100%;line-height:normal;box-sizing:border-box;margin:0;border-radius:0;box-shadow:none}
      .pc-input:focus{outline:none;box-shadow:none}
      .pc-dd{position:absolute;left:-2px;right:-2px;top:calc(100% + 4px);max-height:320px;overflow:auto;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;box-shadow:0 20px 40px rgba(0,0,0,.12);z-index:10050;display:none;padding:6px;box-sizing:border-box;width:auto}
      .pc-dd.open{display:block}
      .pc-item{display:flex;align-items:center;gap:6px;padding:9px 10px;border-radius:10px;cursor:pointer;color:var(--text-main,#1f2937);text-align:left}
      .pc-item:hover{background:rgba(0,0,0,.04)}
      .pc-item-name{flex:0 1 auto;font-size:14px;text-align:left;white-space:nowrap}
      .pc-item-dial{margin-left:6px;color:var(--text-muted,#6b7280);font-size:13px;text-align:left;white-space:nowrap}
    `;
    document.head.appendChild(s);
  }

  window.initPhoneCountry = function(input){
    if(!input || input.dataset.pcInit) return;
    buildStyle();
    input.dataset.pcInit='1';
    const wrap=document.createElement('div');
    wrap.className='pc-wrap';
    const btn=document.createElement('button');
    btn.type='button';
    btn.className='pc-btn';
    btn.innerHTML='<span class="pc-flag">🇷🇺</span><span class="pc-caret">▾</span>';
    const dd=document.createElement('div');
    dd.className='pc-dd';
    COUNTRIES.forEach(c=>{
      const it=document.createElement('div');
      it.className='pc-item';
      it.innerHTML='<span class="pc-flag">'+c.flag+'</span><span class="pc-item-name">'+c.name+'</span><span class="pc-item-dial">+'+c.dial+'</span>';
      it.addEventListener('click',function(e){
        e.preventDefault(); e.stopPropagation();
        input.dataset.pcDial=c.dial;
        input.dataset.pcOldDial=c.dial;
        btn.querySelector('.pc-flag').textContent=c.flag;
        dd.classList.remove('open');
        input.value = '+'+c.dial;
        input.focus();
      });
      dd.appendChild(it);
    });
    input.dataset.pcDial='7';
    input.dataset.pcOldDial='7';
    input.type='tel';
    input.autocomplete='tel';
    input.classList.add('pc-input');
    input.style.border='none';
    input.style.boxShadow='none';
    input.style.outline='none';
    const parent=input.parentNode;
    parent.insertBefore(wrap,input);
    wrap.appendChild(btn);
    wrap.appendChild(input);
    wrap.appendChild(dd);
    btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();dd.classList.toggle('open');});
    document.addEventListener('click',function(e){if(!wrap.contains(e.target)) dd.classList.remove('open');});
    input.addEventListener('input', normalize);
    input.addEventListener('blur', normalize);
    function normalize(){
      let digits = input.value.replace(/\D/g,'');
      const dial=input.dataset.pcOldDial||input.dataset.pcDial||'7';
      if(digits.length === 0){
        input.value = '+'+dial;
        return;
      }
      if(dial==='7'){
        if(digits.startsWith('8')) digits = digits.slice(1);
        if(digits.startsWith('7')) digits = digits.slice(1);
      }
      if(dial==='1'){
        if(digits.startsWith('1')) digits = digits.slice(1);
      }
      input.value = '+'+dial + digits;
    }
    input.value = '+7';
  };
})();
