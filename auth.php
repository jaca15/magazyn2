<?php
// auth.php - pomocnicze funkcje autoryzacji
// Użyj: require 'auth.php'; potem require_login(); aby chronić stronę.

if (session_status() === PHP_SESSION_NONE) {
    // Bezpieczna konfiguracja sesji i ciasteczka (httponly, samesite, strict mode)
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    // Ustaw session.cookie_secure = 1 gdy serwer działa wyłącznie przez HTTPS
    // ini_set('session.cookie_secure', '1');
    session_start();
}

function zalogowany_user() {
    return $_SESSION['user'] ?? null;
}

function czy_zalogowany() {
    return (bool) zalogowany_user();
}

function require_login() {
    if (!czy_zalogowany()) {
        header('Location: logowanie.php');
        exit;
    }
}

function is_ajax_request(): bool {
    return (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        !empty($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false
    );
}

function current_user_id(): int {
    return (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
}

function rola_uzytkownika(): string {
    return strtolower((string)($_SESSION['user']['rola'] ?? $_SESSION['rola'] ?? ''));
}

function czy_admin() {
    return rola_uzytkownika() === 'admin';
}

function ma_uprawnienie(string $permission): bool {
    $rola = rola_uzytkownika();
    if ($rola === 'admin') {
        return true;
    }

    switch ($permission) {
        case 'manage_equipment':
        case 'issue_equipment':
        case 'return_equipment':
            return $rola === 'magazynier';
        case 'delete_equipment':
        case 'manage_users':
        case 'manage_dictionaries':
            return false;
        case 'edit_own_profile':
            return in_array($rola, ['admin', 'magazynier', 'gosc'], true);
        default:
            return false;
    }
}

function deny_access(string $message = 'Brak uprawnień.'): void {
    http_response_code(403);
    if (is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
    exit;
}

function require_permission(string $permission, string $message = 'Brak uprawnień.'): void {
    if (!ma_uprawnienie($permission)) {
        deny_access($message);
    }
}

function can_edit_user_profile(int $targetUserId): bool {
    if (czy_admin()) {
        return true;
    }
    return current_user_id() > 0 && current_user_id() === $targetUserId;
}

function require_admin() {
    if (!czy_admin()) {
        deny_access("Brak uprawnień (wymagane konto administratora).");
    }
}

// ===== Ochrona CSRF =====

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf_token(), (string)$token);
}

function csrf_require(): void {
    if (!csrf_validate()) {
        if (is_ajax_request()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Błąd weryfikacji formularza. Odśwież stronę i spróbuj ponownie.']);
        } else {
            http_response_code(403);
            echo 'Błąd weryfikacji formularza (CSRF). Odśwież stronę i spróbuj ponownie.';
        }
        exit;
    }
}
?>