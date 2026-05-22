<?php
// dodaj_sprzet.php - formularz + backend dla "Dodaj sprzęt"
// Zmiany:
// - pola pogrupowane w sekcje zgodnie z prośbą
// - pola renderowane obok etykiet (label + kontrolka jako rodzeństwo w .form-row)
// - obsługa uploadu do katalogu 'zdjecia'
// - przystosowane do ładowania jako fragment do modala (AJAX) lub bezpośrednio

require 'auth.php';
require_login();
require_permission('manage_equipment', 'Brak uprawnień do dodawania sprzętu.');
require 'polaczenie.php';

function h($v) {
    return htmlspecialchars($v === null ? '' : $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_ajax(): bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        return true;
    }
    return false;
}

// Pobierz opcje do selectów (nie przerywamy działania przy błędzie pobierania - formularz nadal pokaże puste selecty)
$kategorie = $lokalizacje = $magazyny = [];
try {
    $kategorie = $pdo->query("SELECT id, nazwa FROM kategorie ORDER BY nazwa")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignoruj */ }
try {
    $lokalizacje = $pdo->query("SELECT id, nazwa FROM lokalizacje ORDER BY nazwa")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignoruj */ }
try {
    $magazyny = $pdo->query("SELECT id, nazwa FROM magazyny ORDER BY nazwa")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignoruj */ }

// Obsługa POST (zapis)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // Dane podstawowe
    $nazwa = trim($_POST['nazwa'] ?? '');
    $kategoria_id = isset($_POST['kategoria_id']) && $_POST['kategoria_id'] !== '' ? (int)$_POST['kategoria_id'] : null;
    $numer_inwentarzowy = trim($_POST['numer_inwentarzowy'] ?? null);
    $numer_seryjny = trim($_POST['numer_seryjny'] ?? null);
    $opis = trim($_POST['opis'] ?? null);

    // Dane dotyczące nabycia
    $data_zakupu = !empty($_POST['data_zakupu']) ? $_POST['data_zakupu'] : null;
    $nabycie = trim($_POST['nabycie'] ?? null);
    $ilosc = isset($_POST['ilosc']) && $_POST['ilosc'] !== '' ? (int)$_POST['ilosc'] : 0;
    $wartosc = isset($_POST['wartosc']) && $_POST['wartosc'] !== '' ? (float)str_replace(',', '.', $_POST['wartosc']) : null;

    // Miejsce przechowywania
    $magazyn_id = isset($_POST['magazyn_id']) && $_POST['magazyn_id'] !== '' ? (int)$_POST['magazyn_id'] : null;
    $lokalizacja_id = isset($_POST['lokalizacja_id']) && $_POST['lokalizacja_id'] !== '' ? (int)$_POST['lokalizacja_id'] : null;
    $uwagi = trim($_POST['uwagi'] ?? null);

    // Dane dodatkowe
    $waga = isset($_POST['waga']) && $_POST['waga'] !== '' ? (float)str_replace(',', '.', $_POST['waga']) : null;
    $wymiary = trim($_POST['wymiary'] ?? null);

    $errors = [];

    // Walidacja podstawowa
    if ($nazwa === '') $errors[] = 'Nazwa jest wymagana.';
    if ($kategoria_id === null) $errors[] = 'Kategoria jest wymagana.';
    if ($magazyn_id === null) $errors[] = 'Magazyn jest wymagany.';
    if ($lokalizacja_id === null) $errors[] = 'Lokalizacja jest wymagana.';
    if ($ilosc < 0) $errors[] = 'Ilość nie może być ujemna.';
    if ($wartosc !== null && $wartosc < 0) $errors[] = 'Cena jednostkowa nie może być ujemna.';

    // Obsługa pliku (katalog 'zdjecia') - najpierw walidujemy, ale zapisujemy dopiero po INSERT (żeby mieć ID)
    $hasUpload = (!empty($_FILES['zdjecie']) && $_FILES['zdjecie']['error'] !== UPLOAD_ERR_NO_FILE);
    $uploadExt = null;

    if ($hasUpload) {
        $f = $_FILES['zdjecie'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Nieobsługiwany format zdjęcia. Dozwolone: ' . implode(', ', $allowed);
            } else {
                $uploadExt = $ext;
                $uploadDir = __DIR__ . '/zdjecia';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $errors[] = 'Nie udało się utworzyć katalogu zdjecia.';
                    }
                }
                if (empty($errors) && !is_writable($uploadDir)) {
                    $errors[] = 'Katalog zdjecia nie jest zapisowalny. Ustaw prawa zapisu.';
                }
            }
        } else {
            $errors[] = 'Błąd przesyłania pliku (kod: ' . (int)$f['error'] . ').';
        }
    }

    if (!empty($errors)) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        } else {
            echo '<div class="form-error"><ul><li>' . implode('</li><li>', array_map('h', $errors)) . '</li></ul></div>';
        }
    } else {
        // Zapis do bazy (najpierw bez zdjęcia, potem update po uploadzie z nazwą zawierającą nazwa+id)
        try {
            $sql = "INSERT INTO sprzet
                (nazwa, opis, ilosc, ilosc_calkowita, kategoria_id, lokalizacja_id, magazyn_id, numer_inwentarzowy, numer_seryjny, nabycie, uwagi, data_zakupu, waga, wartosc, wymiary, zdjecie, data_dodania)
                VALUES
                (:nazwa, :opis, :ilosc, :ilosc_calkowita, :kategoria_id, :lokalizacja_id, :magazyn_id, :numer_inwentarzowy, :numer_seryjny, :nabycie, :uwagi, :data_zakupu, :waga, :wartosc, :wymiary, :zdjecie, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nazwa' => $nazwa,
                ':opis' => $opis,
                ':ilosc' => $ilosc,
                ':ilosc_calkowita' => $ilosc,
                ':kategoria_id' => $kategoria_id,
                ':lokalizacja_id' => $lokalizacja_id,
                ':magazyn_id' => $magazyn_id,
                ':numer_inwentarzowy' => $numer_inwentarzowy,
                ':numer_seryjny' => $numer_seryjny,
                ':nabycie' => $nabycie,
                ':uwagi' => $uwagi,
                ':data_zakupu' => $data_zakupu ?: null,
                ':waga' => $waga,
                ':wartosc' => $wartosc,
                ':wymiary' => $wymiary,
                ':zdjecie' => null,
            ]);
            $lastId = (int)$pdo->lastInsertId();

            // Upload zdjęcia po INSERT (mamy ID)
            $zdjecie_db = null;
            if ($hasUpload && $uploadExt) {
                $f = $_FILES['zdjecie'];

                // slug z nazwy sprzętu (bez polskich znaków -> bezpieczny filename)
                $base = $nazwa;
                $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
                if ($base === false) $base = $nazwa;
                $base = strtolower($base);
                $base = preg_replace('/[^a-z0-9]+/i', '-', $base);
                $base = trim($base, '-');
                if ($base === '') $base = 'sprzet';

                $basename = $base . '-' . $lastId . '.' . $uploadExt;

                $uploadDir = __DIR__ . '/zdjecia';
                $target = $uploadDir . '/' . $basename;

                if (!move_uploaded_file($f['tmp_name'], $target)) {
                    // jeżeli upload nie wyjdzie, nie blokujemy dodania sprzętu – tylko zwracamy błąd
                    // (opcjonalnie: można tu zrobić rollback i usunąć rekord)
                    $errors[] = 'Sprzęt dodany, ale wystąpił błąd zapisu zdjęcia.';
                } else {
                    $zdjecie_db = 'zdjecia/' . $basename;

                    // update rekordu
                    $u = $pdo->prepare("UPDATE sprzet SET zdjecie = :z WHERE id = :id");
                    $u->execute([':z' => $zdjecie_db, ':id' => $lastId]);
                }
            }

            if (is_ajax()) {
                header('Content-Type: application/json; charset=utf-8');
                if (!empty($errors)) {
                    echo json_encode(['success' => false, 'errors' => $errors]);
                } else {
                    echo json_encode(['success' => true, 'message' => 'Sprzęt dodany.', 'id' => $lastId]);
                }
                exit;
            } else {
                if (!empty($errors)) {
                    echo '<div class="form-error"><ul><li>' . implode('</li><li>', array_map('h', $errors)) . '</li></ul></div>';
                } else {
                    echo '<div class="form-success">Sprzęt został dodany (ID: ' . h($lastId) . ').</div>';
                }
            }
        } catch (PDOException $e) {
            $msg = 'Błąd zapisu: ' . $e->getMessage();
            if (is_ajax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'errors' => [$msg]]);
                exit;
            } else {
                echo '<div class="form-error">' . h($msg) . '</div>';
            }
        }
    }
}

// --- Formularz (fragment HTML) ---
?>
<form id="dodaj-sprzet-form" action="dodaj_sprzet.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
  <h2 id="modal-title">Dodaj nowy sprzęt</h2>

  <!-- Dane podstawowe -->
  <div class="panel">
    <h3>Dane podstawowe</h3>

    <div class="form-row">
      <label for="nazwa" class="required">Nazwa:</label>
      <input type="text" id="nazwa" name="nazwa" required maxlength="255" value="<?= h($_POST['nazwa'] ?? '') ?>">
    </div>

    <div class="form-row">
      <label for="kategoria_id" class="required">Kategoria:</label>
      <select id="kategoria_id" name="kategoria_id" required>
        <option value="">Wybierz...</option>
        <?php foreach ($kategorie as $k): ?>
          <option value="<?= h($k['id']) ?>" <?= isset($_POST['kategoria_id']) && (string)$_POST['kategoria_id'] === (string)$k['id'] ? 'selected' : '' ?>>
            <?= h($k['nazwa']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="numer_inwentarzowy">Nr inwentarzowy:</label>
      <input type="text" id="numer_inwentarzowy" name="numer_inwentarzowy" maxlength="100" value="<?= h($_POST['numer_inwentarzowy'] ?? '') ?>">
    </div>

    <div class="form-row">
      <label for="numer_seryjny">Nr seryjny:</label>
      <input type="text" id="numer_seryjny" name="numer_seryjny" maxlength="255" value="<?= h($_POST['numer_seryjny'] ?? '') ?>">
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label for="opis">Opis:</label>
      <textarea id="opis" name="opis"><?= h($_POST['opis'] ?? '') ?></textarea>
    </div>
  </div>

  <!-- Dane dotyczące nabycia -->
  <div class="panel">
    <h3>Dane dotyczące nabycia</h3>

    <div class="form-row">
      <label for="data_zakupu">Data zakupu:</label>
      <input type="date" id="data_zakupu" name="data_zakupu" value="<?= h($_POST['data_zakupu'] ?? '') ?>">
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label for="nabycie">Podstawa nabycia:</label>
      <textarea id="nabycie" name="nabycie"><?= h($_POST['nabycie'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <label for="ilosc" class="required">Ilość:</label>
      <input type="number" id="ilosc" name="ilosc" min="0" required value="<?= h($_POST['ilosc'] ?? 0) ?>">
    </div>

    <div class="form-row">
      <label for="wartosc" class="required">Cena jednostkowa (zł):</label>
      <input type="text" id="wartosc" name="wartosc" required value="<?= h($_POST['wartosc'] ?? '') ?>">
    </div>
  </div>

  <!-- Miejsce przechowywania -->
  <div class="panel">
    <h3>Miejsce przechowywania</h3>

    <div class="form-row">
      <label for="magazyn_id" class="required">Magazyn:</label>
      <select id="magazyn_id" name="magazyn_id" required>
        <option value="">Wybierz...</option>
        <?php foreach ($magazyny as $m): ?>
          <option value="<?= h($m['id']) ?>" <?= isset($_POST['magazyn_id']) && (string)$_POST['magazyn_id'] === (string)$m['id'] ? 'selected' : '' ?>>
            <?= h($m['nazwa']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="lokalizacja_id" class="required">Lokalizacja:</label>
      <select id="lokalizacja_id" name="lokalizacja_id" required>
        <option value="">Wybierz...</option>
        <?php foreach ($lokalizacje as $l): ?>
          <option value="<?= h($l['id']) ?>" <?= isset($_POST['lokalizacja_id']) && (string)$_POST['lokalizacja_id'] === (string)$l['id'] ? 'selected' : '' ?>>
            <?= h($l['nazwa']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label for="uwagi">Uwagi:</label>
      <textarea id="uwagi" name="uwagi"><?= h($_POST['uwagi'] ?? '') ?></textarea>
    </div>
  </div>

  <!-- Dane dodatkowe -->
  <div class="panel">
    <h3>Dane dodatkowe</h3>

    <div class="form-row">
      <label for="waga">Waga (kg):</label>
      <input type="text" id="waga" name="waga" value="<?= h($_POST['waga'] ?? '') ?>">
    </div>

    <div class="form-row">
      <label for="wymiary">Wymiary (WxSxG cm):</label>
      <input type="text" id="wymiary" name="wymiary" maxlength="255" value="<?= h($_POST['wymiary'] ?? '') ?>">
    </div>
  </div>

  <!-- Zdjęcie -->
  <div class="panel">
    <h3>Zdjęcie</h3>
    <div class="form-row">
      <label for="zdjecie">Plik zdjęcia:</label>
      <input type="file" id="zdjecie" name="zdjecie" accept="image/*">
    </div>
  </div>

  <!-- Akcje -->
  <div class="form-row" style="justify-content:flex-end;">
    <button type="submit" class="btn-save">Zapisz</button>
    <button type="button" class="btn-cancel" onclick="window.closeModal && window.closeModal()" style="margin-left:8px;">Anuluj</button>
  </div>

  <div id="form-feedback" style="margin-top:10px;"></div>
</form>