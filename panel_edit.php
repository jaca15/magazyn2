<?php
// DEPRECATED: Ten plik jest duplikatem user_edit.php i zostaje zachowany wyłącznie ze względów
// na kompatybilność wsteczną. Nowe odwołania powinny używać user_edit.php.
// user_edit.php - formularz edycji użytkownika + zapis (obsługa AJAX/modal i bezpośrednio)
// Rozszerzenie: dodana sekcja zmiany hasła (opcjonalna) oraz walidacja po stronie serwera.
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

// Dozwolone role (zgodne z definicją bazy)
$allowed_roles = ['admin', 'gosc', 'magazynier'];

// Pobierz id użytkownika (GET lub POST)
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Nieprawidłowy identyfikator użytkownika.']]);
        exit;
    }
    header('Location: users_panel.php');
    exit;
}

// Wzorzec hasła (ten sam co w zmiana_hasla.php)
$password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/';

// Obsługa POST (zapis zmian)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    $login = trim((string)($_POST['nazwa_uzytkownika'] ?? ''));
    $name = trim((string)($_POST['imie_nazwisko'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $role = trim((string)($_POST['rola'] ?? 'magazynier'));
    $force_change = isset($_POST['wymus_zmiany_hasla']) && ($_POST['wymus_zmiany_hasla'] == '1' || $_POST['wymus_zmiany_hasla'] === 'on') ? 1 : 0;

    // Hasło opcjonalne (jeśli admin chce ustawić nowe hasło)
    $newPassword = (string)($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');
    $newPassword = trim($newPassword);
    $newPasswordConfirm = trim($newPasswordConfirm);
    $passwordProvided = $newPassword !== '' || $newPasswordConfirm !== '';

    if ($login === '') $errors[] = 'Login (nazwa użytkownika) jest wymagany.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Nieprawidłowy adres e-mail.';
    if (!in_array($role, $allowed_roles, true)) $errors[] = 'Nieprawidłowa rola użytkownika.';

    // Jeśli podano nowe hasło — sprawdź zgodność i wzorzec
    if ($passwordProvided) {
        if ($newPassword === '' || $newPasswordConfirm === '') {
            $errors[] = 'Oba pola nowego hasła są wymagane.';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Nowe hasła nie są zgodne.';
        } elseif (!preg_match($password_pattern, $newPassword)) {
            $errors[] = 'Hasło musi mieć min. 6 znaków, zawierać małą i dużą literę, cyfrę oraz znak specjalny.';
        }
    }

    // Unikalność loginu i e-maila (wykluczamy aktualnego użytkownika)
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzytkownicy WHERE nazwa_uzytkownika = :login AND id != :id");
            $stmt->execute([':login' => $login, ':id' => $id]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podany login jest już zajęty przez innego użytkownika.';
        } catch (Throwable $e) {
            error_log('user_edit check login error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera przy sprawdzaniu loginu.';
        }
    }

    if ($email !== '' && empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM uzytkownicy WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $email, ':id' => $id]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Podany e-mail jest już używany przez innego użytkownika.';
        } catch (Throwable $e) {
            error_log('user_edit check email error: ' . $e->getMessage());
            $errors[] = 'Błąd serwera przy sprawdzaniu e-maila.';
        }
    }

    if (!empty($errors)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // Przygotuj dynamiczny UPDATE - jeśli podano hasło, dołączamy kolumnę haslo_hash
    $fields = [
        'nazwa_uzytkownika' => $login,
        'imie_nazwisko' => $name !== '' ? $name : null,
        'email' => $email !== '' ? $email : null,
        'rola' => $role,
        'wymus_zmiany_hasla' => $force_change ? 1 : 0
    ];

    if ($passwordProvided) {
        $fields['haslo_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    // Zbuduj SQL dynamicznie
    $setParts = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $setParts[] = "$col = :$col";
        $params[":$col"] = $val;
    }
    $params[':id'] = $id;
    $sql = "UPDATE uzytkownicy SET " . implode(', ', $setParts) . " WHERE id = :id";

    try {
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        // Przygotuj odpowiedź
        $resp = ['success' => true, 'message' => 'Zapisano zmiany.'];
        if ($passwordProvided) $resp['password_changed'] = true;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($resp);
        exit;
    } catch (Throwable $e) {
        error_log('user_edit save error: ' . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd zapisu do bazy danych.']]);
        exit;
    }
}

// Jeśli nie POST — pobierz dane użytkownika i wyświetl formularz
try {
    $q = $pdo->prepare("SELECT id, nazwa_uzytkownika, imie_nazwisko, email, rola, wymus_zmiany_hasla FROM uzytkownicy WHERE id = :id LIMIT 1");
    $q->execute([':id' => $id]);
    $user = $q->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['Użytkownik nie istnieje.']]);
            exit;
        }
        header('Location: users_panel.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('user_edit fetch error: ' . $e->getMessage());
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd serwera.']]);
        exit;
    }
    header('Location: users_panel.php');
    exit;
}
?>
<div id="user-edit-panel" class="content">
  <h2>Edytuj użytkownika</h2>

  <form id="userEditForm" method="post" action="user_edit.php" novalidate>
    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

    <div id="form-feedback" class="form-feedback" aria-live="polite"></div>

    <div class="form-row">
      <label for="nazwa_uzytkownika">Login (nazwa użytkownika)</label>
      <input id="nazwa_uzytkownika" name="nazwa_uzytkownika" type="text" required maxlength="100" class="form-control" value="<?= h($user['nazwa_uzytkownika']) ?>">
    </div>

    <div class="form-row">
      <label for="imie_nazwisko">Imię i nazwisko</label>
      <input id="imie_nazwisko" name="imie_nazwisko" type="text" maxlength="255" class="form-control" value="<?= h($user['imie_nazwisko']) ?>">
    </div>

    <div class="form-row">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" maxlength="255" class="form-control" value="<?= h($user['email']) ?>">
    </div>

    <div class="form-row">
      <label for="rola">Rola</label>
      <select id="rola" name="rola" class="form-control">
        <?php foreach ($allowed_roles as $r): ?>
          <option value="<?= h($r) ?>" <?= ($r === $user['rola']) ? 'selected' : '' ?>><?= h(ucfirst($r)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label>
        <input type="checkbox" name="wymus_zmiany_hasla" value="1" <?= !empty($user['wymus_zmiany_hasla']) ? 'checked' : '' ?>>
        Wymuś zmianę hasła przy następnym logowaniu
      </label>
    </div>

    <!-- Sekcja: zmiana hasła (opcjonalna) -->
    <fieldset style="margin-top:12px; padding:10px; border:1px solid #e6eef8; border-radius:6px; background:#fbfdff;">
      <legend style="font-weight:600; padding:0 6px;">Ustaw/zmień hasło (opcjonalnie)</legend>

      <div class="form-row">
        <label for="new_password">Nowe hasło (pozostaw puste, aby nie zmieniać)</label>
        <input id="new_password" name="new_password" type="password" autocomplete="new-password" class="form-control" placeholder="Wpisz nowe hasło (opcjonalne)">
      </div>

      <div class="form-row">
        <label for="new_password_confirm">Powtórz nowe hasło</label>
        <input id="new_password_confirm" name="new_password_confirm" type="password" autocomplete="new-password" class="form-control" placeholder="Powtórz nowe hasło">
      </div>

      <p class="hint">Jeśli ustawiasz nowe hasło: min. 6 znaków, mała i duża litera, cyfra oraz znak specjalny.</p>
    </fieldset>

    <div class="form-actions" style="margin-top:12px;">
      <button type="submit" class="btn btn-primary">Zapisz</button>
      <button type="button" class="btn btn-outline" onclick="if(window.closeModal) window.closeModal(); else history.back();">Anuluj</button>
    </div>
  </form>

  <script>
  (function(){
    // Klientowa walidacja sekcji hasła i obsługa submit (fallback jeśli globalny submitFormAjax nie istnieje)
    var form = document.getElementById('userEditForm');
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
            if (typeof window.loadContent === 'function') window.loadContent('users_panel.php');
            else window.location.href = 'users_panel.php';
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
</div>