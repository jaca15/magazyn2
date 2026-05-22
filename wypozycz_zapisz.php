<?php
// wypozycz_zapisz.php - Zapis wypożyczenia i generacja linku do pliku protokołu PDF
require_once 'auth.php';
require_login();
require_permission('issue_equipment', 'Brak uprawnień do wydawania sprzętu.');
require 'polaczenie.php';

header('Content-Type: application/json; charset=utf-8');

// Funkcja do zwracania odpowiedzi JSON
function resp($ok, $msg = '', $extra = []) {
    $out = array_merge(['success' => $ok, 'message' => $msg], $extra);
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resp(false, 'Zły typ żądania. Oczekiwano POST.');
}

csrf_require();

// Logowanie rozpoczęcia działania skryptu
file_put_contents(__DIR__ . '/logs/debug.log', "Rozpoczęto działanie skryptu wypozycz_zapisz.php\n", FILE_APPEND | LOCK_EX);

// Walidacja danych wejściowych
$sprzet_id = isset($_POST['sprzet_id']) ? (int)$_POST['sprzet_id'] : 0;
$ilosc = isset($_POST['ilosc']) ? (int)$_POST['ilosc'] : 0;
$uzytkownik = isset($_POST['uzytkownik']) ? $_POST['uzytkownik'] : '';
$data_zwrotu = !empty($_POST['data_zwrotu']) ? $_POST['data_zwrotu'] : null;
$uwagi = trim(isset($_POST['uwagi']) ? $_POST['uwagi'] : '');

if ($sprzet_id <= 0) {
    resp(false, 'Nieprawidłowy identyfikator sprzętu.');
}
if ($ilosc <= 0) {
    resp(false, 'Nieprawidłowa ilość sprzętu.');
}

try {
    $pdo->beginTransaction();

    // Sprawdzenie dostępności sprzętu
    $q = $pdo->prepare("SELECT nazwa, ilosc FROM sprzet WHERE id = ? FOR UPDATE");
    $q->execute([$sprzet_id]);
    $row = $q->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        resp(false, 'Podany sprzęt nie istnieje w bazie.');
    }

    if ($row['ilosc'] < $ilosc) {
        $pdo->rollBack();
        resp(false, 'Brak wystarczającej ilości sprzętu w magazynie.');
    }

    // Sprawdzenie istnienia podmiotu
    $qPod = $pdo->prepare("SELECT id FROM podmioty WHERE id = ?");
    $qPod->execute([$uzytkownik]);
    if (!$qPod->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        resp(false, 'Podany podmiot jest nieprawidłowy.');
    }

    // Zapis wypożyczenia w tabeli
    
    
    $insert = $pdo->prepare("
        INSERT INTO wypozyczenia (sprzet_id, ilosc, do_zwrotu, uzytkownik, data_wypozyczenia, data_zwrotu, uwagi)
        VALUES (?, ?, ?, ?, NOW(), ?, ?)
    ");
    $insert->execute([$sprzet_id, $ilosc, $ilosc, $uzytkownik, $data_zwrotu, $uwagi]);

    $lastId = $pdo->lastInsertId();
    if (!$lastId) {
        $pdo->rollBack();
        resp(false, 'Nie udało się pobrać ID ostatniego wypożyczenia.');
    }

    // Aktualizacja ilości sprzętu w magazynie
    $update = $pdo->prepare("UPDATE sprzet SET ilosc = ilosc - ? WHERE id = ?");
    $update->execute([$ilosc, $sprzet_id]);

    $pdo->commit();

    // Zbuduj link do protokołu PDF — klient otworzy go sam
    $baseURL = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/';
    $protokol_pdf = "{$baseURL}protokol_wydania.php?id={$lastId}";

    // Zwracanie odpowiedzi JSON
    resp(true, 'Wypożyczono sprzęt pomyślnie.', [
        'protokol_pdf' => $protokol_pdf
    ]);

} catch (Throwable $e) {
    // Wycofanie transakcji w przypadku błędu
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Logowanie błędu
    file_put_contents(__DIR__ . '/logs/error.log', "Błąd: {$e->getMessage()}\n", FILE_APPEND | LOCK_EX);

    // Zwrócenie błędu w odpowiedzi JSON
    resp(false, 'Wystąpił błąd podczas przetwarzania żądania.');
}