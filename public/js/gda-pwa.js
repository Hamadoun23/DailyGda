/**
 * PWA : enregistrement service worker + menu mobile
 */
(function () {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var swUrl = window.GDA_SW_URL || '/sw.js';
      var scope = swUrl.replace(/\/sw\.js(\?.*)?$/, '/') || '/';
      navigator.serviceWorker.register(swUrl, { scope: scope }).catch(function () {});
    });
  }

  function sidebarEl() {
    return document.getElementById('app-sidebar');
  }

  function backdropEl() {
    return document.getElementById('sidebar-backdrop');
  }

  window.toggleSidebar = function () {
    const sb = sidebarEl();
    const bd = backdropEl();
    if (!sb) return;
    const open = sb.classList.toggle('is-open');
    if (bd) {
      bd.classList.toggle('is-visible', open);
      bd.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    document.body.classList.toggle('sidebar-open', open);
    const btn = document.getElementById('header-menu-btn');
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  window.closeSidebar = function () {
    const sb = sidebarEl();
    const bd = backdropEl();
    if (!sb || !sb.classList.contains('is-open')) return;
    sb.classList.remove('is-open');
    if (bd) {
      bd.classList.remove('is-visible');
      bd.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('sidebar-open');
    const btn = document.getElementById('header-menu-btn');
    if (btn) btn.setAttribute('aria-expanded', 'false');
  };

  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) closeSidebar();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });
})();
