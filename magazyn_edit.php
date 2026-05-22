<?php
// magazyn_edit.php - formularz edycji magazynu + zapis (obsługa AJAX/modal i bezpośrednio)
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



// Pobierz id podmiotu (GET lub POST)
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Nieprawidłowy identyfikator magazynu.']]);
        exit;
    }
    header('Location: magazyn_panel.php');
    exit;
}



// Obsługa POST (zapis zmian)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Pobierz i przytnij pola
    $nazwa = trim((string)($_POST['nazwa'] ?? ''));
    $opis = trim((string)($_POST['opis'] ?? ''));
    $adres = trim((string)($_POST['adres'] ?? ''));
    $zarzadzajacy = trim((string)($_POST['zarzadzajacy'] ?? ''));
    $kontakt = trim((string)($_POST['kontakt'] ?? ''));
   

    // Walidacja
    if ($nazwa === '') $errors[] = 'Nazwa jest wymagana.';
    if ($zarzadzajacy === '') $errors[] = 'Wskazanie zarządzającego jest wymagane.';
    if ($adres === '') $errors[] = 'Adres jest wymagany.';
    

   

    // Przygotuj dynamiczny UPDATE
    $fields = [
            'nazwa' => $nazwa,
            'adres' => $adres,
            'opis' => $opis,
            'zarzadzajacy' => $zarzadzajacy,
            'kontakt' => $kontakt
    ];

   
    // Zbuduj SQL dynamicznie
    $setParts = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $setParts[] = "$col = :$col";
        $params[":$col"] = $val;
    }
    $params[':id'] = $id;
    $sql = "UPDATE magazyny SET " . implode(', ', $setParts) . " WHERE id = :id";

    try {
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        // Przygotuj odpowiedź
        $resp = ['success' => true, 'message' => 'Zapisano zmiany.'];
      //  if ($passwordProvided) $resp['password_changed'] = true;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        exit;
    } catch (Throwable $e) {
        error_log('magazyn_edit save error: ' . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd zapisu do bazy danych!']]);
        exit;
    }
}

// Jeśli nie POST — pobierz dane kategorii i wyświetl formularz
try {
    $q = $pdo->prepare("SELECT id, nazwa, adres, opis, zarzadzajacy, kontakt FROM magazyny WHERE id = :id LIMIT 1");
    $q->execute([':id' => $id]);
    $magazyn = $q->fetch(PDO::FETCH_ASSOC);
    if (!$magazyn) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['Magazyn nie istnieje.']]);
            exit;
        }
        header('Location: magazyn_panel.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('kategoria_edit fetch error: ' . $e->getMessage());
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd serwera.']]);
        exit;
    }
    header('Location: magazyn_panel.php');
    exit;
}
?>
<div id="user-add-panel" class="content">
  <h2>Edytuj magazyn</h2>
  <small class="hint">* - pola wymagane.</small>

  <form id="magazynEditForm" method="post" action="magazyn_edit.php" novalidate>
       <input type="hidden" name="id" value="<?= (int)$magazyn['id'] ?>">
    <div id="form-feedback" class="form-feedback" aria-live="polite"></div>

    <div class="form-row">
      <label for="nazwa">Nazwa <span aria-hidden="true">*</span></label>
      <input id="nazwa" name="nazwa" type="text" required maxlength="100" class="form-control" value="<?= h($magazyn['nazwa']) ?>" />
    </div>

    <div class="form-row">
      <label for="adres">Adres </span></label>
      <input id="adres" name="adres" type="text" required maxlength="255" class="form-control" value="<?= h($magazyn['adres']) ?>" />
    </div>
    
    <div class="form-row">
      <label for="opis">Opis </span></label>
      <input id="opis" name="opis" type="text" required maxlength="255" class="form-control" value="<?= h($magazyn['opis']) ?>" />
    </div>
    
    <div class="form-row">
      <label for="zarzadzajacy">Zarządzający </span></label>
      <input id="zarzadzajacy" name="zarzadzajacy" type="text" required maxlength="255" class="form-control" value="<?= h($magazyn['zarzadzajacy']) ?>" />
    </div>
    
    <div class="form-row">
      <label for="kontakt">Kontakt </span></label>
      <input id="kontakt" name="kontakt" type="text" required maxlength="255" class="form-control" value="<?= h($magazyn['kontakt']) ?>" />
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
      var form = document.getElementById('magazynEditForm');
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
                  window.loadContent('magazyn_edit.php');
                }
              } else {
                window.location.href = 'magazyn_edit.php';
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