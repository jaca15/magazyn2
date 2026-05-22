<?php
// zmiana_hasla.php - strona do zmiany hasła (wymuszona po resecie lub dobrowolna zmiana)
// Wymaga zalogowania

require_once 'auth.php';
require_login();
require 'polaczenie.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$blad = '';
$komunikat = '';
// Regex: min 6, at least one lower, one upper, one digit, one special
$pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/';

// Pobierz ID użytkownika z sesji (obsługa różnych struktur sesji)
$userId = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null;
if (!$userId) {
    // Brak sesji poprawnie — przekieruj do logowania
    header('Location: logowanie.php');
    exit;
}

// Pobierz aktualny stan wymuszenia zmiany hasła z bazy (bez fatalnych błędów)
try {
    $stmt = $pdo->prepare("SELECT wymus_zmiany_hasla FROM uzytkownicy WHERE id = ?");
    $stmt->execute([(int)$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $wymus = !empty($row['wymus_zmiany_hasla']) || !empty($_SESSION['must_change_password']);
} catch (Throwable $e) {
    error_log('zmiana_hasla.php: Błąd pobrania flagi wymuszenia: ' . $e->getMessage());
    $wymus = !empty($_SESSION['must_change_password']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $haslo = (string)($_POST['haslo'] ?? '');
    $haslo2 = (string)($_POST['haslo2'] ?? '');
    $haslo = trim($haslo);
    $haslo2 = trim($haslo2);

    if ($haslo === '' || $haslo2 === '') {
        $blad = 'Wypełnij oba pola hasła.';
    } elseif ($haslo !== $haslo2) {
        $blad = 'Hasła nie są zgodne.';
    } elseif (!preg_match($pattern, $haslo)) {
        $blad = 'Hasło musi mieć min. 6 znaków, zawierać małą i dużą literę, cyfrę oraz znak specjalny.';
    } else {
        $hash = password_hash($haslo, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("UPDATE uzytkownicy SET haslo_hash = ?, wymus_zmiany_hasla = 0 WHERE id = ?");
            $stmt->execute([$hash, (int)$userId]);
            unset($_SESSION['must_change_password']);
            $komunikat = 'Hasło zmienione pomyślnie. Przekierowanie...';
            // Spróbuj przekierować, jeśli to możliwe
            if (!headers_sent()) {
                header('Location: index.php');
                exit;
            } else {
                // Jeżeli nagłówki już wysłane, JS wykona przekierowanie (poniżej)
            }
        } catch (Throwable $e) {
            error_log('zmiana_hasla.php: Błąd zapisu hasła: ' . $e->getMessage());
            $blad = 'Błąd zapisu hasła (zaloguj błąd w logach).';
        }
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Zmiana hasła</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- Korzystamy z globalnego stylu aplikacji -->
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Małe lokalne poprawki dopasowane do stylu aplikacji */
    body { padding: 18px; background: #f9fafb; }
    main.content { max-width: 760px; margin: 18px auto; }
    header h1 { margin: 0 0 12px; font-size: 1.4rem; color: #222; }
    .form-row { margin-bottom: 12px; }
    label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; }
    .form-control { width: 100%; max-width: 480px; padding: 8px 10px; border:1px solid #d6dde3; border-radius:6px; box-sizing:border-box; }
    .hint { font-size: 0.9rem; color: #6b7280; margin-top:6px; }
    .form-actions { margin-top: 12px; display:flex; gap:8px; align-items:center; }
    .form-error { color: #b00020; background:#fff5f5; padding:8px 10px; border-radius:6px; border:1px solid #f5c2c7; }
    .form-success { color: #0b6623; background:#f1fff3; padding:8px 10px; border-radius:6px; border:1px solid #cfead0; }
    .back-link { margin-top: 12px; display:inline-block; color:#0366d6; text-decoration:none; }
    .back-link:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <main class="content" role="main" aria-labelledby="pageTitle">
    <header><h1 id="pageTitle">Zmiana hasła</h1></header>

    <?php if ($wymus): ?>
      <div class="info-message">Twoje hasło zostało zresetowane przez administratora. Musisz ustawić nowe hasło aby kontynuować.</div>
    <?php else: ?>
      <div class="info-message">Możesz tutaj zmienić swoje hasło.</div>
    <?php endif; ?>

    <?php if ($blad): ?><div class="form-error" id="serverError"><?= h($blad) ?></div><?php endif; ?>
    <?php if ($komunikat): ?><div class="form-success" id="serverOk"><?= h($komunikat) ?></div><?php endif; ?>

    <div id="bladClient" class="form-error" style="display:none; margin-top:8px;"></div>

    <form method="post" id="formZmiana" novalidate>
      <div class="form-row">
        <label for="haslo">Nowe hasło <span aria-hidden="true">*</span></label>
        <input id="haslo" name="haslo" type="password" required autocomplete="new-password" class="form-control" />
      </div>

      <div class="form-row">
        <label for="haslo2">Powtórz nowe hasło <span aria-hidden="true">*</span></label>
        <input id="haslo2" name="haslo2" type="password" required autocomplete="new-password" class="form-control" />
      </div>

      <p class="hint">Wymagania: min. 6 znaków, duża i mała litera, cyfra oraz znak specjalny.</p>

      <div class="form-actions">
        <button type="submit" id="submitBtn" class="btn btn-primary">Zmień hasło</button>
        <a href="index.php" class="btn btn-outline" id="cancelBtn">Anuluj / Powrót</a>
      </div>
    </form>

    <p style="margin-top:14px"><a class="back-link" href="index.php">Powrót do panelu</a></p>
  </main>

<script>
(function(){
  'use strict';
  // Klientowa walidacja: ten sam wzorzec co na serwerze
  var pattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/;

  var form = document.getElementById('formZmiana');
  var errEl = document.getElementById('bladClient');
  var submitBtn = document.getElementById('submitBtn');
  var serverError = document.getElementById('serverError');
  var serverOk = document.getElementById('serverOk');

  function showError(msg){
    if (errEl) {
      errEl.textContent = msg || '';
      errEl.style.display = msg ? 'block' : 'none';
    }
  }
  function clearError(){
    if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
  }

  if (form) {
    form.addEventListener('submit', function(e){
      clearError();
      if (serverError) serverError.style.display = 'none';

      var haslo = (document.getElementById('haslo') || {}).value || '';
      var haslo2 = (document.getElementById('haslo2') || {}).value || '';

      if (!pattern.test(haslo)) {
        showError('Hasło musi mieć min. 6 znaków, zawierać małą i dużą literę, cyfrę oraz znak specjalny.');
        e.preventDefault();
        return false;
      }
      if (haslo !== haslo2) {
        showError('Hasła nie są zgodne.');
        e.preventDefault();
        return false;
      }

      // disable submit to avoid double submit until server responds
      if (submitBtn) submitBtn.disabled = true;
      return true; // allow submit
    }, false);
  }

  // If server-side set $komunikat but headers_sent prevented redirect, auto-redirect after short delay
  if (serverOk && serverOk.textContent.trim().length > 0) {
    setTimeout(function(){ window.location.href = 'index.php'; }, 900);
  }
})();
</script>
</body>
</html>
