<?php
// app_settings.php
// Ustawienia aplikacji — jedno źródło prawdy dla nazwy, autora, wersji,
// ścieżki do obrazu logowania oraz opisu wyświetlanego na ekranie logowania.
//
// Edytuj wartości poniżej zgodnie z potrzebami projektu.
$APP = [
    'name'            => 'Magazynier',
    'author'          => 'j@ca15',
    'version'         => '1.0.0',
    // Ścieżka względna do pliku obrazu używanego na ekranie logowania.
    // Przykład: 'uploads/login.png'. Jeśli plik nie istnieje, używany jest fallback SVG.
    'login_image'     => 'login.png',
    // Opis wyświetlany pod grafiką na ekranie logowania.
    // Możesz tu wkleić wielolinijkowy tekst; w widoku zostanie bezpiecznie wyświetlony
    // (znaki HTML będą ucieczkowane, nowe linie zamienione na <br>).
    'login_description' => "Zaloguj się, aby zarządzać sprzętem i magazynami. Interfejs zoptymalizowany pod kątem prostoty i użyteczności.",
];

// Drobna pomocnicza funkcja do bezpiecznego escape'owania w widokach
if (!function_exists('app_h')) {
    function app_h($v){ return htmlspecialchars($v === null ? '' : $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}