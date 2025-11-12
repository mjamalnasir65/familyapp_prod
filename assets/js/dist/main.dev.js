"use strict";

// Family Tree PWA - minimal landing/auth JS
(function () {
  // Register Service Worker (production-ready)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('./service-worker.js', {
        scope: './'
      }).then(function (reg) {
        // Handle updates: if there's a waiting worker, prompt to refresh
        function listenForWaiting(worker) {
          if (!worker) return;

          if (worker.state === 'installed') {// Optionally: trigger immediate activation
            // reg.waiting?.postMessage({ type: 'SKIP_WAITING' });
          } else {
            worker.addEventListener('statechange', function () {
              if (worker.state === 'installed') {// reg.waiting?.postMessage({ type: 'SKIP_WAITING' });
              }
            });
          }
        }

        reg.addEventListener('updatefound', function () {
          listenForWaiting(reg.installing);
        });
      })["catch"](function () {
        /* ignore */
      });
    }); // Refresh when controller changes (new SW took over)

    navigator.serviceWorker.addEventListener('controllerchange', function () {
      // Avoid reload loops: only reload once per activation
      if (!window.__reloadedBySW) {
        window.__reloadedBySW = true;

        try {
          location.replace(location.href.split('#')[0]);
        } catch (_) {
          location.reload();
        }
      }
    });
  }

  var supportsBackdrop = CSS && CSS.supports && CSS.supports('backdrop-filter', 'blur(1px)');

  if (!supportsBackdrop) {
    document.documentElement.classList.add('no-backdrop');
  } // Simple ripple on primary buttons


  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn');
    if (!btn) return;
    btn.classList.add('press');
    setTimeout(function () {
      return btn.classList.remove('press');
    }, 150);
  }); // Install prompt (optional UI hook)

  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e; // you can surface a custom install button and call deferredPrompt.prompt()
  });
  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
  });
})();