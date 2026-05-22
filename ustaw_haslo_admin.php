<?php
// ustaw_haslo_admin.php - wymusza utworzenie konta 'admin' (graficznie zgodne z logowanie.php)
require_once 'auth.php';
require 'polaczenie.php';
require_once 'app_settings.php';

function h($v){ return htmlspecialchars($v === null ? '' : $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Sprawdź, czy istnieje jakikolwiek administrator w systemie
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM uzytkownicy WHERE rola = 'admin'");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row && $row['cnt'] > 0) {
        // jeśli jakikolwiek admin istnieje, przekieruj do logowania
        header('Location: logowanie.php');
        exit;
    }
} catch (Throwable $e) {
    // brak tabeli lub błąd DB — traktuj jako brak admina, kontynuuj formularz
    error_log('ustaw_haslo_admin.php: błąd sprawdzania admina: ' . $e->getMessage());
}

$blad = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $blad = 'Błąd weryfikacji formularza. Odśwież stronę i spróbuj ponownie.';
    } else {
    $haslo = $_POST['haslo'] ?? '';
    $haslo2 = $_POST['haslo2'] ?? '';

    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/';

    if ($haslo === '' || $haslo2 === '') {
        $blad = 'Wypełnij oba pola hasła.';
    } elseif ($haslo !== $haslo2) {
        $blad = 'Hasła nie są zgodne.';
    } elseif (!preg_match($pattern, $haslo)) {
        $blad = 'Hasło musi mieć min. 6 znaków, zawierać małą i dużą literę, cyfrę oraz znak specjalny.';
    } else {
        $hash = password_hash($haslo, PASSWORD_DEFAULT);
        $username = 'admin';
        try {
            $findAdminUser = $pdo->prepare("SELECT id FROM uzytkownicy WHERE nazwa_uzytkownika = ? LIMIT 1");
            $findAdminUser->execute([$username]);
            $existingAdminUser = $findAdminUser->fetch(PDO::FETCH_ASSOC);

            if ($existingAdminUser) {
                $userId = (int)$existingAdminUser['id'];
                $updateAdminUser = $pdo->prepare("UPDATE uzytkownicy SET haslo_hash = ?, rola = 'admin' WHERE id = ?");
                $updateAdminUser->execute([$hash, $userId]);
            } else {
                $insertAdminUser = $pdo->prepare("INSERT INTO uzytkownicy (nazwa_uzytkownika, haslo_hash, rola) VALUES (?, ?, 'admin')");
                $insertAdminUser->execute([$username, $hash]);
                $userId = (int)$pdo->lastInsertId();
            }

            $_SESSION['user'] = [
                'id' => $userId,
                'nazwa_uzytkownika' => $username,
                'rola' => 'admin'
            ];
            $_SESSION['user_id'] = $userId;
            $_SESSION['nazwa_uzytkownika'] = $username;
            $_SESSION['rola'] = 'admin';
            $_SESSION['wymus_zmiany_hasla'] = 0;
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            error_log('ustaw_haslo_admin.php: błąd przy tworzeniu konta: ' . $e->getMessage());
            $blad = 'Błąd przy tworzeniu konta. Skontaktuj się z administratorem systemu.';
        }
    }
    } // end csrf check
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title><?= h($APP['name'] ?? 'Aplikacja') ?> — Ustaw hasło admina</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{
      --bg:#f3f4f6;
      --panel:#ffffff;
      --muted:#6b7280;
      --accent-2:#111827;
      --error:#b00020;
      --border:#e5e7eb;
    }
    html,body{height:100%;margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--accent-2);}
    .page-center{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;}
    .set-password-modal { width:100%; max-width:540px; background:var(--panel); border-radius:12px; box-shadow:0 10px 40px rgba(2,6,23,0.12); border:1px solid var(--border); padding:32px; box-sizing:border-box; }
    h1 { font-size:1.5rem; margin-bottom:8px; color:var(--accent-2); }
    .info { font-size:0.95rem; color:var(--muted); margin-bottom:20px; }
    form label { display:block; font-size:0.9rem; color:var(--muted); margin-bottom:6px; }
    input[type=password] { width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border); background:#fff; box-sizing:border-box; font-size:0.95rem; color:var(--accent-2); }
    .row { margin-bottom:18px; }
    .btn { display:inline-block; padding:12px 16px; background:#111827; color:#fff; border-radius:8px; border:0; cursor:pointer; font-weight:600; font-size:1rem; text-align:center; width:100%; }
    .error { color:var(--error); margin-bottom:16px; font-weight:600; }
    .small { font-size:0.85rem; color:var(--muted); text-align:center; }
  </style>
</head>
<body>
  <div class="page-center">
    <div class="set-password-modal" role="dialog" aria-modal="true" aria-labelledby="setPasswordTitle">
      <h1 id="setPasswordTitle">Ustaw hasło administratora</h1>

      <p class="info">Nie znaleziono konta <strong>administratora</strong>. Utwórz konto <strong>admin</strong>, podając hasło zgodne z wymaganiami bezpieczeństwa.</p>

      <?php if ($blad): ?>
        <div class="error" role="alert"><?= h($blad) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="row">
          <label for="haslo">Nowe hasło</label>
          <input id="haslo" name="haslo" type="password" required>
        </div>
        <div class="row">
          <label for="haslo2">Powtórz hasło</label>
          <input id="haslo2" name="haslo2" type="password" required>
        </div>
        <p class="small">Hasło musi mieć min. 6 znaków, zawierać małą i dużą literę, cyfrę oraz znak specjalny.</p>
        <button type="submit" class="btn">Utwórz konto admin</button>
      </form>
    </div>
  </div>
</body>
</html>