<?php
// user_edit.php - formularz edycji użytkownika + zapis (obsługa AJAX/modal i bezpośrednio)
require_once 'auth.php';
require_login();
require 'polaczenie.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function is_ajax(): bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
    return false;
}

$allowed_roles = ['admin', 'gosc', 'magazynier'];
$password_pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/';

$currentUserId = current_user_id();
$isAdmin = czy_admin();
if ($currentUserId <= 0) {
    deny_access('Brak aktywnej sesji użytkownika.');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    $id = $currentUserId;
}

if (!can_edit_user_profile($id)) {
    deny_access('Możesz edytować wyłącznie własny profil.');
}

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
        header('Location: index.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('user_edit fetch error: ' . $e->getMessage());
    if (is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd serwera.']]);
        exit;
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $errors = [];

    $login = $isAdmin ? trim((string)($_POST['nazwa_uzytkownika'] ?? '')) : (string)$user['nazwa_uzytkownika'];
    $name = trim((string)($_POST['imie_nazwisko'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $role = $isAdmin ? trim((string)($_POST['rola'] ?? 'magazynier')) : (string)$user['rola'];
    $force_change = $isAdmin
        ? (isset($_POST['wymus_zmiany_hasla']) && ($_POST['wymus_zmiany_hasla'] == '1' || $_POST['wymus_zmiany_hasla'] === 'on') ? 1 : 0)
        : (int)$user['wymus_zmiany_hasla'];

    $newPassword = trim((string)($_POST['new_password'] ?? ''));
    $newPasswordConfirm = trim((string)($_POST['new_password_confirm'] ?? ''));
    $passwordProvided = $newPassword !== '' || $newPasswordConfirm !== '';

    if ($isAdmin && $login === '') $errors[] = 'Login (nazwa użytkownika) jest wymagany.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Nieprawidłowy adres e-mail.';
    if ($isAdmin && !in_array($role, $allowed_roles, true)) $errors[] = 'Nieprawidłowa rola użytkownika.';

    if ($passwordProvided) {
        if ($newPassword === '' || $newPasswordConfirm === '') {
            $errors[] = 'Oba pola nowego hasła są wymagane.';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Nowe hasła nie są zgodne.';
        } elseif (!preg_match($password_pattern, $newPassword)) {
            $errors[] = 'Hasło musi mieć min. 6 znaków, zawierać małą i dużą literę, cyfrę oraz znak specjalny.';
        }
    }

    if ($isAdmin && empty($errors)) {
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

    $fields = [
        'imie_nazwisko' => $name !== '' ? $name : null,
        'email' => $email !== '' ? $email : null,
    ];

    if ($isAdmin) {
        $fields['nazwa_uzytkownika'] = $login;
        $fields['rola'] = $role;
        $fields['wymus_zmiany_hasla'] = $force_change ? 1 : 0;
    }

    if ($passwordProvided) {
        $fields['haslo_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    $setParts = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $setParts[] = "$col = :$col";
        $params[":$col"] = $val;
    }
    $params[':id'] = $id;

    try {
        $upd = $pdo->prepare("UPDATE uzytkownicy SET " . implode(', ', $setParts) . " WHERE id = :id");
        $upd->execute($params);

        if ($id === $currentUserId) {
            if ($isAdmin && isset($fields['nazwa_uzytkownika'])) {
                $_SESSION['nazwa_uzytkownika'] = $fields['nazwa_uzytkownika'];
                $_SESSION['user']['nazwa_uzytkownika'] = $fields['nazwa_uzytkownika'];
            }
            if ($isAdmin && isset($fields['rola'])) {
                $_SESSION['rola'] = $fields['rola'];
                $_SESSION['user']['rola'] = $fields['rola'];
            }
        }

        $redirectUrl = ($isAdmin && $id !== $currentUserId) ? 'users_panel.php' : ('user_edit.php?id=' . $id);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Zapisano zmiany.', 'redirect_url' => $redirectUrl]);
        exit;
    } catch (Throwable $e) {
        error_log('user_edit save error: ' . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'errors' => ['Błąd zapisu do bazy danych.']]);
        exit;
    }
}

$isOwnProfile = $id === $currentUserId;
$canManageAccount = $isAdmin;
$panelTitle = ($isOwnProfile || !$isAdmin) ? 'Mój profil' : 'Edytuj użytkownika';
?>
<div id="user-edit-panel" class="content">
  <h2><?= h($panelTitle) ?></h2>

  <form id="userEditForm" method="post" action="user_edit.php" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

    <div id="form-feedback" class="form-feedback" aria-live="polite"></div>

    <div class="form-row">
      <label for="nazwa_uzytkownika">Login (nazwa użytkownika)</label>
      <input
        id="nazwa_uzytkownika"
        name="nazwa_uzytkownika"
        type="text"
        required
        maxlength="100"
        class="form-control"
        value="<?= h($user['nazwa_uzytkownika']) ?>"
        <?= $canManageAccount ? '' : 'aria-describedby="login-help"' ?>
        <?= $canManageAccount ? '' : 'readonly' ?>
      >
      <?php if (!$canManageAccount): ?>
      <small id="login-help" class="hint">Login nie może być zmieniony dla tej roli.</small>
      <?php endif; ?>
    </div>

    <div class="form-row">
      <label for="imie_nazwisko">Imię i nazwisko</label>
      <input id="imie_nazwisko" name="imie_nazwisko" type="text" maxlength="255" class="form-control" value="<?= h($user['imie_nazwisko']) ?>">
    </div>

    <div class="form-row">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" maxlength="255" class="form-control" value="<?= h($user['email']) ?>">
    </div>

    <?php if ($canManageAccount): ?>
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
    <?php endif; ?>

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
    var form = document.getElementById('userEditForm');
    var feedback = document.getElementById('form-feedback');
    var passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/;

    function showFeedback(msg, ok) {
      if (!feedback) return;
      feedback.innerHTML = ok ? '<div class="form-success">' + msg + '</div>' : '<div class="form-error">' + msg + '</div>';
    }

    if (!form) return;

    form.addEventListener('submit', function(e){
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
          if (json.message) alert(json.message);
          setTimeout(function(){
            var target = (json && json.redirect_url) ? json.redirect_url : 'user_edit.php?id=<?= (int)$id ?>';
            if (typeof window.closeModal === 'function') window.closeModal();
            if (typeof window.loadContent === 'function') window.loadContent(target);
            else window.location.href = target;
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
