<?php
require 'auth.php';
require_login();
require_permission('delete_equipment', 'Brak uprawnień do usuwania sprzętu.');
require 'polaczenie.php';

header('Content-Type: application/json');

$request = json_decode(file_get_contents('php://input'), true);
$id = isset($request['id']) ? (int)$request['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Nieprawidłowe ID sprzętu.',
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM sprzet WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Sprzęt został pomyślnie usunięty.',
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nie znaleziono sprzętu o podanym ID.',
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Błąd podczas próby usunięcia: ' . htmlspecialchars($e->getMessage()),
    ]);
}
exit;