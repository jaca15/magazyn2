<?php
// lokalizacja_edit.php - formularz edycji lokalizacji + zapis (obsługa AJAX/modal i bezpośrednio)
// Wymagane: auth.php (require_admin()), polaczenie.php ($pdo)
require_once 'auth.php';
require_admin();
require 'polaczenie.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function is_ajax(): bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
    return false;
}

// Pobierz id lokalizacji (GET lub POST)
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Nieprawidłowy identyfikator lokalizacji.']]);
        exit;
    }
    header('Location: lokalizacja_panel.php');
    exit;
}

// Lista magazynów do wyboru
try {
    $magazyny = $pdo->query("SELECT id, nazwa FROM magazyny ORDER BY nazwa")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $magazyny = [];
}

// Obsługa POST (zapis zmian)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $errors = [];

    // Pobierz i przytnij pola
    $nazwa   = trim((string)($_POST['nazwa'] ?? ''));
    $id_mag  = isset($_POST['id_mag']) && $_POST['id_mag'] !== '' ? (int)$_POST['id_mag'] : null;
    $uwagi   = trim((string)($_POST['uwagi'] ?? ''));

    // Walidacja
    if ($nazwa === '') $errors[] = 'Nazwa jest wymagana.';
    if ($id_mag === null) $errors[] = 'Magazyn jest wymagany.';

    if (!empty($errors)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        $upd = $pdo->prepare("UPDATE lokalizacje SET nazwa = :nazwa, id_mag = :id_mag, uwagi = :uwagi WHERE id = :id");
        $upd->execute([':nazwa' => $nazwa, ':id_mag' => $id_mag, ':uwagi' => $uwagi, ':id' => $id]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Zapisano zmiany.']);
        exit;
    } catch (Throwable $e) {
        error_log('lokalizacja_edit save error: ' . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd zapisu do bazy danych.']]);
        exit;
    }
}

// Jeśli nie POST — pobierz dane lokalizacji i wyświetl formularz
try {
    $q = $pdo->prepare("SELECT id, nazwa, id_mag, uwagi FROM lokalizacje WHERE id = :id LIMIT 1");
    $q->execute([':id' => $id]);
    $lokalizacja = $q->fetch(PDO::FETCH_ASSOC);
    if (!$lokalizacja) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['Lokalizacja nie istnieje.']]);
            exit;
        }
        header('Location: lokalizacja_panel.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('lokalizacja_edit fetch error: ' . $e->getMessage());
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd serwera.']]);
        exit;
    }
    header('Location: lokalizacja_panel.php');
    exit;
}
?>
<div id="lokalizacja-edit-panel" class="content">
  <h2>Edytuj lokalizację</h2>
  <small class="hint">* - pola wymagane.</small>

  <form id="lokalizacjaEditForm" method="post" action="lokalizacja_edit.php" novalidate>
    <input type="hidden" name="id" value="<?= (int)$lokalizacja['id'] ?>">
    <?= csrf_field() ?>
    <div id="form-feedback" class="form-feedback" aria-live="polite"></div>

    <div class="form-row">
      <label for="nazwa">Nazwa <span aria-hidden="true">*</span></label>
      <input id="nazwa" name="nazwa" type="text" required maxlength="100" class="form-control" value="<?= h($lokalizacja['nazwa']) ?>" />
    </div>

    <div class="form-row">
      <label for="id_mag">Magazyn <span aria-hidden="true">*</span></label>
      <select id="id_mag" name="id_mag" required class="form-control">
        <option value="">Wybierz...</option>
        <?php foreach ($magazyny as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= (string)$lokalizacja['id_mag'] === (string)$m['id'] ? 'selected' : '' ?>>
            <?= h($m['nazwa']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="uwagi">Uwagi</label>
      <input id="uwagi" name="uwagi" type="text" maxlength="150" class="form-control" value="<?= h($lokalizacja['uwagi'] ?? '') ?>" />
    </div>

    <div class="form-actions" style="margin-top:12px;">
      <button type="submit" class="btn btn-primary">Zapisz</button>
      <button type="button" class="btn btn-outline" onclick="if(window.closeModal) window.closeModal(); else history.back();">Anuluj</button>
    </div>
  </form>

  <script>
    (function(){
      var form = document.getElementById('lokalizacjaEditForm');
      if (!form) return;

      form.addEventListener('submit', function(e){
        if (typeof submitFormAjax === 'function') return;
        e.preventDefault();
        var fb = document.getElementById('form-feedback');
        fb.innerHTML = '<em>Wysyłanie…</em>';
        var data = new FormData(form);
        fetch(form.getAttribute('action') || window.location.pathname, {
          method: 'POST',
          body: data,
          credentials: 'same-origin',
          headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function(r){ return r.json(); })
        .then(function(json){
          if (json && json.success) {
            fb.innerHTML = '<div class="form-success">' + (json.message || 'Zapisano') + '</div>';
            setTimeout(function(){
              if (typeof window.closeModal === 'function') {
                window.closeModal();
                if (typeof window.loadContent === 'function') {
                  window.loadContent('lokalizacja_panel.php');
                }
              } else {
                window.location.href = 'lokalizacja_panel.php';
              }
            }, 700);
          } else {
            if (json && json.errors) {
              fb.innerHTML = '<div class="form-error"><ul><li>' + json.errors.map(function(x){ return x; }).join('</li><li>') + '</li></ul></div>';
            } else {
              fb.innerHTML = '<div class="form-error">Wystąpił błąd. Spróbuj ponownie.</div>';
            }
          }
        }).catch(function(err){
          fb.innerHTML = '<div class="form-error">Błąd sieci: ' + (err.message || err) + '</div>';
        });
      });
    })();
  </script>

</div>
