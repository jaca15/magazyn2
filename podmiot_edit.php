<?php
// podmiot_edit.php - formularz edycji podmiotu + zapis (obsługa AJAX/modal i bezpośrednio)
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
        echo json_encode(['success' => false, 'errors' => ['Nieprawidłowy identyfikator podmiotu.']]);
        exit;
    }
    header('Location: podmiot_panel.php');
    exit;
}



// Obsługa POST (zapis zmian)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Pobierz i przytnij pola
    $nazwa_s = trim((string)($_POST['nazwa_s'] ?? ''));
    $nazwa_p = trim((string)($_POST['nazwa_p'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $adres = (string)($_POST['adres'] ?? '');
    $telefon = trim((string)($_POST['telefon'] ?? ''));
    $uwagi = (string)($_POST['uwagi'] ?? '');

    // Walidacja
    if ($nazwa_s === '') $errors[] = 'Nazwa skrócona jest wymagana.';
    if ($nazwa_p === '') $errors[] = 'Nazwa pełna jest wymagana.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Nieprawidłowy format adresu e-mail.';
    if ($adres === '') $errors[] = 'Adres podmiotu jest wymagany.';

   
/*
     // Sprawdź unikalność nazw podmiotów i e-maila
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM podmioty WHERE nazwa_pelna = :nazwa_p");
            $stmt->execute([':nazwa_p' => $nazwa_p]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podmiot o takiej nazwie już istnieje.';
        } catch (Throwable $e) {
            error_log('podmiot add check login error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera podczas sprawdzania nazwy.';
        }
    }
    
   if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM podmioty WHERE nazwa_skrocona = :nazwa_s");
            $stmt->execute([':nazwa_s' => $nazwa_s]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podmiot o takiej nazwie skróconej już istnieje.';
        } catch (Throwable $e) {
            error_log('podmiot add check login error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera podczas sprawdzania nazwy.';
        }
    } 
    
  if ($email !== '' && empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzytkownicy WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podany e-mail jest już używany.';
        } catch (Throwable $e) {
            error_log('podmiot add check email error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera podczas sprawdzania e-maila.';
        }
    }

    if (!empty($errors)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
*/
    // Przygotuj dynamiczny UPDATE
    $fields = [
            'nazwa_skrocona' => $nazwa_s,
            'nazwa_pelna' => $nazwa_p,
            'adres' => $adres,
            'telefon' => $telefon !== '' ? $telefon : null,
            'email' => $email,
            'uwagi' => $uwagi !== '' ? $uwagi : null
    ];

   
    // Zbuduj SQL dynamicznie
    $setParts = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $setParts[] = "$col = :$col";
        $params[":$col"] = $val;
    }
    $params[':id'] = $id;
    $sql = "UPDATE podmioty SET " . implode(', ', $setParts) . " WHERE id = :id";

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
        error_log('podmiot_edit save error: ' . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd zapisu do bazy danych!']]);
        exit;
    }
}

// Jeśli nie POST — pobierz dane podmiotu i wyświetl formularz
try {
    $q = $pdo->prepare("SELECT id, nazwa_skrocona, nazwa_pelna, adres,telefon, email, uwagi FROM podmioty WHERE id = :id LIMIT 1");
    $q->execute([':id' => $id]);
    $podmiot = $q->fetch(PDO::FETCH_ASSOC);
    if (!$podmiot) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['Podmiot nie istnieje.']]);
            exit;
        }
        header('Location: podmiot_panel.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('podmiot_edit fetch error: ' . $e->getMessage());
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd serwera.']]);
        exit;
    }
    header('Location: podmiot_panel.php');
    exit;
}
?>
<div id="user-add-panel" class="content">
  <h2>Edytuj podmiot</h2>
  <small class="hint">* - pola wymagane.</small>

  <form id="podmiotEditForm" method="post" action="podmiot_edit.php" novalidate>
       <input type="hidden" name="id" value="<?= (int)$podmiot['id'] ?>">
    <div id="form-feedback" class="form-feedback" aria-live="polite"></div>

    <div class="form-row">
      <label for="nazwa_p">Nazwa podmiotu (pełna nazwa) <span aria-hidden="true">*</span></label>
      <input id="nazwa_p" name="nazwa_p" type="text" required maxlength="100" class="form-control" value="<?= h($podmiot['nazwa_pelna']) ?>" />
    </div>

    <div class="form-row">
      <label for="nazwa_s">Nazwa skrócona <span aria-hidden="true">*</span></label>
      <input id="nazwa_s" name="nazwa_s" type="text" required maxlength="255" class="form-control" value="<?= h($podmiot['nazwa_skrocona']) ?>" />
    </div>

    <div class="form-row">
      <label for="adres">Adres <span aria-hidden="true">*</span></label>
      <input id="adres" name="adres" type="text" required maxlength="255" class="form-control" value="<?= h($podmiot['adres']) ?>" />
    </div>

     <div class="form-row">
      <label for="telefon">Telefon </span></label>
      <input id="telefon" name="telefon" type="text" maxlength="15" class="form-control" value="<?= h($podmiot['telefon']) ?>" />
    </div>

    <div class="form-row">
      <label for="email">E-mail <span aria-hidden="true">*</span></label>
      <input id="email" name="email" type="email" required maxlength="255" class="form-control" value="<?= h($podmiot['email']) ?>"/>
    </div>

     <div class="form-row">
      <label for="uwagi">Uwagi </label>
      <input id="uwagi" name="uwagi" type="text" maxlength="255" class="form-control" value="<?= h($podmiot['uwagi']) ?>"/>
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
      var form = document.getElementById('podmiotEditForm');
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
                  window.loadContent('podmiot_edit.php');
                }
              } else {
                window.location.href = 'podmiot_edit.php';
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


<!-- skrypt sprawdzania hasła??
  <script>
  (function(){
    // Klientowa walidacja sekcji hasła i obsługa submit (fallback jeśli globalny submitFormAjax nie istnieje)
    var form = document.getElementById('podmiotEditForm');
    var feedback = document.getElementById('form-feedback');
    var passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/;

    function showFeedback(msg, ok) {
      if (!feedback) return;
      feedback.innerHTML = ok ? '<div class="form-success">' + msg + '</div>' : '<div class="form-error">' + msg + '</div>';
    }

    if (!form) return;

    form.addEventListener('submit', function(e){
      // Jeśli globalny handler istnieje, pozwól mu przejąć formularz
      if (typeof submitFormAjax === 'function') return;

      e.preventDefault();
      feedback.innerHTML = '<em>Wysyłanie…</em>';

      var newPass = (document.getElementById('new_password') || {}).value || '';
      var newPass2 = (document.getElementById('new_password_confirm') || {}).value || '';

      if (newPass || newPass2) {
        if (!passwordPattern.test(newPass)) {
          showFeedback('Hasło musi mieć min. 6 znaków, zawierać małą i dużą literę, cyfrę oraz znak specjalny.', false);
          return;
        }
        if (newPass !== newPass2) {
          showFeedback('Hasła nie są zgodne.', false);
          return;
        }
      }

      var fd = new FormData(form);

      fetch(form.getAttribute('action') || window.location.pathname, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: {'X-Requested-With':'XMLHttpRequest'}
      }).then(function(r){ return r.json(); })
      .then(function(json){
        if (json && json.success) {
          showFeedback(json.message || 'Zapisano.', true);
          setTimeout(function(){
            if (typeof window.closeModal === 'function') window.closeModal();
            if (typeof window.loadContent === 'function') window.loadContent('podmiot_panel.php');
            else window.location.href = 'podmiot_panel.php';
          }, 600);
        } else {
          var text = 'Błąd';
          if (json && json.errors && Array.isArray(json.errors)) text = json.errors.join('\n');
          else if (json && json.message) text = json.message;
          showFeedback(text, false);
        }
      }).catch(function(err){
        console.error(err);
        showFeedback('Błąd sieci. Spróbuj ponownie.', false);
      });
    }, false);
  })();
  </script>
  
  -->
  
</div>