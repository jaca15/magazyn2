<?php
// logout.php - wylogowanie użytkownika
// Usuwa sesję, czyści cookie sesyjne i przekierowuje na stronę logowania.

@session_start();

// Wyczyść wszystkie dane sesji
$_SESSION = [];

// Jeśli chcesz usunąć także cookie sesji
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"] ?? '/',
        $params["domain"] ?? '',
        $params["secure"] ?? false,
        $params["httponly"] ?? false
    );
}

// Zniszcz sesję serwera
session_destroy();

// Jeżeli żądanie jest AJAX-owe, zwróć JSON zamiast przekierowania
$isAjax = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'Wylogowano.']);
    exit;
}

// Przekieruj na stronę logowania (dostosuj nazwę jeśli u Ciebie jest inna)
header('Location: logowanie.php');
exit;