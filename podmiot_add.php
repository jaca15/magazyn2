<?php
// podmiot_add.php - formularz dodawania podmiotu + obsługa AJAX (dla dashboardu)
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

// Dozwolone role (zgodne ze strukturą bazy)
//$allowed_roles = ['admin', 'gosc', 'magazynier'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // Obsługa zapisu (AJAX POST)
    $errors = [];

    // Pobierz i przytnij pola
    $nazwa_s = trim((string)($_POST['nazwa_skrocona'] ?? ''));
    $nazwa_p = trim((string)($_POST['nazwa_pelna'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $adres = (string)($_POST['adres'] ?? '');
    $telefon = trim((string)($_POST['telefon'] ?? ''));
    $uwagi = (string)($_POST['uwagi'] ?? '');

    // Walidacja
    if ($nazwa_s === '') $errors[] = 'Nazwa skrócona jest wymagana.';
    if ($nazwa_p === '') $errors[] = 'Nazwa pełna jest wymagana.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Nieprawidłowy format adresu e-mail.';
    if ($adres === '') $errors[] = 'Adres podmiotu jest wymagany.';

    // Sprawdź unikalność nazw podmiotów i e-maila
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM podmioty WHERE nazwa_pelna = :nazwa_p");
            $stmt->execute([':nazwa_p' => $nazwa_p]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podmiot o takiej nazwie już istnieje.';
        } catch (Throwable $e) {
            error_log('users add check login error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera podczas sprawdzania nazwy.';
        }
    }
    
   if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM podmioty WHERE nazwa_skrocona = :nazwa_s");
            $stmt->execute([':nazwa_s' => $nazwa_s]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podmiot o takiej nazwie skróconej już istnieje.';
        } catch (Throwable $e) {
            error_log('users add check login error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera podczas sprawdzania nazwy.';
        }
    } 
    
  if ($email !== '' && empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzytkownicy WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podany e-mail jest już używany.';
        } catch (Throwable $e) {
            error_log('users add check email error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera podczas sprawdzania e-maila.';
        }
    }

    if (!empty($errors)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    
    try {
        $ins = $pdo->prepare("INSERT INTO podmioty (nazwa_skrocona, nazwa_pelna, adres, telefon, email, uwagi, created_at) VALUES (:nazwa_s, :nazwa_p, :adres, :telefon, :email, :uwagi, NOW())");
        $ins->execute([
            ':nazwa_s' => $nazwa_s,
            ':nazwa_p' => $nazwa_p,
            ':adres' => $adres,
            ':telefon' => $telefon,
            ':email' => $email,
            ':uwagi' => $uwagi,
        ]);
        $newId = (int)$pdo->lastInsertId();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Podmiot dodany.', 'id' => $newId]);
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
  <h2>Dodaj podmiot</h2>
  <small class="hint">* - pola wymagane.</small>

  <form id="podmiotAddForm" method="post" action="podmiot_add.php" novalidate>
    <?= csrf_field() ?>
    <div id="form-feedback" class="form-feedback" aria-live="polite"></div>

    <div class="form-row">
      <label for="nazwa_pelna">Nazwa podmiotu (pełna nazwa) <span aria-hidden="true">*</span></label>
      <input id="nazwa_pelna" name="nazwa_pelna" type="text" required maxlength="100" class="form-control" />
    </div>

    <div class="form-row">
      <label for="nazwa_skrocona">Nazwa skrócona <span aria-hidden="true">*</span></label>
      <input id="nazwa_skrocona" name="nazwa_skrocona" type="text" required maxlength="255" class="form-control" />
    </div>

    <div class="form-row">
      <label for="adres">Adres <span aria-hidden="true">*</span></label>
      <input id="adres" name="adres" type="text" required maxlength="255" class="form-control" />
    </div>

     <div class="form-row">
      <label for="telefon">Telefon </span></label>
      <input id="telefon" name="telefon" type="text" maxlength="15" class="form-control" />
    </div>

    <div class="form-row">
      <label for="email">E-mail <span aria-hidden="true">*</span></label>
      <input id="email" name="email" type="email" required maxlength="255" class="form-control" />
    </div>

     <div class="form-row">
      <label for="uwagi">Uwagi </label>
      <input id="uwagi" name="uwagi" type="text" maxlength="255" class="form-control" />
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
      var form = document.getElementById('podmiotAddForm');
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
                  window.loadContent('podmiot_panel.php');
                }
              } else {
                window.location.href = 'podmiot_panel.php';
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