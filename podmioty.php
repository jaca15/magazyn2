<?php
// DEPRECATED: Ten plik jest starą wersją obsługi podmiotów. Nowe odwołania powinny używać
// podmiot_panel.php / podmiot_add.php / podmiot_edit.php.
require 'auth.php';
require_login();
require 'polaczenie.php';

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_ajax() : bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
    return false;
}

// --- Obsługa usuwania (jeśli przychodzi POST z delete_id) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    // Pobierz nazwę (do komunikatu) i sprawdź czy istnieje
    $q = $pdo->prepare("SELECT nazwa_pelna FROM podmioty WHERE id = :id LIMIT 1");
    $q->execute([':id' => $deleteId]);
    $item = $q->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['Rekord nie istnieje.']]);
            exit;
        } else {
            header('Location: podmioty.php?error=notfound');
            exit;
        }
    }

    // Spróbuj usunąć rekord
    try {
        $del = $pdo->prepare("DELETE FROM podmioty WHERE id = :id");
        $del->execute([':id' => $deleteId]);
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Usunięto podmiot: ' . $item['nazwa_pelna']]);
            exit;
        } else {
            header('Location: podmioty.php?deleted=1');
            exit;
        }
    } catch (PDOException $e) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['Błąd bazy danych: ' . $e->getMessage()]]);
            exit;
        } else {
            header('Location: podmioty.php?error=sql');
            exit;
        }
    }
}

// Rozdzielczość paginacji
$perPage = 2; // Liczba rekordów na stronę
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Obliczenie całkowitej liczby rekordów
$totalStmt = $pdo->query("SELECT COUNT(*) AS total FROM podmioty");
$totalRecords = (int)($totalStmt->fetchColumn());
$totalPages = max(1, ceil($totalRecords / $perPage));

// Pobranie danych z tabeli sprzet wraz z nazwą kategorii, magazynu i lokalizacji
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.nazwa_skrocona,
        p.nazwa_pelna,
        p.adres,
        p.telefon,
        p.email,
        p.uwagi
    FROM podmioty p
   ORDER BY p.nazwa_pelna ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
/* Jasnoszary kolor dla napisu "Nie przypisano" - kolorystyka nie zmieniana */
.not-assigned {
  color: #9aa0a6;
}
/* Proste przyciski akcji - nie zmieniamy kolorów */
.table-actions { display:flex; gap:6px; }
.table-actions button,
.table-actions a {
  padding:6px 8px;
  border-radius:6px;
  border:1px solid #d0d7de;
  background:#fff;
  cursor:pointer;
  font-size:0.9rem;
  text-decoration:none;
  color:#222;
}
.table-actions a.view { background:#f8f9fa; }
.table-actions a.edit { background:#eef7ff; border-color:#cfe2ff; color:#0b5ed7; }
.table-actions button.delete { background:#fff5f5; border-color:#f5c2c7; color:#a71d2a; }
.table-actions button.delete:hover { background:#ffecec; }


/* Keep cell padding similar to previous style (no color changes). */
.podmiot-table thead th,
.podmiot-table tbody td {
  padding: 10px 12px;
  vertical-align: middle;
  box-sizing: border-box;
}

/* Prevent layout-breaking long words: wrap if needed, but keep single-line where possible */
.podmiot-table td { 
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* If the screen is narrow, fallback to 100% width and allow wrapping */
@media (max-width: 900px) {
  .podmiot-table { width: 100% !important; margin: 8px 0 !important; table-layout: auto; }
  .podmiot-table td { white-space: normal; }
}

/* Keep action buttons responsive */
@media (max-width: 720px) {
  .table-actions { flex-wrap:wrap; gap:4px; }
}
</style>

<h2>Lista podmiotów</h2>
<table class="sprzet-table">
  <thead>
    <tr>
      <th>L.p.</th>
      <th>Nazwa pełna</th>
      <th>Nazwa skrócona</th>
      <th>Adres</th>
      <th>telefon</th>
      <th>e-mail</th>
      <th>uwagi</th>
      <th>Akcje</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $index => $row): 
      // przygotuj wyświetlane wartości i klasy dla "Nie przypisano"
//      $kategoria = $row['kategoria_nazwa'] ?? ($row['kategoria_id'] !== null ? $row['kategoria_id'] : 'Nie przypisano');
//      $kategoria_class = ($kategoria === 'Nie przypisano') ? 'not-assigned' : '';

//      $magazyn = $row['magazyn_nazwa'] ?? ($row['magazyn_id'] !== null ? $row['magazyn_id'] : 'Nie przypisano');
//      $magazyn_class = ($magazyn === 'Nie przypisano') ? 'not-assigned' : '';

//      $lokalizacja = $row['lokalizacja_nazwa'] ?? ($row['lokalizacja_id'] !== null ? $row['lokalizacja_id'] : 'Nie przypisano');
//      $lokalizacja_class = ($lokalizacja === 'Nie przypisano') ? 'not-assigned' : '';
    ?>
      <tr data-id="<?= h($row['id']) ?>">
        <td><?= h(($page - 1) * $perPage + $index + 1) ?></td>
        <td title="<?= h($row['nazwa_pelna']) ?>"><?= h($row['nazwa_pelna']) ?></td>
        <td title="<?= h($row['nazwa_skrocona'] ?? 'Brak') ?>"><?= h($row['nazwa_skrocona'] ?? 'Brak') ?></td>
        <td><?= h($row['adres']) ?></td>
        <td><?= h($row['telefon']) ?></td>
        <td><?= h($row['email'] ?? 'Brak') ?></td>
        <td><?= h($row['uwagi'] ?? 'Brak') ?></td>
        <td>
          <div class="table-actions">
            <a href="#" class="view open-modal" data-url="podglad_sprzet.php?id=<?= h($row['id']) ?>">Podgląd</a>
            <a href="#" class="edit open-modal" data-url="edytuj_sprzet.php?id=<?= h($row['id']) ?>">Edytuj</a>
            <a href="#" class="edit open-modal" data-url="wypozycz_sprzet.php?id=<?= h($row['id']) ?>">Wypożycz</a>
            <button
              class="delete"
              data-id="<?= h($row['id']) ?>"
              data-name="<?= h($row['nazwa_pelna']) ?>"
              type="button"
              title="Usuń">Usuń</button>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- Paginacja -->
<nav class="pagination">
  <?php if ($page > 1): ?>
    <a href="podmioty.php?page=<?= $page - 1 ?>">Poprzednia</a>
  <?php endif; ?>

  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="podmioty.php?page=<?= $i ?>" <?= $i === $page ? 'class="active"' : '' ?>>
      <?= $i ?>
    </a>
  <?php endfor; ?>

  <?php if ($page < $totalPages): ?>
    <a href="podmioty.php?page=<?= $page + 1 ?>">Następna</a>
  <?php endif; ?>
</nav>