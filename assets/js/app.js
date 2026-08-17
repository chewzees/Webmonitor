(function () {
  'use strict';

  function getTheme() {
    try {
      var stored = localStorage.getItem('wm-theme');
      if (stored === 'light' || stored === 'dark') return stored;
    } catch (e) {}
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('wm-theme', theme); } catch (e) {}
  }

  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTheme(getTheme() === 'dark' ? 'light' : 'dark');
    });
  });

  var sidebar = document.getElementById('sidebar');
  var backdrop = document.querySelector('.sidebar-backdrop');

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('is-open');
    document.body.classList.add('sidebar-open-lock');
    if (backdrop) backdrop.hidden = false;
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('is-open');
    document.body.classList.remove('sidebar-open-lock');
    if (backdrop) backdrop.hidden = true;
  }

  document.querySelectorAll('[data-sidebar-open]').forEach(function (btn) {
    btn.addEventListener('click', function (e) { e.preventDefault(); openSidebar(); });
  });
  document.querySelectorAll('[data-sidebar-close]').forEach(function (btn) {
    btn.addEventListener('click', function (e) { e.preventDefault(); closeSidebar(); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });
  window.addEventListener('resize', function () {
    if (window.matchMedia('(min-width: 901px)').matches) closeSidebar();
  });

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      var msg = el.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(msg)) e.preventDefault();
    });
    if (el.tagName === 'FORM') {
      el.addEventListener('submit', function (e) {
        var msg = el.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(msg)) e.preventDefault();
      });
    }
  });

  document.querySelectorAll('[data-autofill]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var emailInput = document.querySelector('input[name="email"]');
      var passwordInput = document.querySelector('input[name="password"]');
      if (emailInput) emailInput.value = btn.getAttribute('data-email') || '';
      if (passwordInput) passwordInput.value = btn.getAttribute('data-password') || '';
      if (emailInput) emailInput.focus();
    });
  });

  var refreshEl = document.querySelector('[data-auto-refresh]');
  if (refreshEl) {
    var seconds = parseInt(refreshEl.getAttribute('data-auto-refresh') || '60', 10);
    if (seconds > 0) {
      setInterval(function () { window.location.reload(); }, seconds * 1000);
    }
  }
})();
