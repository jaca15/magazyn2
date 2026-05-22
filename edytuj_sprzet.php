<?php
// edytuj_sprzet.php
// Fragment formularza edycji sprzętu — przeznaczony do załadowania do modala (openModal).
// Obsługuje GET (wyświetlenie formularza) i POST (aktualizacja, zwraca JSON gdy AJAX).
//
// Wymagane: auth.php (require_login()), polaczenie.php (ustawia $pdo)

require 'auth.php';
require_login();
require_permission('manage_equipment', 'Brak uprawnień do edycji sprzętu.');
require 'polaczenie.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function is_ajax(): bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
    return false;
}

// Pobierz listy do selectów
$kategorie = $magazyny = $lokalizacje = [];
try { $kategorie = $pdo->query("SELECT id, nazwa FROM kategorie ORDER BY nazwa")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
try { $magazyny = $pdo->query("SELECT id, nazwa FROM magazyny ORDER BY nazwa")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
try { $lokalizacje = $pdo->query("SELECT id, nazwa FROM lokalizacje ORDER BY nazwa")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}

// ID rekordu
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($id <= 0) {
    echo '<div class="form-error">Nieprawidłowy identyfikator rekordu.</div>';
    exit;
}

// Pobierz istniejące dane
try {
    $q = $pdo->prepare("SELECT * FROM sprzet WHERE id = :id LIMIT 1");
    $q->execute([':id' => $id]);
    $existing = $q->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        echo '<div class="form-error">Nie znaleziono rekordu o podanym identyfikatorze.</div>';
        exit;
    }
} catch (PDOException $e) {
    echo '<div class="form-error">Błąd bazy danych: ' . h($e->getMessage()) . '</div>';
    exit;
}

// Obsługa POST (aktualizacja)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // Pobierz pola
    $nazwa = trim($_POST['nazwa'] ?? '');
    $kategoria_id = isset($_POST['kategoria_id']) && $_POST['kategoria_id'] !== '' ? (int)$_POST['kategoria_id'] : null;
    $numer_inwentarzowy = trim($_POST['numer_inwentarzowy'] ?? null);
    $numer_seryjny = trim($_POST['numer_seryjny'] ?? null);
    $opis = trim($_POST['opis'] ?? null);

    $data_zakupu = !empty($_POST['data_zakupu']) ? $_POST['data_zakupu'] : null;
    $nabycie = trim($_POST['nabycie'] ?? null);
    $ilosc = isset($_POST['ilosc']) && $_POST['ilosc'] !== '' ? (int)$_POST['ilosc'] : 0;
    $wartosc = isset($_POST['wartosc']) && $_POST['wartosc'] !== '' ? (float)str_replace(',', '.', $_POST['wartosc']) : null;

    $magazyn_id = isset($_POST['magazyn_id']) && $_POST['magazyn_id'] !== '' ? (int)$_POST['magazyn_id'] : null;
    $lokalizacja_id = isset($_POST['lokalizacja_id']) && $_POST['lokalizacja_id'] !== '' ? (int)$_POST['lokalizacja_id'] : null;
    $uwagi = trim($_POST['uwagi'] ?? null);

    $waga = isset($_POST['waga']) && $_POST['waga'] !== '' ? (float)str_replace(',', '.', $_POST['waga']) : null;
    $wymiary = trim($_POST['wymiary'] ?? null);

    $remove_photo = !empty($_POST['remove_photo']) ? true : false;

    $errors = [];

    // Walidacja
    if ($nazwa === '') $errors[] = 'Nazwa jest wymagana.';
    if ($kategoria_id === null) $errors[] = 'Kategoria jest wymagana.';
    if ($magazyn_id === null) $errors[] = 'Magazyn jest wymagany.';
    if ($lokalizacja_id === null) $errors[] = 'Lokalizacja jest wymagana.';
    if ($ilosc < 0) $errors[] = 'Ilość nie może być ujemna.';
    if ($wartosc !== null && $wartosc < 0) $errors[] = 'Cena jednostkowa nie może być ujemna.';

    // Obsługa przesłanego pliku zdjęcia (opcjonalnie)
    // Walidujemy upload, ale nazwę pliku robimy: <nazwa>-<id>.<ext>
    $new_photo_path = null;
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
            // fallthrough - render form with $_POST values
        }
    } else {
        // Zaktualizuj rekord
        try {
            $sql = "UPDATE sprzet SET
                        nazwa = :nazwa,
                        opis = :opis,
                        ilosc = :ilosc,
                        ilosc_calkowita = :ilosc_calkowita,
                        kategoria_id = :kategoria_id,
                        lokalizacja_id = :lokalizacja_id,
                        magazyn_id = :magazyn_id,
                        numer_inwentarzowy = :numer_inwentarzowy,
                        numer_seryjny = :numer_seryjny,
                        nabycie = :nabycie,
                        uwagi = :uwagi,
                        data_zakupu = :data_zakupu,
                        waga = :waga,
                        wartosc = :wartosc,
                        wymiary = :wymiary
                    WHERE id = :id";
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
                ':id' => $id,
            ]);

            // Obsłuż zdjęcie: usuń stare jeśli nowe przesłane lub jeśli zaznaczono usunięcie
            if ($hasUpload && $uploadExt) {
                $f = $_FILES['zdjecie'];

                // slug z nazwy sprzętu (bezpieczny filename)
                $base = $nazwa;
                $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
                if ($base === false) $base = $nazwa;
                $base = strtolower($base);
                $base = preg_replace('/[^a-z0-9]+/i', '-', $base);
                $base = trim($base, '-');
                if ($base === '') $base = 'sprzet';

                $basename = $base . '-' . $id . '.' . $uploadExt;

                $uploadDir = __DIR__ . '/zdjecia';
                $target = $uploadDir . '/' . $basename;

                if (!move_uploaded_file($f['tmp_name'], $target)) {
                    $errors[] = 'Błąd zapisu pliku.';
                } else {
                    $new_photo_path = 'zdjecia/' . $basename;

                    // usuń stare pliki jeśli istnieją i są inne niż docelowy
                    if (!empty($existing['zdjecie'])) {
                        $oldAbs = __DIR__ . '/' . $existing['zdjecie'];
                        $newAbs = __DIR__ . '/' . $new_photo_path;
                        if ($oldAbs !== $newAbs && file_exists($oldAbs)) {
                            @unlink($oldAbs);
                        }
                    }

                    $u = $pdo->prepare("UPDATE sprzet SET zdjecie = :zdjecie WHERE id = :id");
                    $u->execute([':zdjecie' => $new_photo_path, ':id' => $id]);
                }
            } elseif ($remove_photo) {
                if (!empty($existing['zdjecie']) && file_exists(__DIR__ . '/' . $existing['zdjecie'])) {
                    @unlink(__DIR__ . '/' . $existing['zdjecie']);
                }
                $u = $pdo->prepare("UPDATE sprzet SET zdjecie = NULL WHERE id = :id");
                $u->execute([':id' => $id]);
            }

            // Odśwież $existing, żeby od razu pokazywał aktualne dane (także ścieżkę zdjęcia)
            $q = $pdo->prepare("SELECT * FROM sprzet WHERE id = :id LIMIT 1");
            $q->execute([':id' => $id]);
            $existing = $q->fetch(PDO::FETCH_ASSOC);

            if (is_ajax()) {
                header('Content-Type: application/json; charset=utf-8');
                if (!empty($errors)) {
                    echo json_encode(['success' => false, 'errors' => $errors]);
                } else {
                    echo json_encode(['success' => true, 'message' => 'Dane zaktualizowano.']);
                }
                exit;
            } else {
                if (!empty($errors)) {
                    echo '<div class="form-error"><ul><li>' . implode('</li><li>', array_map('h', $errors)) . '</li></ul></div>';
                } else {
                    echo '<div class="form-success">Zaktualizowano dane.</div>';
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

// --- Render formularza (GET lub po błędach) ---
$val = array_merge($existing, $_POST ?? []);

?>
<!-- Używamy id dodaj-sprzet-form aby przejąć style -->
<form id="dodaj-sprzet-form" action="edytuj_sprzet.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
  <h2 id="modal-title">Edytuj sprzęt — <?= h($existing['nazwa']) ?> (ID: <?= h($id) ?>)</h2>
  <input type="hidden" name="id" value="<?= h($id) ?>">

  <!-- Dane podstawowe -->
  <div class="panel">
    <h3>Dane podstawowe</h3>

    <div class="form-row">
      <label for="nazwa" class="required">Nazwa:</label>
      <input type="text" id="nazwa" name="nazwa" required maxlength="255" value="<?= h($val['nazwa'] ?? '') ?>">
    </div>

    <div class="form-row">
      <label for="kategoria_id" class="required">Kategoria:</label>
      <select id="kategoria_id" name="kategoria_id" required>
        <option value="">Wybierz...</option>
        <?php foreach ($kategorie as $k): ?>
          <option value="<?= h($k['id']) ?>" <?= (string)($val['kategoria_id'] ?? $existing['kategoria_id']) === (string)$k['id'] ? 'selected' : '' ?>><?= h($k['nazwa']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="numer_inwentarzowy">Nr inwentarzowy:</label>
      <input type="text" id="numer_inwentarzowy" name="numer_inwentarzowy" maxlength="100" value="<?= h($val['numer_inwentarzowy'] ?? $existing['numer_inwentarzowy']) ?>">
    </div>

    <div class="form-row">
      <label for="numer_seryjny">Nr seryjny:</label>
      <input type="text" id="numer_seryjny" name="numer_seryjny" maxlength="255" value="<?= h($val['numer_seryjny'] ?? $existing['numer_seryjny']) ?>">
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label for="opis">Opis:</label>
      <textarea id="opis" name="opis"><?= h($val['opis'] ?? $existing['opis']) ?></textarea>
    </div>
  </div>

  <!-- Dane dotyczące nabycia -->
  <div class="panel">
    <h3>Dane dotyczące nabycia</h3>

    <div class="form-row">
      <label for="data_zakupu">Data zakupu:</label>
      <input type="date" id="data_zakupu" name="data_zakupu" value="<?= h($val['data_zakupu'] ?? $existing['data_zakupu']) ?>">
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label for="nabycie">Podstawa nabycia:</label>
      <textarea id="nabycie" name="nabycie"><?= h($val['nabycie'] ?? $existing['nabycie']) ?></textarea>
    </div>

    <div class="form-row">
      <label for="ilosc" class="required">Ilość nabycia:</label>
      <input type="number" id="ilosc" name="ilosc" min="0" required value="<?= h($val['ilosc_calkowita'] ?? $existing['ilosc_calkowita']) ?>">
    </div>

    <div class="form-row">
      <label for="wartosc" class="required">Cena jednostkowa (zł):</label>
      <input type="text" id="wartosc" name="wartosc" required value="<?= h($val['wartosc'] ?? $existing['wartosc']) ?>">
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
          <option value="<?= h($m['id']) ?>" <?= (string)($val['magazyn_id'] ?? $existing['magazyn_id']) === (string)$m['id'] ? 'selected' : '' ?>><?= h($m['nazwa']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label for="lokalizacja_id" class="required">Lokalizacja:</label>
      <select id="lokalizacja_id" name="lokalizacja_id" required>
        <option value="">Wybierz...</option>
        <?php foreach ($lokalizacje as $l): ?>
          <option value="<?= h($l['id']) ?>" <?= (string)($val['lokalizacja_id'] ?? $existing['lokalizacja_id']) === (string)$l['id'] ? 'selected' : '' ?>><?= h($l['nazwa']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label for="uwagi">Uwagi:</label>
      <textarea id="uwagi" name="uwagi"><?= h($val['uwagi'] ?? $existing['uwagi']) ?></textarea>
    </div>
  </div>

  <!-- Dane dodatkowe -->
  <div class="panel">
    <h3>Dane dodatkowe</h3>

    <div class="form-row">
      <label for="waga">Waga (kg):</label>
      <input type="text" id="waga" name="waga" value="<?= h($val['waga'] ?? $existing['waga']) ?>">
    </div>

    <div class="form-row">
      <label for="wymiary">Wymiary (WxSxG cm):</label>
      <input type="text" id="wymiary" name="wymiary" maxlength="255" value="<?= h($val['wymiary'] ?? $existing['wymiary']) ?>">
    </div>
  </div>

  <!-- Zdjęcie -->
  <div class="panel">
    <h3>Zdjęcie</h3>
    <div class="form-row">
      <label for="zdjecie">Nowe zdjęcie (opcjonalnie):</label>
      <input type="file" id="zdjecie" name="zdjecie" accept="image/*">
    </div>

    <?php if (!empty($existing['zdjecie'])): ?>
      <div class="form-row">
        <label>Aktualne:</label>
        <div class="readonly-value">
          <?php if (file_exists(__DIR__ . '/' . $existing['zdjecie'])): ?>
            <img src="<?= h($existing['zdjecie']) ?>" alt="Zdjęcie" style="max-width:160px; max-height:120px; object-fit:cover; border-radius:6px;">
          <?php else: ?>
            <?= h($existing['zdjecie']) ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <label for="remove_photo">Usuń zdjęcie:</label>
        <input type="checkbox" id="remove_photo" name="remove_photo" value="1">
      </div>
    <?php endif; ?>
  </div>

  <!-- Akcje -->
  <div class="form-row panel-actions" style="justify-content:flex-end;">
    <button type="submit" class="btn-save">Zapisz zmiany</button>
    <button type="button" class="btn-cancel" onclick="window.closeModal && window.closeModal()" style="margin-left:8px;">Anuluj</button>
  </div>

  <div id="form-feedback" style="margin-top:10px;"></div>
</form>