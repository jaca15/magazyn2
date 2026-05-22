<?php
// user_add.php - formularz dodawania użytkownika + obsługa AJAX (dla dashboardu)
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
$allowed_roles = ['admin', 'gosc', 'magazynier'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obsługa zapisu (AJAX POST)
    $errors = [];

    // Pobierz i przytnij pola
    $login = trim((string)($_POST['nazwa_uzytkownika'] ?? ''));
    $name = trim((string)($_POST['imie_nazwisko'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = trim((string)($_POST['rola'] ?? 'magazynier'));
    $force_change = isset($_POST['wymus_zmiany_hasla']) && ($_POST['wymus_zmiany_hasla'] == '1' || $_POST['wymus_zmiany_hasla'] === 'on') ? 1 : 0;

    // Walidacja
    if ($login === '') $errors[] = 'Login (nazwa użytkownika) jest wymagany.';
    if ($password === '') $errors[] = 'Hasło jest wymagane.';
    if (strlen($password) < 6) $errors[] = 'Hasło musi mieć co najmniej 6 znaków.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Nieprawidłowy format adresu e-mail.';
    if (!in_array($role, $allowed_roles, true)) $errors[] = 'Nieprawidłowa rola użytkownika.';

    // Sprawdź unikalność loginu i e-maila
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzytkownicy WHERE nazwa_uzytkownika = :login");
            $stmt->execute([':login' => $login]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podany login jest już zajęty.';
        } catch (Throwable $e) {
            error_log('users add check login error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera podczas sprawdzania loginu.';
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

    // Hash hasła i insert
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $ins = $pdo->prepare("INSERT INTO uzytkownicy (nazwa_uzytkownika, imie_nazwisko, email, haslo_hash, rola, wymus_zmiany_hasla, created_at) VALUES (:login, :name, :email, :hash, :role, :force_change, NOW())");
        $ins->execute([
            ':login' => $login,
            ':name' => $name !== '' ? $name : null,
            ':email' => $email !== '' ? $email : null,
            ':hash' => $passwordHash,
            ':role' => $role,
            ':force_change' => $force_change ? 1 : 0
        ]);
        $newId = (int)$pdo->lastInsertId();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Użytkownik dodany.', 'id' => $newId]);
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
  <h2>Dodaj użytkownika</h2>
   
  <form id="userAddForm" method="post" action="user_add.php" novalidate>
    <div id="form-feedback" class="form-feedback" aria-live="polite">
         <small class="hint">* - pola wymagane.</small>
    </div>

    <div class="form-row">
      <label for="nazwa_uzytkownika">Login (nazwa użytkownika) <span aria-hidden="true">*</span></label>
      <input id="nazwa_uzytkownika" name="nazwa_uzytkownika" type="text" required maxlength="100" class="form-control" />
    </div>

    <div class="form-row">
      <label for="imie_nazwisko">Imię i nazwisko</label>
      <input id="imie_nazwisko" name="imie_nazwisko" type="text" maxlength="255" class="form-control" />
    </div>

    <div class="form-row">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" maxlength="255" class="form-control" />
    </div>

    <div class="form-row">
      <label for="password">Hasło <span aria-hidden="true">*</span></label>
      <input id="password" name="password" type="password" required minlength="6" class="form-control" />
      <small class="hint">Hasło minimum 6 znaków.</small>
    </div>

    <div class="form-row">
      <label for="rola">Rola</label>
      <select id="rola" name="rola" class="form-control">
        <?php foreach ($allowed_roles as $r): ?>
          <option value="<?= h($r) ?>" <?= $r === 'magazynier' ? 'selected' : '' ?>><?= h(ucfirst($r)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label>
        <input type="checkbox" name="wymus_zmiany_hasla" value="1" />
        Wymuś zmianę hasła przy pierwszym logowaniu
      </label>
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
      var form = document.getElementById('userAddForm');
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
                  window.loadContent('users_panel.php');
                }
              } else {
                window.location.href = 'users_panel.php';
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