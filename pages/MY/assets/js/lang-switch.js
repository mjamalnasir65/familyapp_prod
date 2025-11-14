(function(){
  function setCookie(name, value, days){
    var expires="";
    if(days){
      var date = new Date();
      date.setTime(date.getTime() + (days*24*60*60*1000));
      expires = "; expires=" + date.toUTCString();
    }
    // Use root path so cookie is available site-wide on prod
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
  }
  function getCookie(name){
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i<ca.length;i++){
      var c = ca[i];
      while(c.charAt(0)==' ') c = c.substring(1,c.length);
      if(c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
  }
  function currentFileName(){
    var path = window.location.pathname;
    var last = path.split('/').filter(Boolean).pop() || 'index.html';
    if(!/\.html?$/i.test(last)){
      last = last + '.html';
    }
    return last;
  }
  function resolveTarget(lang){
    var path = window.location.pathname;
    var parts = path.split('/').filter(Boolean);
    // Prefer clean root-based pages path (always lowercase in URL)
    var urlLang = (lang || '').toString().toLowerCase();
    var base = '/pages/' + urlLang + '/';
  // If already under /pages/<LANG>/ (legacy /familyapp/public/pages/ removed), replace LANG in-place
    var pagesIdx = parts.indexOf('pages');
    if (pagesIdx >= 0 && parts.length > pagesIdx+1) {
      var langSeg = parts[pagesIdx+1];
      if (/^[A-Za-z]{2}$/.test(langSeg)){
        parts[pagesIdx+1] = urlLang;
        return '/' + parts.join('/');
      }
    }
    var famIdx = parts.indexOf('familyapp');
    if (famIdx >= 0) {
      var pubIdx = parts.indexOf('public');
      var pIdx = parts.indexOf('pages');
      if (pubIdx >= 0 && pIdx >= 0 && parts.length > pIdx+1) {
        parts[pIdx+1] = urlLang;
        return '/' + parts.join('/');
      }
    }
    // Otherwise, navigate to same filename under language folder at root
    var fname = currentFileName();
    if (fname.toLowerCase() === 'index.html') {
      // Landing should go to language-specific public.html
      fname = 'public.html';
    }
    return base + fname;
  }
  function setLanguage(lang){
    if(!lang) return;
    localStorage.setItem('pwa_lang', lang);
    setCookie('pwa_lang', lang, 365);
    window.location.href = resolveTarget(lang);
  }
  function initButtons() {
    var host = document.getElementById('langSwitch');
    if(!host) return;
    host.innerHTML = '<button id="btnEN" class="btn-lang">EN</button>'+
                     '<button id="btnMY" class="btn-lang">MY</button>';
    var en = document.getElementById('btnEN');
    var my = document.getElementById('btnMY');
    if(en) en.addEventListener('click', function(){ setLanguage('EN'); });
    if(my) my.addEventListener('click', function(){ setLanguage('MY'); });
  }
  function ensureDefaultLang(){
    // If no preference set, default to EN but do NOT redirect
    var saved = localStorage.getItem('pwa_lang') || getCookie('pwa_lang');
    if (!saved) {
      localStorage.setItem('pwa_lang', 'EN');
      setCookie('pwa_lang', 'EN', 365);
    }
  }
  // Style (minimal)
  var css = '.btn-lang{background:#fff;border:1px solid #cbd5e1;border-radius:6px;margin-left:6px;padding:4px 8px;cursor:pointer;font-size:13px}'+
            '.btn-lang:hover{background:#e2e8f0}'+
            '#langSwitch{position:fixed;top:10px;right:15px;z-index:1000}';
  var style = document.createElement('style'); style.textContent = css; document.head.appendChild(style);

  document.addEventListener('DOMContentLoaded', function(){
    initButtons();
    ensureDefaultLang();
  });

  // Expose for manual calls
  window.setLanguage = setLanguage;
})();
