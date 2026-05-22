<?php
// logowanie.php - uproszczone i bardziej odporne okno logowania korzystające z ustawień w app_settings.php
require 'polaczenie.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'app_settings.php';

function h($v){ return htmlspecialchars($v === null ? '' : $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Jeśli użytkownik jest już zalogowany, przekieruj do panelu
if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Jeśli nie ma konta admin (login=admin, rola=admin) - przekieruj do ustawienia hasła admin
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM uzytkownicy WHERE nazwa_uzytkownika = ? AND rola = 'admin'");
    $stmt->execute(['admin']);
    $row = $stmt->fetch();
    if (!$row || (int)$row['cnt'] === 0) {
        header('Location: ustaw_haslo_admin.php');
        exit;
    }
} catch (Throwable $e) {
    error_log('logowanie.php: błąd sprawdzania adminów: ' . $e->getMessage());
    // Jeśli nie można sprawdzić (np. brak tabeli przy świeżej instalacji) → ustaw hasło admina
    header('Location: ustaw_haslo_admin.php');
    exit;
}

$blad = '';
$last_user = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nazwa = trim((string)($_POST['nazwa'] ?? ''));
    $haslo = (string)($_POST['haslo'] ?? '');
    $last_user = $nazwa;

    if ($nazwa === '' || $haslo === '') {
        $blad = 'Uzupełnij nazwę użytkownika i hasło.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, nazwa_uzytkownika, haslo_hash, rola, wymus_zmiany_hasla FROM uzytkownicy WHERE nazwa_uzytkownika = ? LIMIT 1");
            $stmt->execute([$nazwa]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($u && !empty($u['haslo_hash']) && password_verify($haslo, $u['haslo_hash'])) {
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id' => (int)$u['id'],
                    'nazwa_uzytkownika' => $u['nazwa_uzytkownika'],
                    'rola' => $u['rola']
                ];

                $_SESSION['user_id'] = (int)$u['id'];
                $_SESSION['nazwa_uzytkownika'] = $u['nazwa_uzytkownika'];
                $_SESSION['rola'] = $u['rola'];
                $_SESSION['wymus_zmiany_hasla'] = !empty($u['wymus_zmiany_hasla']) ? 1 : 0;

                if (!empty($u['wymus_zmiany_hasla'])) {
                    $_SESSION['must_change_password'] = true;
                    header('Location: zmiana_hasla.php');
                    exit;
                }

                header('Location: index.php');
                exit;
            }

            $blad = 'Nieprawidłowa nazwa użytkownika lub hasło.';
        } catch (Throwable $e) {
            error_log('logowanie.php: ' . $e->getMessage());
            $blad = 'Błąd serwera przy logowaniu.';
        }
    }
}

$appName = $APP['name'] ?? 'Aplikacja';
$appAuthor = $APP['author'] ?? '';
$appVersion = $APP['version'] ?? '';
$loginImageRel = !empty($APP['login_image']) ? $APP['login_image'] : '';
$loginImageFull = $loginImageRel ? __DIR__ . DIRECTORY_SEPARATOR . $loginImageRel : '';
$loginImageExists = $loginImageFull ? file_exists($loginImageFull) : false;
$loginImageSrc = $loginImageExists ? ($loginImageRel . '?t=' . @filemtime($loginImageFull)) : '';
$loginDescription = isset($APP['login_description']) ? (string)$APP['login_description'] : '';
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title><?= h($appName) ?> — Logowanie</title>
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
    *{box-sizing:border-box;}
    html,body{
      height:100%;
      margin:0;
      font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
      background:var(--bg);
      color:var(--accent-2);
    }
    body{
      min-height:100vh;
    }
    .page-center{
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px;
    }
    .login-card{
      width:100%;
      max-width:520px;
      background:var(--panel);
      border:1px solid var(--border);
      border-radius:14px;
      box-shadow:0 10px 40px rgba(2,6,23,0.12);
      padding:28px;
    }
    .brand{
      font-weight:700;
      font-size:1.35rem;
      margin:0 0 6px 0;
      text-align:center;
    }
    .tag{
      color:var(--muted);
      font-size:0.95rem;
      margin:0 0 18px 0;
      text-align:center;
    }
    .illustration-wrap{
      margin:0 0 18px 0;
      text-align:center;
    }
    .illustration{
      width:100%;
      max-width:320px;
      height:auto;
      max-height:220px;
      display:inline-block;
      object-fit:contain;
    }
    .desc{
      color:var(--muted);
      font-size:0.92rem;
      line-height:1.5;
      margin:0 0 18px 0;
      text-align:center;
    }
    h2{
      margin:0 0 14px 0;
      font-size:1.4rem;
      text-align:center;
    }
    form label{
      display:block;
      font-size:0.92rem;
      color:var(--muted);
      margin-bottom:6px;
    }
    input[type=text], input[type=password]{
      width:100%;
      padding:12px 14px;
      border-radius:8px;
      border:1px solid var(--border);
      background:#fff;
      font-size:0.98rem;
      color:var(--accent-2);
    }
    .row{
      margin-bottom:14px;
    }
    .actions{
      display:flex;
      flex-direction:column;
      gap:10px;
      margin-top:10px;
    }
    .btn{
      display:inline-block;
      width:100%;
      padding:12px 14px;
      background:#111827;
      color:#fff;
      border-radius:8px;
      border:0;
      cursor:pointer;
      font-weight:600;
      text-align:center;
      text-decoration:none;
    }
    .muted{
      color:var(--muted);
      font-size:0.9rem;
      text-align:center;
    }
    .error{
      color:var(--error);
      margin-bottom:12px;
      font-weight:600;
      text-align:center;
    }
    .foot{
      margin-top:18px;
      font-size:0.85rem;
      color:var(--muted);
      text-align:center;
    }
  </style>
</head>
<body>
  <div class="page-center">
    <div class="login-card" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
      <div class="brand"><?= h($appName) ?></div>
      <div class="tag">System zarządzania zasobami — autor: <?= h($appAuthor) ?></div>

      <div class="illustration-wrap">
        <?php if ($loginImageSrc): ?>
          <img class="illustration" src="<?= h($loginImageSrc) ?>" alt="Grafika logowania">
        <?php else: ?>
          <svg class="illustration" viewBox="0 0 640 360" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">
            <defs>
              <linearGradient id="g1" x1="0" x2="1" y1="0" y2="1">
                <stop offset="0" stop-color="#eef2f7"/>
                <stop offset="1" stop-color="#f8fafc"/>
              </linearGradient>
            </defs>
            <rect width="640" height="360" rx="12" fill="url(#g1)"></rect>
            <g transform="translate(80,40)" fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="0" y="0" width="480" height="220" rx="8" fill="#fff"/>
              <g transform="translate(18,18)" stroke="#e2e8f0">
                <rect x="0" y="0" width="180" height="48" rx="6" fill="#f8fafc"></rect>
                <rect x="0" y="70" width="420" height="12" rx="6" fill="#f1f5f9"></rect>
                <rect x="0" y="92" width="360" height="12" rx="6" fill="#f1f5f9"></rect>
                <rect x="0" y="114" width="200" height="12" rx="6" fill="#f1f5f9"></rect>
                <circle cx="380" cy="24" r="18" fill="#fff"></circle>
                <path d="M340 170c20-18 48-18 68 0" stroke="#cbd5e1" stroke-width="3" fill="none"></path>
              </g>
            </g>
          </svg>
        <?php endif; ?>
      </div>

      <?php if ($loginDescription !== ''): ?>
        <div class="desc">
          <?= nl2br(h($loginDescription)) ?>
        </div>
      <?php endif; ?>

      <h2 id="loginTitle">Logowanie</h2>

      <?php if ($blad): ?>
        <div class="error" role="alert"><?= h($blad) ?></div>
      <?php endif; ?>

      <form action="logowanie.php" method="post" novalidate>
        <div class="row">
          <label for="nazwa">Nazwa użytkownika</label>
          <input id="nazwa" name="nazwa" type="text" value="<?= h($last_user) ?>" autocomplete="username" required>
        </div>

        <div class="row">
          <label for="haslo">Hasło</label>
          <input id="haslo" name="haslo" type="password" autocomplete="current-password" required>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Zaloguj</button>
        </div>
      </form>

      <p style="margin-top:14px;" class="muted">Wersja aplikacji: <?= h($appVersion) ?></p>
      <div class="foot">Masz problem z dostępem? Skontaktuj się z administratorem.</div>
    </div>
  </div>

<script>
(function(){
  var user = document.getElementById('nazwa');
  if (user) user.focus();
})();
</script>
</body>
</html>
