<?php
// zwrot_zapisz.php - Obsługa zwrotu sprzętu
require_once 'auth.php';
require_login();
require_permission('return_equipment', 'Brak uprawnień do przyjmowania zwrotów.');
require 'polaczenie.php';

header('Content-Type: application/json; charset=utf-8');

function respond($success, $message, $results = []) {
    echo json_encode(['success' => $success, 'message' => $message, 'results' => $results]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Zły typ żądania. Oczekiwano POST.');
}

$ids = $_POST['ids'] ?? [];
$qtys = $_POST['qtys'] ?? [];
$uwagiZwroty = $_POST['uwagi_zw'] ?? [];

// Walidacja wejścia
if (empty($ids) || empty($qtys) || !is_array($ids) || !is_array($qtys)) {
    respond(false, 'Nieprawidłowe dane wejściowe.');
}

if (count($ids) !== count($qtys)) {
    respond(false, 'Znaleziono niezgodność między listą ID a ilościami.');
}

// Przygotowanie odpowiedzi dla klienta
$results = [];
$pdo->beginTransaction();
try {
    foreach ($ids as $index => $id) {
        $id = (int)$id;
        $qty = (int)$qtys[$index];
        $uwagaZwrot = trim($uwagiZwroty[$id] ?? '');

        // Pobierz dane wypożyczenia i sprzętu
        $stmt = $pdo->prepare("
            SELECT w.ilosc AS ilosc_wypozyczona, w.sprzet_id, w.status, w.do_zwrotu AS aktualna_ilosc_sprzetu, s.nazwa 
            FROM wypozyczenia w
            JOIN sprzet s ON s.id = w.sprzet_id
            WHERE w.id = :id
            FOR UPDATE
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $results[] = ['wyp_id' => $id, 'success' => false, 'message' => 'Nie znaleziono wypożyczenia o podanym ID.'];
            continue;
        }
        $sprzetId = (int)$row['sprzet_id'];
//*****        
        $stmt1 = $pdo->prepare("
            SELECT s.ilosc AS stan_magazynowy 
            FROM sprzet s
            WHERE s.id = :id_sprzet
            ");
        $stmt1->execute([':id_sprzet' => $sprzetId]);
        $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);
        
        $stan = $row1['stan_magazynowy'];
        $stan_aktualny = $stan + $qty;
//********        

        // Walidacja ilości
//        $sprzetId = (int)$row['sprzet_id'];
        $aktualnaIloscSprzetu = (int)$row['aktualna_ilosc_sprzetu'];
        $iloscdozwrotu = (int)$row['aktualna_ilosc_sprzetu'];
        $iloscWypozyczona = (int)$row['ilosc_wypozyczona'];
        $nazwaSprzetu = $row['nazwa'];

        if ($qty < 1 || $qty > $iloscWypozyczona) {
            $results[] = ['wyp_id' => $id, 'success' => false, 'message' => 'Nieprawidłowa ilość zwrotu.'];
            continue;
        }

        // Zaktualizuj rekord sprzętu (dodaj ilość zwracaną do dostępnej ilości)
        $nowaIloscSprzetu = $aktualnaIloscSprzetu + $qty;
        $iloscdozwrotu = $iloscdozwrotu -$qty; 
        $stmtUpdateSprzet = $pdo->prepare("
            UPDATE sprzet
            SET ilosc = :nowa_ilosc
            WHERE id = :sprzet_id
        ");

        $stmtUpdateSprzet->execute([
            ':nowa_ilosc' => $stan_aktualny,
            ':sprzet_id' => $sprzetId
        ]);


//        $stmtUpdateSprzet->execute([
//            ':nowa_ilosc' => $nowaIloscSprzetu,
//            ':sprzet_id' => $sprzetId
//        ]);

        // Zaktualizuj rekord wypożyczenia (dodaj uwagi do zwrotu i zmień status)
        $nowailospozwrocie = $aktualnaIloscSprzetu -$qty; 
        $statusZwrotu = ($qty === $aktualnaIloscSprzetu) ? 'zwrócono' : 'zwroty częściowe';
        $stmtUpdateWypozyczenia = $pdo->prepare("
            UPDATE wypozyczenia
            SET do_zwrotu = :do_zwrotu, data_zwrotu = NOW(), uwagi_zw = :uwagi_zw, status = :status  
            WHERE id = :id
        ");

        $stmtUpdateWypozyczenia->execute([
            ':do_zwrotu' => $iloscdozwrotu,
            ':uwagi_zw' => $uwagaZwrot,
            ':status' => $statusZwrotu,
            ':id' => $id
        ]);

        // Sukces zwrotu
        $results[] = [
            'wyp_id' => $id,
            'success' => true,
            'message' => $qty === $iloscWypozyczona
                ? "Zwrócono cały sprzęt: $nazwaSprzetu."
                : "Zwrócono $qty szt. sprzętu: $nazwaSprzetu."
        ];
    }


    $pdo->commit();
    respond(true, 'Zwrot zakończony sukcesem.' . $stan, $results);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Błąd podczas obsługi zwrotów: ' . $e->getMessage());
    respond(false, 'Wystąpił błąd podczas zapisywania zwrotów.');
}