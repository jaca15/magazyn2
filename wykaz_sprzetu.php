<?php
require 'auth.php';
require_login();
require 'polaczenie.php';

$canManageEquipment = ma_uprawnienie('manage_equipment');
$canIssueEquipment = ma_uprawnienie('issue_equipment');
$canDeleteEquipment = ma_uprawnienie('delete_equipment');

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
    if (!$canDeleteEquipment) {
        deny_access('Brak uprawnień do usuwania sprzętu.');
    }
    $deleteId = (int)$_POST['delete_id'];
    // Pobierz nazwę (do komunikatu) i sprawdź czy istnieje
    $q = $pdo->prepare("SELECT nazwa FROM sprzet WHERE id = :id LIMIT 1");
    $q->execute([':id' => $deleteId]);
    $item = $q->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['Rekord nie istnieje.']]);
            exit;
        } else {
            header('Location: wykaz_sprzetu.php?error=notfound');
            exit;
        }
    }

    // Spróbuj usunąć rekord
    try {
        $del = $pdo->prepare("DELETE FROM sprzet WHERE id = :id");
        $del->execute([':id' => $deleteId]);
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Usunięto sprzęt: ' . $item['nazwa']]);
            exit;
        } else {
            header('Location: wykaz_sprzetu.php?deleted=1');
            exit;
        }
    } catch (PDOException $e) {
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'errors' => ['Błąd bazy danych: ' . $e->getMessage()]]);
            exit;
        } else {
            header('Location: wykaz_sprzetu.php?error=sql');
            exit;
        }
    }
}

/* =======================
   FILTRY (SERVER-SIDE)
   ======================= */
$filterKategoria = trim((string)($_GET['kategoria'] ?? ''));
$filterMagazyn = trim((string)($_GET['magazyn'] ?? ''));
$filterLokalizacja = trim((string)($_GET['lokalizacja'] ?? ''));
$filterDostepne = trim((string)($_GET['dostepne'] ?? ''));

$where = [];
$params = [];

if ($filterKategoria !== '') {
    $where[] = "k.nazwa = :kategoria";
    $params[':kategoria'] = $filterKategoria;
}
if ($filterMagazyn !== '') {
    $where[] = "m.nazwa = :magazyn";
    $params[':magazyn'] = $filterMagazyn;
}
if ($filterLokalizacja !== '') {
    $where[] = "l.nazwa = :lokalizacja";
    $params[':lokalizacja'] = $filterLokalizacja;
}
if ($filterDostepne === '1') {
    $where[] = "s.ilosc > 0";
}

if ($filterDostepne === '2') {
    $where[] = "s.ilosc = 0";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Rozdzielczość paginacji
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Total (z filtrami)
$totalStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM sprzet s
    LEFT JOIN kategorie k ON s.kategoria_id = k.id
    LEFT JOIN magazyny m ON s.magazyn_id = m.id
    LEFT JOIN lokalizacje l ON s.lokalizacja_id = l.id
    $whereSql
");
$totalStmt->execute($params);
$totalRecords = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($totalRecords / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Dane (z filtrami)
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.nazwa,
        s.opis,
        s.ilosc_calkowita,
        s.ilosc AS dostepna_ilosc,
        s.numer_inwentarzowy,
        s.magazyn_id,
        s.kategoria_id,
        s.lokalizacja_id,
        k.nazwa AS kategoria_nazwa,
        m.nazwa AS magazyn_nazwa,
        l.nazwa AS lokalizacja_nazwa
    FROM sprzet s
    LEFT JOIN kategorie k ON s.kategoria_id = k.id
    LEFT JOIN magazyny m ON s.magazyn_id = m.id
    LEFT JOIN lokalizacje l ON s.lokalizacja_id = l.id
    $whereSql
    ORDER BY s.nazwa ASC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Listy do filtrów
$kategorieList = $pdo->query("SELECT id, nazwa FROM kategorie ORDER BY nazwa ASC")->fetchAll(PDO::FETCH_ASSOC);
$magazynyList  = $pdo->query("SELECT id, nazwa FROM magazyny ORDER BY nazwa ASC")->fetchAll(PDO::FETCH_ASSOC);
$lokalizacjeAll = $pdo->query("SELECT id, nazwa FROM lokalizacje ORDER BY nazwa ASC")->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
.not-assigned { color: #9aa0a6; }
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

.sprzet-table thead th,
.sprzet-table tbody td {
  padding: 10px 12px;
  vertical-align: middle;
  box-sizing: border-box;
}
.sprzet-table td { 
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
@media (max-width: 900px) {
  .sprzet-table { width: 100% !important; margin: 8px 0 !important; table-layout: auto; }
  .sprzet-table td { white-space: normal; }
}
@media (max-width: 720px) {
  .table-actions { flex-wrap:wrap; gap:4px; }
}

.filters-panel{
  border:1px solid #d0d7de;
  border-radius:10px;
  padding:12px;
  margin: 10px 0 14px;
  background:#fff;
}
.filters-grid{
  display:grid;
  grid-template-columns: repeat(4, minmax(180px, 1fr));
  gap:10px;
  align-items:end;
}
.filters-grid label{
  display:block;
  font-size:0.9rem;
  margin-bottom:6px;
  color:#222;
}
.filters-grid select{
  width:100%;
  padding:8px 10px;
  border:1px solid #d0d7de;
  border-radius:8px;
  background:#fff;
}
.filters-actions{
  display:flex;
  gap:8px;
  align-items:center;
  flex-wrap:wrap;
}
.filters-actions button,
.filters-actions a{
  padding:8px 10px;
  border-radius:8px;
  border:1px solid #d0d7de;
  background:#fff;
  cursor:pointer;
  font-size:0.9rem;
  text-decoration:none;
  color:#222;
}
@media (max-width: 900px){
  .filters-grid{ grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px){
  .filters-grid{ grid-template-columns: 1fr; }
}
</style>

<h2>Lista sprzętu</h2>

<form class="filters-panel" id="sprzetFilters" method="get" action="wykaz_sprzetu.php">
  <div class="filters-grid">
    <div>
      <label for="f_kategoria">Kategoria sprzętu</label>
      <select id="f_kategoria" name="kategoria">
        <option value="">Wszystkie</option>
        <?php foreach ($kategorieList as $k): ?>
          <option value="<?= h($k['nazwa']) ?>" <?= ($filterKategoria === (string)$k['nazwa']) ? 'selected' : '' ?>>
            <?= h($k['nazwa']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="f_magazyn">Magazyn</label>
      <select id="f_magazyn" name="magazyn">
        <option value="">Wszystkie</option>
        <?php foreach ($magazynyList as $m): ?>
          <option value="<?= h($m['nazwa']) ?>" <?= ($filterMagazyn === (string)$m['nazwa']) ? 'selected' : '' ?>>
            <?= h($m['nazwa']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="f_lokalizacja">Lokalizacja</label>
      <select id="f_lokalizacja" name="lokalizacja">
        <option value="">Wszystkie</option>
        <?php foreach ($lokalizacjeAll as $l): ?>
          <option value="<?= h($l['nazwa']) ?>" <?= ($filterLokalizacja === (string)$l['nazwa']) ? 'selected' : '' ?>>
            <?= h($l['nazwa']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="f_dostepne">Dostępność</label>
      <select id="f_dostepne" name="dostepne">
        <option value="">Wszystkie</option>
        <option value="1" <?= ($filterDostepne === '1') ? 'selected' : '' ?>>
          Tylko dostępne
        </option>
        <option value="2" <?= ($filterDostepne === '2') ? 'selected' : '' ?>>
          Tylko niedostępne
        </option>
      </select>
    </div>

    <div class="filters-actions">
      <button type="submit">Filtruj</button>
      <a href="wykaz_sprzetu.php?page=1" id="filtersClearLink">Wyczyść</a>
    </div>
  </div>
</form>

<table class="sprzet-table">
  <thead>
    <tr>
      <th>L.p.</th>
      <th>Nazwa</th>
      <th>Opis</th>
      <th>Ilość całkowita</th>
      <th>Dostępna ilość</th>
      <th>Nr inwentarzowy</th>
      <th>Kategoria</th>
      <th>Magazyn</th>
      <th>Lokalizacja</th>
      <th>Akcje</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $index => $row): 
      $kategoria = $row['kategoria_nazwa'] ?? ($row['kategoria_id'] !== null ? $row['kategoria_id'] : 'Nie przypisano');
      $kategoria_class = ($kategoria === 'Nie przypisano') ? 'not-assigned' : '';

      $magazyn = $row['magazyn_nazwa'] ?? ($row['magazyn_id'] !== null ? $row['magazyn_id'] : 'Nie przypisano');
      $magazyn_class = ($magazyn === 'Nie przypisano') ? 'not-assigned' : '';

      $lokalizacja = $row['lokalizacja_nazwa'] ?? ($row['lokalizacja_id'] !== null ? $row['lokalizacja_id'] : 'Nie przypisano');
      $lokalizacja_class = ($lokalizacja === 'Nie przypisano') ? 'not-assigned' : '';
    ?>
      <tr data-id="<?= h($row['id']) ?>">
        <td><?= h(($page - 1) * $perPage + $index + 1) ?></td>
        <td title="<?= h($row['nazwa']) ?>"><?= h($row['nazwa']) ?></td>
        <td title="<?= h($row['opis'] ?? 'Brak opisu') ?>"><?= h($row['opis'] ?? 'Brak opisu') ?></td>
        <td><?= h($row['ilosc_calkowita']) ?></td>
        <td><?= h($row['dostepna_ilosc']) ?></td>
        <td><?= h($row['numer_inwentarzowy'] ?? 'Brak numeru') ?></td>
        <td class="<?= $kategoria_class ?>" title="<?= h($kategoria) ?>"><?= h($kategoria) ?></td>
        <td class="<?= $magazyn_class ?>" title="<?= h($magazyn) ?>"><?= h($magazyn) ?></td>
        <td class="<?= $lokalizacja_class ?>" title="<?= h($lokalizacja) ?>"><?= h($lokalizacja) ?></td>
        <td>
          <div class="table-actions">
            <a href="#" class="view open-modal" data-url="podglad_sprzet.php?id=<?= h($row['id']) ?>">Podgląd</a>
            <?php if ($canManageEquipment): ?>
            <a href="#" class="edit open-modal" data-url="edytuj_sprzet.php?id=<?= h($row['id']) ?>">Edytuj</a>
            <?php endif; ?>
            <?php if ($canIssueEquipment): ?>
            <a href="#" class="edit open-modal" data-url="wypozycz_sprzet.php?id=<?= h($row['id']) ?>">Wypożycz</a>
            <?php endif; ?>
            <?php if ($canDeleteEquipment): ?>
            <button
              class="delete"
              data-id="<?= h($row['id']) ?>"
              data-name="<?= h($row['nazwa']) ?>"
              type="button"
              title="Usuń">Usuń</button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
/* Filtry i "Wyczyść": zawsze przez loadContent() (AJAX) */
(function(){
  const content = document.getElementById('content') || document;
  const form = content.querySelector('#sprzetFilters');
  const clearLink = content.querySelector('#filtersClearLink');
  if (!form) return;

  form.addEventListener('submit', function(e){
    if (typeof window.loadContent !== 'function') return; // fallback: normalny submit
    e.preventDefault();

    const fd = new FormData(form);
    const params = new URLSearchParams();
    for (const [k,v] of fd.entries()) {
      if (v !== null && String(v).trim() !== '') params.append(k, String(v));
    }
    params.set('page', '1'); // po filtrach wracamy na 1 stronę

    const url = (form.getAttribute('action') || 'wykaz_sprzetu.php') + '?' + params.toString();
    window.loadContent(url);
  });

  if (clearLink) {
    clearLink.addEventListener('click', function(e){
      if (typeof window.loadContent !== 'function') return; // fallback: normalna nawigacja
      e.preventDefault();
      const href = clearLink.getAttribute('href') || 'wykaz_sprzetu.php?page=1';
      window.loadContent(href);
    });
  }
})();

/* Paginacja: klik -> loadContent() */
(function(){
  const content = document.getElementById('content');
  if (!content) return;

  content.addEventListener('click', function(e){
    const a = e.target.closest && e.target.closest('.pagination a');
    if (!a) return;

    const href = a.getAttribute('href');
    if (!href) return;

    if (typeof window.loadContent === 'function') {
      e.preventDefault();
      window.loadContent(href);
    }
  });
})();
</script>

<!-- Paginacja (z zachowaniem filtrów w URL) -->
<nav class="pagination">
  <?php
    $qs = http_build_query([
      'kategoria' => $filterKategoria,
      'magazyn' => $filterMagazyn,
      'lokalizacja' => $filterLokalizacja,
      'dostepne' => $filterDostepne,
    ]);
    $qs = $qs ? ('&' . $qs) : '';
  ?>

  <?php if ($page > 1): ?>
    <a href="wykaz_sprzetu.php?page=<?= $page - 1 ?><?= $qs ?>">Poprzednia</a>
  <?php endif; ?>

  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="wykaz_sprzetu.php?page=<?= $i ?><?= $qs ?>" <?= $i === $page ? 'class="active"' : '' ?>>
      <?= $i ?>
    </a>
  <?php endfor; ?>

  <?php if ($page < $totalPages): ?>
    <a href="wykaz_sprzetu.php?page=<?= $page + 1 ?><?= $qs ?>">Następna</a>
  <?php endif; ?>
</nav>