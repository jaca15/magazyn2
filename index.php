<?php
// index.php - Główny panel aplikacji z listą, panelem menu i modalem "Dodaj sprzęt"
// Wymagane pliki: auth.php (require_login()), app_settings.php (opcjonalnie), polaczenie.php (dla podstron)
require_once 'auth.php';
require_login();
require_once 'app_settings.php'; // jeśli nie masz - usuń tę linię

function h($v) { return is_callable('app_h') ? app_h($v) : htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$userName = $_SESSION['nazwa_uzytkownika'] ?? ($_SESSION['user']['nazwa_uzytkownika'] ?? 'Gość');
$canManageEquipment = ma_uprawnienie('manage_equipment');
$canReturnEquipment = ma_uprawnienie('return_equipment');
$isAdmin = czy_admin();
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title><?= h($APP['name'] ?? 'Aplikacja') ?> — Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/dodaj_sprzet.css">
</head>
<body>
  <!-- Nagłówek -->
  <header class="header" role="banner">
    <div class="header-left">
      <h1><?= h($APP['name'] ?? 'Aplikacja') ?></h1>
      <div class="header-subtitle">Wersja: <?= h($APP['version'] ?? '') ?></div>
    </div>
    <div class="user-info">
      <span>Zalogowany: <strong><?= h($userName) ?></strong></span>
      <span id="clock" aria-live="polite">--:--:--</span>
      <a href="logout.php" class="logout-btn">Wyloguj</a>
    </div>
  </header>

  <!-- Panel menu boczny -->
  <nav class="sidebar" role="navigation" aria-label="Menu główne">
    <ul>
      <li><button id="sprzet-btn" class="menu-btn" data-target="wykaz_sprzetu.php?page=1">Sprzęt</button></li>
      <?php if ($canManageEquipment): ?>
      <li><button id="dodaj-sprzet-btn" class="menu-btn" data-target="dodaj_sprzet.php">Dodaj sprzęt</button></li>
      <?php endif; ?>
      <?php if ($canReturnEquipment): ?>
      <li><button id="zwroty-btn" class="menu-btn" data-target="zwrot_panel.php">Zwroty</button></li>
      <?php endif; ?>
      <?php if ($isAdmin): ?>
      <li><button id="podmiot-btn" class="menu-btn" data-target="podmiot_panel.php">Podmioty</button></li>
      <li><button id="podmiot-btn" class="menu-btn" data-target="kategoria_panel.php">Kategorie</button></li>
      <li><button id="podmiot-btn" class="menu-btn" data-target="magazyn_panel.php">Magazyny</button></li>
      <li><button id="podmiot-btn" class="menu-btn" data-target="lokalizacja_panel.php">Lokalizacje</button></li>
      <li><button id="uzytkownicy-btn" class="menu-btn" data-target="users_panel.php">Użytkownicy</button></li>
      <?php endif; ?>
      <li><button id="profil-btn" class="menu-btn" data-target="user_edit.php">Mój profil</button></li>
      <!-- Dodaj kolejne przyciski tutaj -->
    </ul>
  </nav>

  <!-- Główna zawartość -->
  <main class="main-content" role="main">
    <div id="content" class="content">
      <section id="dashboard">
        <h2>Witaj w panelu</h2>
        <p>Wybierz pozycję z menu po lewej stronie, aby wyświetlić zawartość.</p>
        <p class="app-author">Autor: <?= h($APP['author'] ?? '') ?></p>
      </section>
    </div>
  </main>

  <!-- Modal (struktura powinna być childem body) -->
  <div id="modal" class="modal" aria-hidden="true" role="dialog">
    <div id="modal-backdrop" class="modal-backdrop" data-close="1"></div>
    <div class="modal-dialog" role="document" aria-labelledby="modal-title">
      <button id="modal-close" class="modal-close" aria-label="Zamknij okno">&times;</button>
      <div id="modal-body" class="modal-body">
        <!-- Zawartość formularza ładowana dynamicznie -->
      </div>
    </div>
  </div>

  <!-- Stopka -->
  <footer class="footer" role="contentinfo">
    <p><?= h($APP['author'] ?? '') ?> © <?= date('Y') ?></p>
  </footer>

  <script>
  // ===== Zegar =====
  (function() {
    const clockEl = document.getElementById('clock');
    function tick() {
      const now = new Date();
      clockEl.textContent = now.toLocaleTimeString();
    }
    tick();
    setInterval(tick, 1000);
  })();

  // ===== Stan aplikacji =====
  window.currentContentUrl = null;

  // ===== Pomocnicze =====
  function qs(selector, root=document) { return root.querySelector(selector); }
  function qsa(selector, root=document) { return Array.from(root.querySelectorAll(selector)); }
  function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  // ===== Przenieś modal do body (zapobiega problemom z stacking context) =====
  document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal');
    if (modal && modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
  });

  // ===== Utility: wykonaj <script> znajdujące się w wstawionym fragmencie =====
  // Funkcja wykonuje inline i zewnętrzne skrypty w kolejności występowania.
  // Zwraca Promise, który kończy się gdy wszystkie skrypty (w tym z src) zostaną załadowane.
  async function runInlineScripts(container) {
    if (!container) return;
    const scripts = Array.from(container.querySelectorAll('script'));
    for (const oldScript of scripts) {
      const newScript = document.createElement('script');
      if (oldScript.type) newScript.type = oldScript.type;
      if (oldScript.src) {
        await new Promise((resolve) => {
          newScript.src = oldScript.src;
          newScript.async = false;
          newScript.onload = resolve;
          newScript.onerror = function() { console.error('Błąd ładowania skryptu:', oldScript.src); resolve(); };
          document.head.appendChild(newScript);
        });
      } else {
        newScript.text = oldScript.textContent;
        document.head.appendChild(newScript);
        document.head.removeChild(newScript);
      }
    }
  }

  // ===== Ładowanie treści do #content (AJAX) =====
  async function loadContent(url) {
    const content = qs('#content');
    if (!content) return;
    window.currentContentUrl = url;
    content.innerHTML = '<p>Ładowanie…</p>';
    try {
      const resp = await fetch(url, { credentials: 'same-origin' });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const html = await resp.text();
      content.innerHTML = html;

      // wykonaj skrypty osadzone w HTML (jeśli są)
      await runInlineScripts(content);

      // podłącz paginację (linki w treści)
      attachPaginationListeners();

      // załaduj ogólne delegowane handlery (tylko raz)
      if (typeof attachContentHandlers === 'function') {
        attachContentHandlers();
      }

      // Wywołaj wszystkie globalne funkcje init*Panel, które mogły zostać zdefiniowane przez fragment.
      // Funkcje są idempotentne (powinny same sprawdzać, czy są już zainicjalizowane).
      try {
        Object.keys(window).forEach(function(k){
          if (/^init.*Panel$/.test(k) && typeof window[k] === 'function') {
            try { window[k](); } catch (e) { console.error('init panel error for', k, e); }
          }
        });
      } catch (e) {
        console.error('Error invoking init*Panel functions', e);
      }

      // powiadom inne skrypty, że treść została załadowana
      document.dispatchEvent(new CustomEvent('content:loaded', { detail: { url } }));
    } catch (err) {
      console.error('Błąd ładowania treści:', err);
      content.innerHTML = '<p class="error">Błąd ładowania: ' + escapeHtml(err.message || '') + '</p>';
    }
  }

  function attachPaginationListeners() {
    const content = qs('#content');
    if (!content) return;
    qsa('.pagination a', content).forEach(a => {
      if (!a.dataset.ajaxBound) {
        a.dataset.ajaxBound = '1';
        a.addEventListener('click', function(e) {
          e.preventDefault();
          const href = this.getAttribute('href');
          if (href) loadContent(href);
        });
      }
    });
  }

  // ===== Menu - podłączenie przycisków (delegacja i bezpośrednio) =====
  (function initMenu() {
    document.addEventListener('click', function(e){
      const btn = e.target.closest && e.target.closest('.menu-btn[data-target]');
      if (!btn) return;
      e.preventDefault();
      const target = btn.dataset.target;
      if (!target) return;
      // Jeśli to "Dodaj sprzęt" - otwórz modal
      if (btn.id === 'dodaj-sprzet-btn') {
        openModal(target);
        return;
      }
      // Inne - ładuj do content
      document.querySelectorAll('.menu-btn').forEach(b => b.classList.toggle('active', b === btn));
      loadContent(target);
    });

    // dodatkowe klawisze obsługi (Enter)
    document.querySelectorAll('.menu-btn[data-target]').forEach(btn => {
      if (!btn._menuBound) {
        btn._menuBound = true;
        btn.addEventListener('keydown', function(e){
          if (e.key === 'Enter') btn.click();
        });
      }
    });
  })();

  // ===== Modal - otwieranie, zamykanie, fetch i obsługa formularza AJAX =====
  (function initModal() {
    const modal = qs('#modal');
    const modalBody = qs('#modal-body');
    const modalClose = qs('#modal-close');
    const modalBackdrop = qs('#modal-backdrop');

    if (!modal || !modalBody) return;

    window.openModal = async function(url) {
      if (modal.parentElement !== document.body) document.body.appendChild(modal);

      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
      modal.style.display = 'block';
      modal.style.zIndex = '99999';
      modalBackdrop.style.display = 'block';
      qs('.modal-dialog', modal).style.display = 'block';

      modalBody.innerHTML = '<p>Ładowanie…</p>';
      try {
        const resp = await fetch(url, { credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'} });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const html = await resp.text();
        modalBody.innerHTML = html;

        // uruchom skrypty zawarte w modalu (jeśli są)
        await runInlineScripts(modalBody);

        // Po wstawieniu formularza - podłącz obsługę submit (AJAX)
        attachFormHandler();

        const first = modalBody.querySelector('input, select, textarea, button');
        if (first) first.focus();
      } catch (err) {
        console.error('Błąd ładowania modala:', err);
        modalBody.innerHTML = '<div class="error">Błąd ładowania formularza: ' + escapeHtml(err.message || '') + '</div>';
      }
    };

    window.closeModal = function() {
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('modal-open');
      modal.style.display = 'none';
      modalBackdrop.style.display = 'none';
      qs('.modal-dialog', modal).style.display = 'none';
      if (modalBody) modalBody.innerHTML = '';
    };

    if (modalClose) modalClose.addEventListener('click', () => window.closeModal());
    if (modalBackdrop) modalBackdrop.addEventListener('click', function(e) {
      if (e.target === modalBackdrop) window.closeModal();
    });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
        window.closeModal();
      }
    });
  })();

  // ===== Formularz w modalu: attach + AJAX submit =====
  function attachFormHandler() {
    const modalBody = qs('#modal-body');
    if (!modalBody) return;
    const form = modalBody.querySelector('form');
    if (!form) return;

    if (form._hasSubmitHandler) return;
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      submitFormAjax(form);
    });
    form._hasSubmitHandler = true;
  }

  async function submitFormAjax(form) {
    const feedback = form.querySelector('#form-feedback') || qs('#form-feedback') || null;
    if (feedback) feedback.innerHTML = '<em>Wysyłanie…</em>';

    const action = form.getAttribute('action') || '';
    const method = (form.getAttribute('method') || 'post').toUpperCase();
    const formData = new FormData(form);
    try {
      const resp = await fetch(action, {
        method: method,
        body: formData,
        credentials: 'same-origin',
        headers: {'X-Requested-With':'XMLHttpRequest'}
      });

      const text = await resp.text();
      let data = null;
      try { data = JSON.parse(text); } catch (e) { data = null; }

      if (resp.ok && data && data.success) {
        if (feedback) feedback.innerHTML = '<div class="form-success">' + escapeHtml(data.message || 'Zapisano') + '</div>';
        if (form && form.id === 'userEditForm' && data.message) {
          alert(data.message);
        }
        setTimeout(() => {
          if (window.closeModal) window.closeModal();
          const url = window.currentContentUrl || 'wykaz_sprzetu.php?page=1';
          if (typeof loadContent === 'function') loadContent(url);
        }, 600);
      } else {
        if (data && data.errors && Array.isArray(data.errors)) {
          if (feedback) feedback.innerHTML = '<div class="form-error"><ul><li>' + data.errors.map(e => escapeHtml(e)).join('</li><li>') + '</li></ul></div>';
        } else {
          if (text && !data) {
            if (text.indexOf('<form') !== -1) {
              qs('#modal-body').innerHTML = text;
              attachFormHandler();
            } else if (feedback) {
              feedback.innerHTML = text;
            }
          } else {
            if (feedback) feedback.innerHTML = '<div class="form-error">Błąd serwera. Spróbuj ponownie.</div>';
          }
        }
      }
    } catch (err) {
      console.error('Błąd wysyłania formularza:', err);
      if (feedback) feedback.innerHTML = '<div class="form-error">Błąd wysyłania: ' + escapeHtml(err.message || '') + '</div>';
    }
  }

  // ===== Nowa funkcja: Attach handlers for dynamically loaded content (list actions) =====
  function attachContentHandlers() {
    if (attachContentHandlers._inited) return;
    attachContentHandlers._inited = true;

    const content = qs('#content');

    // Delegated click handler for links that should open in modal
    document.addEventListener('click', function(e) {
      const a = e.target.closest('a.open-modal');
      if (!a) return;
      if (!content.contains(a)) return;
      e.preventDefault();
      const url = a.dataset.url || a.getAttribute('href');
      if (!url) return;
      if (typeof window.openModal === 'function') {
        window.openModal(url);
      } else {
        window.location.href = url;
      }
    });

    // Delegated click handler for delete buttons inside #content
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('button.delete');
      if (!btn) return;
      if (!content.contains(btn)) return;

      const id = btn.dataset.id;
      const name = btn.dataset.name || 'element';
      if (!id) return;

      if (!confirm('Czy na pewno chcesz usunąć: ' + name + ' ?')) return;

      const fd = new FormData();
      fd.append('delete_id', id);

      btn.disabled = true;
      const origText = btn.textContent;
      btn.textContent = 'Usuwanie...';

      fetch(content.dataset.deleteEndpoint || (content.dataset.currentUrl || window.currentContentUrl || location.pathname), {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(r => r.json())
        .then(data => {
          if (data && data.success) {
            if (typeof window.loadContent === 'function') {
              const url = window.currentContentUrl || location.pathname;
              window.loadContent(url);
            } else {
              const tr = document.querySelector('tr[data-id="' + id + '"]');
              if (tr) tr.remove();
              alert(data.message || 'Usunięto.');
            }
          } else {
            const err = (data && data.errors && data.errors.join) ? data.errors.join('\n') : 'Błąd';
            alert(err);
            btn.disabled = false;
            btn.textContent = origText;
          }
        }).catch(err => {
          console.error(err);
          alert('Błąd sieci. Spróbuj ponownie.');
          btn.disabled = false;
          btn.textContent = origText;
        });
    });
  }

  // ===== Przydatne: domyślne załadowanie wykazu przy starcie =====
  document.addEventListener('DOMContentLoaded', function() {
    attachContentHandlers();
    // opcjonalnie automatyczne załadowanie listy sprzętu/inna strona startowa:
    // loadContent('wykaz_sprzetu.php?page=1');
  });
  </script>
</body>
</html>