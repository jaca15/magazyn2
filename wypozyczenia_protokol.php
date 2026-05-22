<?php
require 'tcpdf/tcpdf.php'; // Załaduj TCPDF 
require_once 'auth.php';
require_once 'polaczenie.php';

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Dane testowe, dynamicznie do zmiany
$sprzet_dane = [
    'nazwa' => 'Laptop Dell XPS 15',
    'numer_inwentarzowy' => 'INV12345',
    'opis' => 'Laptop z Windows 10, 16GB RAM, 512GB SSD',
    'ilosc' => 1,
    'data_zwrotu' => '2026-02-15',
    'uwagi' => 'Sprawdzono stan sprzętu, brak uszkodzeń',
];

$podmiot_dane = [
    'nazwa_pelna' => 'ABC Sp. z o.o.',
    'adres' => 'ul. Przykładowa 5, 00-123 Warszawa',
];

// Ścieżka zapisu wygenerowanego PDF
$plik_sciezka = 'protokoly/wypozyczenie_' . date('Ymd_His') . '.pdf';

// Utwórz nowy PDF
$pdf = new TCPDF();
$pdf->SetCreator('Twoja Aplikacja');
$pdf->SetAuthor('Twoja Firma');
$pdf->SetTitle('Protokół Wypożyczenia Sprzętu');
$pdf->SetSubject('Protokół wypożyczenia');

// Ustawienia dokumentu
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// Nagłówek - Tytuł dokumentu
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'Protokół Wypożyczenia Sprzętu', 0, 1, 'C');
$pdf->Ln(5);

// Dane sprzętu
$pdf->SetFont('dejavusans', '', 12);
$pdf->Cell(0, 10, 'Dane wypożyczanego sprzętu:', 0, 1);
$pdf->Write(6, 'Nazwa: ' . h($sprzet_dane['nazwa']) . "\n");
$pdf->Write(6, 'Nr inwentarzowy: ' . h($sprzet_dane['numer_inwentarzowy']) . "\n");
$pdf->MultiCell(0, 6, 'Opis: ' . h($sprzet_dane['opis']), 0, 1);
$pdf->Write(6, 'Ilość wypożyczona: ' . h($sprzet_dane['ilosc']) . "\n");
$pdf->Write(6, 'Przewidywana data zwrotu: ' . h($sprzet_dane['data_zwrotu']) . "\n");
$pdf->MultiCell(0, 6, 'Uwagi: ' . h($sprzet_dane['uwagi']), 0, 1);
$pdf->Ln(5);

// Dane wypożyczającego
$pdf->Cell(0, 10, 'Dane podmiotu wypożyczającego:', 0, 1);
$pdf->Write(6, 'Pełna nazwa: ' . h($podmiot_dane['nazwa_pelna']) . "\n");
$pdf->MultiCell(0, 6, 'Adres: ' . h($podmiot_dane['adres']), 0, 1);
$pdf->Ln(10);

// Miejsce na podpisy
$pdf->Cell(0, 10, '________________________________       ________________________________', 0, 1, 'L');
$pdf->Cell(0, 10, 'Podpis wypożyczającego                        Podpis osoby wydającej', 0, 1, 'L');

// Zapisywanie pliku PDF
if (!is_dir('protokoly')) {
    mkdir('protokoly', 0777, true);
}
$pdf->Output($plik_sciezka, 'F');

// Informacja dla użytkownika
echo 'Protokół wypożyczenia został wygenerowany: <a href="' . h($plik_sciezka) . '" target="_blank">Pobierz protokół</a>';
?>