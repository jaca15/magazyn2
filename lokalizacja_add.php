<?php
// lokalizacja_add.php - formularz dodawania lokalizacji + obsługa AJAX (dla dashboardu)
// Uruchamiany z dashboardu w modalu lub bezpośrednio.
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


try {
    $magazyny = $pdo->query("SELECT id, nazwa FROM magazyny ORDER BY nazwa")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignoruj */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obsługa zapisu (AJAX POST)
    $errors = [];

    // Pobierz i przytnij pola
    $nazwa = trim((string)($_POST['nazwa'] ?? ''));
    $uwagi = trim((string)($_POST['uwagi'] ?? ''));
    $id_mag = isset($_POST['id_mag']) && $_POST['id_mag'] !== '' ? (int)$_POST['id_mag'] : null;
   

    // Walidacja
    if ($nazwa_s === '') $errors[] = 'Nazwa jest wymagana.';
    if ($id_mag === null) $errors[] = 'Magazyn jest wymagany.';
    
   

  if (!empty($errors)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    
    try {
        $ins = $pdo->prepare("INSERT INTO lokalizacje (nazwa, id_mag, uwagi) VALUES (:nazwa, :id_mag, :uwagi)");
        $ins->execute([
            ':nazwa' => $nazwa,
            ':id_mag' => $id_mag,
            ':uwagi' => $uwagi,
          ]);
        $newId = (int)$pdo->lastInsertId();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Lokalizacja dodana.', 'id' => $newId]);
        exit;
    } catch (PDOException $e) {
        error_log('users add insert error: ' . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd zapisu do bazy danych.']]);
        exit;
    }
}

// Jeśli nie POST - wyświetl formularz (fragment do modal / do wczytania w #content)
?>
<div id="user-add-panel" class="content">
  <h2>Dodaj lokalizację</h2>
  <small class="hint">* - pola wymagane.</small>

  <form id="lokalizacjaAddForm" method="post" action="lokalizacja_add.php" novalidate>
    <div id="form-feedback" class="form-feedback" aria-live="polite"></div>

    <div class="form-row">
      <label for="nazwa">Nazwa<span aria-hidden="true">*</span></label>
      <input id="nazwa" name="nazwa" type="text" required maxlength="100" class="form-control" />
    </div>
<!--
    <div class="form-row">
      <label for="id_mag">Magazyn<span aria-hidden="true">*</span></label>
      <input id="id_mag" name="id_mag" type="text" required maxlength="150" class="form-control" />
    </div>
-->    
    
    <div class="form-row">
      <label for="id_mag">Magazyn:</label>
      <select id="id_mag" name="id_mag" required class="form-control">
        <option value="">Wybierz...</option>
        <?php foreach ($magazyny as $m): ?>
          <option value="<?= h($m['id']) ?>" <?= isset($_POST['id_mag']) && (string)$_POST['id_mag'] === (string)$m['id'] ? 'selected' : '' ?>>
            <?= h($m['nazwa']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="uwagi">Uwagi </span></label>
      <input id="uwagi" name="uwagi" type="text" maxlength="150" class="form-control" />
    </div>



   <div class="form-actions" style="margin-top:12px;">
      <button type="submit" class="btn btn-primary">Zapisz</button>
      <button type="button" class="btn btn-outline" onclick="if(window.closeModal) window.closeModal(); else history.back();">Anuluj</button>
    </div>
  </form>

  <script>
    // Jeśli formularz jest w modalu dashboardu, attachFormHandler w index.js zajmie się submitem.
    // Dla bezpieczeństwa, obsłużimy też lokalny submit jeśli ktoś otworzy formularz bez dashboardu.
    (function(){
      var form = document.getElementById('lokalizacjaAddForm');
      if (!form) return;

      // jeśli submitFormAjax jest dostępny (z index.js), to on zrobi AJAX; w przeciwnym razie wykonaj prosty AJAX tutaj
      form.addEventListener('submit', function(e){
        if (typeof submitFormAjax === 'function') return; // global handler przejmie formularz
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
                // reload users panel if loader available
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