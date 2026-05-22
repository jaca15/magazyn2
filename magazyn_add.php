<?php
// magazyn_add.php - formularz dodawania magazynów + obsługa AJAX (dla dashboardu)
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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // Obsługa zapisu (AJAX POST)
    $errors = [];

    // Pobierz i przytnij pola
    $nazwa = trim((string)($_POST['nazwa'] ?? ''));
    $adres = trim((string)($_POST['adres'] ?? ''));
    $zarzadzajacy = trim((string)($_POST['zarzadzajacy'] ?? ''));
    $kontakt = trim((string)($_POST['kontakt'] ?? ''));
    $opis = trim((string)($_POST['opis'] ?? ''));
   

    // Walidacja
    if ($nazwa_s === '') $errors[] = 'Nazwa jest wymagana.';
   

    // Sprawdź unikalność nazw magazynów
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM magazyny WHERE nazwa = :nazwa");
            $stmt->execute([':nazwa' => $nazwa]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Magazyn o takiej nazwie już istnieje.';
        } catch (Throwable $e) {
            error_log('users add check login error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera podczas sprawdzania nazwy.';
        }
    }
    
   
    
  if (!empty($errors)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    
    try {
        $ins = $pdo->prepare("INSERT INTO magazyny (nazwa, adres, opis, zarzadzajacy, kontakt) VALUES (:nazwa, :adres, :opis, :zarzadzajacy, :kontakt)");
        $ins->execute([
            ':nazwa' => $nazwa,
            ':adres' => $adres,
            ':opis' => $opis,
            ':zarzadzajacy' => $zarzadzajacy,
            ':kontakt' => $kontakt,
        ]);
        $newId = (int)$pdo->lastInsertId();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Magazyn dodany.', 'id' => $newId]);
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
  <h2>Dodaj magazyn</h2>
  <small class="hint">* - pola wymagane.</small>

  <form id="magazynAddForm" method="post" action="magazyn_add.php" novalidate>
    <?= csrf_field() ?>
    <div id="form-feedback" class="form-feedback" aria-live="polite"></div>

    <div class="form-row">
      <label for="nazwa">Nazwa<span aria-hidden="true">*</span></label>
      <input id="nazwa" name="nazwa" type="text" required maxlength="100" class="form-control" />
    </div>

    <div class="form-row">
      <label for="adres">Adres<span aria-hidden="true">*</span></label>
      <input id="adres" name="adres" type="text" required maxlength="150" class="form-control" />
    </div>
    
    <div class="form-row">
      <label for="zarzadzajacy">Zarządzający <span aria-hidden="true">*</span></label>
      <input id="zarzadzajacy" name="zarzadzajacy" required type="text" maxlength="150" class="form-control" />
    </div>
    
    <div class="form-row">
      <label for="kontakt">Kontakt </label>
      <input id="kontakt" name="kontakt" type="text" maxlength="255" class="form-control" />
    </div>
    
    <div class="form-row">
      <label for="opis">Opis </label>
      <input id="opis" name="opis" type="text" maxlength="255" class="form-control" />
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
      var form = document.getElementById('magazynAddForm');
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
                  window.loadContent('magazyn_panel.php');
                }
              } else {
                window.location.href = 'magazyn_panel.php';
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