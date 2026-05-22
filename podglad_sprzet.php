<?php
// podglad_sprzet.php
// Fragment HTML do załadowania do modala — podgląd danych jednego rekordu sprzętu.
// Używa stylów z css/dodaj_sprzet.css (formularz otrzymuje id 'dodaj-sprzet-form',
// dzięki czemu odziedziczy wygląd formularza dodawania).
//
// Wymagane: auth.php (require_login()), polaczenie.php (ustawia $pdo)

require 'auth.php';
require_login();
require 'polaczenie.php';
$canManageEquipment = ma_uprawnienie('manage_equipment');

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<div class="form-error">Nieprawidłowy identyfikator rekordu.</div>';
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT s.*,
               k.nazwa AS kategoria_nazwa,
               m.nazwa AS magazyn_nazwa,
               l.nazwa AS lokalizacja_nazwa
        FROM sprzet s
        LEFT JOIN kategorie k ON s.kategoria_id = k.id
        LEFT JOIN magazyny m ON s.magazyn_id = m.id
        LEFT JOIN lokalizacje l ON s.lokalizacja_id = l.id
        WHERE s.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo '<div class="form-error">Nie znaleziono rekordu o podanym identyfikatorze.</div>';
        exit;
    }
} catch (PDOException $e) {
    echo '<div class="form-error">Błąd bazy danych: ' . h($e->getMessage()) . '</div>';
    exit;
}

// Przygotuj pola do wyświetlenia
$kategoria = $row['kategoria_nazwa'] ?? ($row['kategoria_id'] !== null ? $row['kategoria_id'] : 'Nie przypisano');
$magazyn = $row['magazyn_nazwa'] ?? ($row['magazyn_id'] !== null ? $row['magazyn_id'] : 'Nie przypisano');
$lokalizacja = $row['lokalizacja_nazwa'] ?? ($row['lokalizacja_id'] !== null ? $row['lokalizacja_id'] : 'Nie przypisano');

// Pobierz historię wypożyczeń dla danego sprzętu
try {
    $wypozyczeniaStmt = $pdo->prepare("
        SELECT 
            w.id AS id,
            w.data_wypozyczenia,
            w.data_zwrotu,
            w.ilosc,
            p.nazwa_skrocona AS uzytkownik,
            w.uwagi,
            w.uwagi_zw
        FROM wypozyczenia w
        LEFT JOIN podmioty p ON w.uzytkownik = p.id
        WHERE w.sprzet_id = ?
        ORDER BY w.data_wypozyczenia DESC
    ");
    $wypozyczeniaStmt->execute([$id]);
    $wypozyczenia = $wypozyczeniaStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Obsługuję brak tabeli lub błąd bazy danych
    $wypozyczenia = [];
}





?>
<!-- Drobny styl lokalny: pogrubienie wartości pól, etykiety bez zmian -->
<style>
/* Jeśli css/dodaj_sprzet.css jest ładowane, to poniższe tylko ustawia pogrubienie wartości */
#dodaj-sprzet-form .readonly-value {
  font-weight: 700;
  color: #1f2d3d;
}
</style>

<!-- Używamy id dodaj-sprzet-form aby przejąć style z css/dodaj_sprzet.css -->
<form id="dodaj-sprzet-form" class="readonly-fragment" action="#" method="get" aria-labelledby="modal-title">
  <!-- Nagłówek z nazwą sprzętu i (ID) za nazwą -->
  <h2 id="modal-title"><?= h($row['nazwa']) ?> (ID: <?= h($id) ?>)</h2>

  <div class="panel">
    <h3>Dane podstawowe</h3>

    <div class="form-row">
      <label>Nazwa:</label>
      <div class="readonly-value"><?= h($row['nazwa']) ?></div>
    </div>

    <div class="form-row">
      <label>Kategoria:</label>
      <div class="readonly-value <?= ($kategoria === 'Nie przypisano') ? 'not-assigned' : '' ?>"><?= h($kategoria) ?></div>
    </div>

    <div class="form-row">
      <label>Nr inwentarzowy:</label>
      <div class="readonly-value"><?= h($row['numer_inwentarzowy'] ?? 'Brak') ?></div>
    </div>

    <div class="form-row">
      <label>Nr seryjny:</label>
      <div class="readonly-value"><?= h($row['numer_seryjny'] ?? 'Brak') ?></div>
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label>Opis:</label>
      <div class="readonly-value" style="white-space:pre-wrap;"><?= h($row['opis'] ?? '-') ?></div>
    </div>
  </div>

  <div class="panel">
    <h3>Dane dotyczące nabycia</h3>

    <div class="form-row">
      <label>Data zakupu:</label>
      <div class="readonly-value"><?= h($row['data_zakupu'] ?? '-') ?></div>
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label>Podstawa nabycia:</label>
      <div class="readonly-value" style="white-space:pre-wrap;"><?= h($row['nabycie'] ?? '-') ?></div>
    </div>

    <div class="form-row">
      <label>Ilość zakupu:</label>
      <div class="readonly-value"><?= h($row['ilosc_calkowita'] ?? 0) ?></div>
    </div>

    <div class="form-row">
      <label>Cena jednostkowa:</label>
      <div class="readonly-value"><?= $row['wartosc'] !== null ? h(number_format((float)$row['wartosc'], 2, ',', ' ')) . ' zł' : '-' ?></div>
    </div>
  </div>

  <div class="panel">
    <h3>Miejsce przechowywania i dostępność</h3>
    
     <div class="form-row">
      <label>Dostępna ilość:</label>
      <div class="readonly-value"><?= h($row['ilosc'] ?? 0) ?></div>
    </div>

    <div class="form-row">
      <label>Magazyn:</label>
      <div class="readonly-value <?= ($magazyn === 'Nie przypisano') ? 'not-assigned' : '' ?>"><?= h($magazyn) ?></div>
    </div>

    <div class="form-row">
      <label>Lokalizacja:</label>
      <div class="readonly-value <?= ($lokalizacja === 'Nie przypisano') ? 'not-assigned' : '' ?>"><?= h($lokalizacja) ?></div>
    </div>

    <div class="form-row" style="align-items:flex-start;">
      <label>Uwagi:</label>
      <div class="readonly-value" style="white-space:pre-wrap;"><?= h($row['uwagi'] ?? '-') ?></div>
    </div>
  </div>

  <div class="panel">
    <h3>Dane dodatkowe</h3>

    <div class="form-row">
      <label>Waga (kg):</label>
      <div class="readonly-value"><?= $row['waga'] !== null ? h($row['waga']) : '-' ?></div>
    </div>

    <div class="form-row">
      <label>Wymiary (WxSxG cm):</label>
      <div class="readonly-value"><?= h($row['wymiary'] ?? '-') ?></div>
    </div>
  </div>

  <div class="panel">
    <h3>Zdjęcie</h3>
    <div class="form-row">
      <label>Plik:</label>
      <div class="readonly-value">
        <?php if (!empty($row['zdjecie']) && file_exists(__DIR__ . '/' . $row['zdjecie'])): ?>
          <img src="<?= h($row['zdjecie']) ?>" alt="Zdjęcie: <?= h($row['nazwa']) ?>" style="max-width:220px; max-height:180px; object-fit:cover; border-radius:6px;">
        <?php elseif (!empty($row['zdjecie'])): ?>
          <div><?= h($row['zdjecie']) ?></div>
        <?php else: ?>
          <div>- brak zdjęcia -</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  
   <!-- Sekcja Historia wypożyczeń -->
        <h3>Historia wypożyczeń</h3>
        <?php if (!empty($wypozyczenia)): ?>
            <table>
                <thead>
                    <tr>
                        <th>L.p.</th>
                        <th>Data wypożyczenia</th>
                        <th>Data zwrotu</th>
                        <th>Ilość</th>
                        <th>Wypożyczający</th>
                        <th>Uwagi</th>
                        <th>Uwagi zwrot.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wypozyczenia as $index => $w): ?>
                        <tr>
                            <td><?= h($index + 1) ?></td>
                            <td><?= h($w['data_wypozyczenia']) ?></td>
                            <td><?= h($w['data_zwrotu'] ?? 'Brak') ?></td>
                            <td><?= h($w['ilosc']) ?></td>
                            <td><?= h($w['uzytkownik'] ?? 'Nieznany użytkownik') ?></td>
                            <td><?= h($w['uwagi'] ?? '') ?></td>
                            <td><?= h($w['uwagi_zw'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Brak historii wypożyczeń dla tego sprzętu.</p>
        <?php endif; ?>
  
  

  <div class="form-row panel-actions" style="justify-content:flex-end;">
    <button type="button" class="btn-cancel" onclick="window.closeModal && window.closeModal()">Zamknij</button>
    <?php if ($canManageEquipment): ?>
    <button type="button" class="btn-save" style="margin-left:8px;" onclick="(function(){ if(typeof window.openModal==='function'){ window.openModal('edytuj_sprzet.php?id=<?= $id ?>'); } else { window.location.href='edytuj_sprzet.php?id=<?= $id ?>'; } })()">Edytuj</button>
    <?php endif; ?>
  </div>
</form>