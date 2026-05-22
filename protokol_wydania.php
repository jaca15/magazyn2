<?php
require_once 'auth.php';
require_login();
require_permission('issue_equipment', 'Brak uprawnień do generowania protokołu wydania.');
require 'polaczenie.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Walidacja ID wypożyczenia
if ($id <= 0) {
    http_response_code(400);
    exit('Nieprawidłowy identyfikator wypożyczenia.');
}

// Pobranie danych wypożyczenia z bazy danych
try {
    $query = $pdo->prepare("
        SELECT w.id, w.sprzet_id, w.ilosc, w.data_wypozyczenia, w.data_zwrotu, w.uwagi,
               s.nazwa AS sprzet_nazwa, s.numer_inwentarzowy, p.nazwa_pelna AS podmiot_nazwa
        FROM wypozyczenia w
        JOIN sprzet s ON w.sprzet_id = s.id
        JOIN podmioty p ON w.uzytkownik = p.id
        WHERE w.id = ?
    ");
    $query->execute([$id]);
    $wypozyczenie = $query->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('protokol_wydania fetch error: ' . $e->getMessage());
    http_response_code(500);
    exit('Błąd serwera podczas pobierania danych wypożyczenia.');
}

if (!$wypozyczenie) {
    http_response_code(404);
    exit('Nie znaleziono wypożyczenia o podanym ID.');
}

// Konfiguracja katalogu zapisu pliku PDF
$folderAbsolutny = __DIR__ . '/protokoly';

// Tworzenie folderu na pliki PDF, jeśli nie istnieje
if (!is_dir($folderAbsolutny)) {
    if (!mkdir($folderAbsolutny, 0750, true) && !is_dir($folderAbsolutny)) {
        error_log('protokol_wydania: nie można utworzyć katalogu protokoly/');
        http_response_code(500);
        exit('Błąd konfiguracji serwera — skontaktuj się z administratorem.');
    }
}
if (!is_writable($folderAbsolutny)) {
    error_log('protokol_wydania: brak praw zapisu w katalogu protokoly/');
    http_response_code(500);
    exit('Błąd konfiguracji serwera — skontaktuj się z administratorem.');
}

// Ścieżki pliku
$nazwaPliku = 'wypozyczenie_' . $id . '.pdf';
$sciezkaAbsolutna = $folderAbsolutny . '/' . $nazwaPliku;

// Sprawdź dostępność biblioteki TCPDF
$tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    error_log('protokol_wydania: brak biblioteki TCPDF w ' . $tcpdfPath);
    http_response_code(501);
    exit('Generowanie PDF jest niedostępne — skontaktuj się z administratorem.');
}

require $tcpdfPath;

// Generowanie PDF-a
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, 'Protokół Wypożyczenia', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('dejavusans', '', 12);
$pdf->Cell(0, 10, 'Nazwa sprzętu: ' . $wypozyczenie['sprzet_nazwa'], 0, 1);
$pdf->Cell(0, 10, 'Numer inwentarzowy: ' . $wypozyczenie['numer_inwentarzowy'], 0, 1);
$pdf->Cell(0, 10, 'Ilość wypożyczona: ' . $wypozyczenie['ilosc'], 0, 1);
$pdf->Cell(0, 10, 'Podmiot wypożyczający: ' . $wypozyczenie['podmiot_nazwa'], 0, 1);
$pdf->Cell(0, 10, 'Data wypożyczenia: ' . $wypozyczenie['data_wypozyczenia'], 0, 1);
$pdf->Cell(0, 10, 'Przewidywana data zwrotu: ' . ($wypozyczenie['data_zwrotu'] ?: 'Brak'), 0, 1);

$pdf->Output($sciezkaAbsolutna, 'F'); // Zapis pliku do katalogu

// Otwieranie pliku PDF w przeglądarce
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nazwaPliku . '"');
header('Content-Length: ' . filesize($sciezkaAbsolutna));
readfile($sciezkaAbsolutna);
