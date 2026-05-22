<?php
require_once 'auth.php';
require_login();
require_permission('issue_equipment', 'Brak uprawnień do wydawania sprzętu.');
require 'polaczenie.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'Nieprawidłowy id'; exit; }

// Pobierz dane sprzętu
$stmt = $pdo->prepare("SELECT id, nazwa, ilosc FROM sprzet WHERE id = ?");
$stmt->execute([$id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$s) { http_response_code(404); echo 'Brak sprzętu'; exit; }

// Pobierz listę podmiotów
$podmioty = [];
try {
    $q = $pdo->query("SELECT id, nazwa_skrocona FROM podmioty ORDER BY nazwa_skrocona");
    $podmioty = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Jeśli błąd, lista będzie pusta (fallback do pola tekstowego użytkownika)
}

// Domyślny wybór podmiotu na podstawie sesji
$defaultPodmiotId = $_SESSION['podmiot_id'] ?? $_SESSION['user']['podmiot_id'] ?? null;

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wypożycz sprzęt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #d0d7de;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 24px;
            color: #333;
        }
        p {
            margin-bottom: 15px;
            font-size: 16px;
            color: #555;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="date"],
        select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }
        
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }
        
        button {
            display: inline-block;
            padding: 10px 15px;
            font-size: 16px;
            color: #fff;
            background-color: #0d6efd;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }
        button:hover {
            background-color: #0b5ed7;
        }
        .btn-secondary {
            background-color: #6c757d;
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Wypożycz sprzęt</h2>
        <p><strong><?= h($s['nazwa']) ?></strong> — dostępnych: <?= (int)$s['ilosc'] ?></p>

        <form method="post" action="wypozycz_zapisz.php">
            <input type="hidden" name="sprzet_id" value="<?= (int)$s['id'] ?>">

            <!-- Ilość do wypożyczenia -->
            <label for="ilosc">Ilość do wypożyczenia:</label>
            <input type="number" id="ilosc" name="ilosc" min="1" max="<?= (int)$s['ilosc'] ?>" value="1" required>

            <!-- Wybór podmiotu (lista rozwijana lub fallback) -->
            <?php if (!empty($podmioty)): ?>
                <label for="podmiot">Podmiot (wypożyczający):</label>
                <select id="podmiot" name="uzytkownik" required>
                    <?php foreach ($podmioty as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $defaultPodmiotId == $p['id'] ? 'selected' : '' ?>>
                            <?= h($p['nazwa_skrocona']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <label for="uzytkownik">Użytkownik (brak listy podmiotów):</label>
                <input type="text" id="uzytkownik" name="uzytkownik" value="<?= h($_SESSION['user']['nazwa_uzytkownika'] ?? '') ?>" required>
            <?php endif; ?>

            <!-- Data zwrotu -->
            <label for="data-zwrotu">Data zwrotu (opcjonalnie):</label>
            <input type="date" id="data-zwrotu" name="data_zwrotu">

            <!-- Uwagi -->
            <label for="uwagi">Uwagi (opcjonalnie):</label>
            <textarea id="uwagi" name="uwagi"><?= $_POST['uwagi'] ?></textarea>
            
            
            
            
            <!-- Przycisk wyślij i anuluj -->
            <button type="submit">Wypożycz</button>
            <button type="button" class="btn-secondary" onclick="window.close();">Anuluj</button>
        </form>
    </div>
</body>
</html>