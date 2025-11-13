// Family Tree PWA - minimal landing/auth JS
(function(){
  function isMobileBrowser(){
    try{
      if (navigator.userAgentData && typeof navigator.userAgentData.mobile === 'boolean') {
        return navigator.userAgentData.mobile;
      }
    }catch(_){ }
    var ua = (navigator.userAgent||'');
    var ipadOS = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    return /Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|Windows Phone|BlackBerry/i.test(ua) || ipadOS;
  }

  // Daftar SW hanya pada peranti mudah alih; nyahdaftar di desktop
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
      if (isMobileBrowser()) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
          .catch(function(){ /* abaikan ralat */ });
      } else {
        navigator.serviceWorker.getRegistrations().then(function(regs){
          regs.forEach(function(reg){ reg.unregister().catch(function(){}); });
        }).catch(function(){});
      }
    });
  }
  const supportsBackdrop = CSS && CSS.supports && CSS.supports('backdrop-filter','blur(1px)');
  if (!supportsBackdrop) {
    document.documentElement.classList.add('no-backdrop');
  }
  // Simple ripple on primary buttons
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.btn');
    if (!btn) return;
    btn.classList.add('press');
    setTimeout(()=>btn.classList.remove('press'), 150);
  });

  // Install prompt (optional UI hook)
  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function(e){
    e.preventDefault();
    deferredPrompt = e; // you can surface a custom install button and call deferredPrompt.prompt()
  });
  window.addEventListener('appinstalled', function(){ deferredPrompt = null; });
})();
