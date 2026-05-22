<?php
//kategoria_panel.php - fragment panelu kategorii (dashboard-friendly, z paginacją i akcjami)
// Zmiany: tabela ustawiona na szerokość 65% i wyśrodkowana
require_once 'auth.php';
require_admin();
require 'polaczenie.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function is_ajax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/*
  Obsługa usuwania użytkownika (AJAX POST).
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $deleteId = (int)$_POST['delete_id'];
/*
    $currentUserId = (int)($_SESSION['user']['id'] ?? $_SESSION['id'] ?? 0);
    if ($deleteId && $currentUserId === $deleteId) {
        echo json_encode(['success' => false, 'errors' => ['Nie można usunąć aktualnie zalogowanego użytkownika.']]);
        exit;
    }
*/
    try {
        $q = $pdo->prepare("SELECT id, nazwa, opis FROM kategorie WHERE id = :id LIMIT 1");
        $q->execute([':id' => $deleteId]);
        $item = $q->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            echo json_encode(['success' => false, 'errors' => ['Kategoria nie istnieje.']]);
            exit;
        }
        
        $del = $pdo->prepare("DELETE FROM kategorie WHERE id = :id");
        $del->execute([':id' => $deleteId]);

        echo json_encode(['success' => true, 'message' => 'Usunięto kategorię: ' . ($item['nazwa'] ?: $item['nazwa'])]);
        exit;
    } catch (Throwable $e) {
        error_log('kategoria_panel delete error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'errors' => ['Błąd bazy danych: ' . $e->getMessage()]]);
        exit;
    }
}

/* --- Parametry paginacji --- */
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* --- Pobierz liczbę kategorii i stronę danych --- */
try {
    $totalStmt = $pdo->query("SELECT COUNT(*) AS total FROM kategorie");
    $totalRecords = (int)$totalStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('kategoria_panel count error: ' . $e->getMessage());
    $totalRecords = 0;
}
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

try {
    $stmtp = $pdo->prepare("
        SELECT 
          id,
          nazwa,
          opis
          FROM kategorie
        ORDER BY nazwa ASC
        LIMIT :limit OFFSET :offset
    ");
    $stmtp->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmtp->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmtp->execute();
    $rowsp = $stmtp->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('podmiot_panel fetch error: ' . $e->getMessage());
    $rowsp = [];
}

/* Helper URL paginacji */
function pageUrlUsers($p) {
    return 'kategoria_panel.php?' . http_build_query(['page' => $p]);
}

/* Widoczne strony w paginacji */
$visiblePages = 7;
$half = floor($visiblePages / 2);
$start = max(1, $page - $half);
$end = min($totalPages, $start + $visiblePages - 1);
if ($end - $start + 1 < $visiblePages) {
    $start = max(1, $end - $visiblePages + 1);
}
?>

<link rel="stylesheet" href="css/users_panel.css">

<div id="users-panel" class="content">
  <h2>Kategorie sprzętu</h2>

  <p>Lista kategorii sprzętu w systemie. Możesz dodawać, edytować, lub usunąć poszczególną kategorię.</p>

  <div class="actions actions--toolbar" role="toolbar" aria-label="Akcje użytkowników" style="margin-bottom:10px;">
    <button id="addUserBtn" class="btn" type="button" title="Dodaj nowy podmiot">＋ Dodaj kategorię</button>
  </div>

  <?php if (empty($rowsp)): ?>
    <p class="info-message">Brak kategorii.</p>
  <?php else: ?>
    <!-- Tabela ustawiona na szerokość 65% i wyśrodkowana -->
    <table class="tabela" role="table" aria-label="Lista kategorii" style="width:45%; margin:0 auto;">
      <thead>
        <tr>
          <th>L.p.</th>
          <th>Nazwa</th>
         <th>Opis kategorii</th>
          <!-- zwiększona szerokość kolumny Akcje -->
          <th class="col-actions" style="width:15%">Akcje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rowsp as $idx => $r):
          $displayName = $r['nazwa'] ?: $r['nazwa'];
        ?>
        <tr data-id="<?= (int)$r['id'] ?>">
          <td><?= h(($page - 1) * $perPage + $idx + 1) ?></td>
          <td title="<?= h($displayName) ?>"><?= h($displayName) ?></td>
          <td><?= h($r['opis'] ?? '') ?></td>
          <!-- zastosuj klasę col-actions w komórce, by styl był spójny -->
          <td class="col-actions">
            <div class="akcje" role="group" aria-label="Akcje">
              <!-- Usunięto przycisk "Podgląd" zgodnie z prośbą -->
              <button type="button" class="btn-akcja edit open-modal" data-url="kategoria_edit.php?id=<?= (int)$r['id'] ?>">Edytuj</button>
              <button type="button" class="btn-akcja usun delete" data-id="<?= (int)$r['id'] ?>" data-name="<?= h($displayName) ?>">Usuń</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Paginacja -->
    <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Paginacja">
        <?php if ($page > 1): ?>
          <a href="<?= h(pageUrlUsers($page - 1)) ?>">&lsaquo; Poprzednia</a>
        <?php endif; ?>

        <?php for ($p = $start; $p <= $end; $p++): ?>
          <?php if ($p == $page): ?>
            <a href="<?= h(pageUrlUsers($p)) ?>" class="pagination-current"><?= $p ?></a>
          <?php else: ?>
            <a href="<?= h(pageUrlUsers($p)) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a href="<?= h(pageUrlUsers($page + 1)) ?>">Następna &rsaquo;</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

  <?php endif; ?>

  <div id="users-result" aria-live="polite"></div>

  <script>
  // initUsersPanel - obsługa działania przycisków (open-modal i delete)
  window.initUsersPanel = function initUsersPanel() {
    'use strict';
    var panel = document.getElementById('users-panel');
    if (!panel) return;
    if (panel.dataset.inited === '1') return;
    panel.dataset.inited = '1';

    var addBtn = panel.querySelector('#addUserBtn');

    function reloadPanel() {
      try {
        if (typeof window.loadContent === 'function') {
          var url = window.currentContentUrl || 'kategoria_panel.php?page=<?= $page ?>';
          window.loadContent(url);
          return;
        }
      } catch (e) { console.error(e); }
      window.location.href = 'kategoria_panel.php?page=<?= $page ?>';
    }

    if (addBtn) {
      addBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof window.openModal === 'function') window.openModal('kategoria_add.php');
        else window.location.href = 'kategoria_add.php';
      });
    }

    // Delegacja kliknięć w panelu
    panel.addEventListener('click', function(e) {
      var el = e.target;

      // Delete button (klasa delete)
      var del = el.closest && el.closest('button.delete');
      if (del) {
        e.preventDefault();
        e.stopPropagation();

        var id = del.getAttribute('data-id');
        var name = del.getAttribute('data-name') || id;
        if (!id) return;

        if (!confirm('Czy na pewno chcesz usunąć podmiot: ' + name + ' ?')) return;

        del.disabled = true;
        var origText = del.textContent;
        del.textContent = 'Usuwanie...';

        var fd = new FormData();
        fd.append('delete_id', id);

        fetch('kategoria_panel.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r){ return r.json(); })
          .then(function(data){
            del.disabled = false;
            del.textContent = origText;
            if (data && data.success) {
              reloadPanel();
            } else {
              var msg = (data && data.errors && data.errors.join) ? data.errors.join('\n') : (data && data.message) ? data.message : 'Błąd';
              alert(msg);
            }
          }).catch(function(err){
            del.disabled = false;
            del.textContent = origText;
            console.error(err);
            alert('Błąd sieci. Spróbuj ponownie.');
          });

        return;
      }

      // Open-modal (button with .open-modal) - Edytuj / Reset hasła
      var openEl = el.closest && el.closest('.open-modal');
      if (openEl) {
        if (!panel.contains(openEl)) return;
        e.preventDefault();
        e.stopPropagation();
        var url = openEl.getAttribute('data-url') || openEl.getAttribute('href');
        if (!url) return;
        if (typeof window.openModal === 'function') window.openModal(url);
        else window.location.href = url;
      }
    });
  };

  // Wywołanie defensywne (jeśli loader nie wywoła)
  try { window.initUsersPanel(); } catch(e){ console.error(e); }
  </script>
</div>