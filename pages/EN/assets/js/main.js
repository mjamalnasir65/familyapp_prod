// Family Tree PWA - minimal landing/auth JS
(function(){
  // PWA Service Worker disabled for now: proactively unregister any existing registrations
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
      navigator.serviceWorker.getRegistrations().then(function(regs){
        regs.forEach(function(reg){ reg.unregister().catch(function(){}); });
      }).catch(function(){});
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
