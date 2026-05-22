<?php
require 'tcpdf/tcpdf.php';
require 'polaczenie.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Walidacja ID wypożyczenia
if ($id <= 0) {
    die('Nieprawidłowy identyfikator wypożyczenia.');
}

// Pobranie danych wypożyczenia z bazy danych
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

if (!$wypozyczenie) {
    die('Nie znaleziono wypożyczenia o podanym ID.');
}

// Konfiguracja katalogu zapisu pliku PDF
$folderRelatywny = "protokoly"; // Relatywny folder projektu
$folderAbsolutny = __DIR__ . "/$folderRelatywny";

// Tworzenie folderu na pliki PDF, jeśli nie istnieje
if (!is_dir($folderAbsolutny)) {
    mkdir($folderAbsolutny, 0777, true);
}
if (!is_writable($folderAbsolutny)) {
    die("Brak praw zapisu w katalogu: $folderRelatywny.");
}

// Ścieżki pliku
$nazwaPliku = "wypozyczenie_{$id}.pdf";
$sciezkaAbsolutna = "$folderAbsolutny/$nazwaPliku"; // Ścieżka na serwerze
$sciezkaURL = "$folderRelatywny/$nazwaPliku"; // Publiczny link URL

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
header('Content-Disposition: inline; filename='.$sciezkaAbsolutna);
header('Content-Length: ' . filesize($sciezkaAbsolutna));
readfile($sciezkaAbsolutna);
