<?php
// users_panel.php - fragment panelu użytkowników (dashboard-friendly, z paginacją i akcjami)
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
    csrf_require();
    header('Content-Type: application/json; charset=utf-8');
    $deleteId = (int)$_POST['delete_id'];

    $currentUserId = (int)($_SESSION['user']['id'] ?? $_SESSION['id'] ?? 0);
    if ($deleteId && $currentUserId === $deleteId) {
        echo json_encode(['success' => false, 'errors' => ['Nie można usunąć aktualnie zalogowanego użytkownika.']]);
        exit;
    }

    try {
        $q = $pdo->prepare("SELECT id, nazwa_uzytkownika, imie_nazwisko FROM uzytkownicy WHERE id = :id LIMIT 1");
        $q->execute([':id' => $deleteId]);
        $item = $q->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            echo json_encode(['success' => false, 'errors' => ['Użytkownik nie istnieje.']]);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM uzytkownicy WHERE id = :id");
        $del->execute([':id' => $deleteId]);

        echo json_encode(['success' => true, 'message' => 'Usunięto użytkownika: ' . ($item['imie_nazwisko'] ?: $item['nazwa_uzytkownika'])]);
        exit;
    } catch (Throwable $e) {
        error_log('users_panel delete error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'errors' => ['Błąd bazy danych: ' . $e->getMessage()]]);
        exit;
    }
}

/* --- Parametry paginacji --- */
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* --- Pobierz liczbę użytkowników i stronę danych --- */
try {
    $totalStmt = $pdo->query("SELECT COUNT(*) AS total FROM uzytkownicy");
    $totalRecords = (int)$totalStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('users_panel count error: ' . $e->getMessage());
    $totalRecords = 0;
}
$totalPages = max(1, (int)ceil($totalRecords / $perPage));

try {
    $stmt = $pdo->prepare("
        SELECT 
          id,
          nazwa_uzytkownika,
          imie_nazwisko,
          email,
          rola,
          COALESCE(created_at, data_utworzenia) AS created_at
        FROM uzytkownicy
        ORDER BY nazwa_uzytkownika ASC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('users_panel fetch error: ' . $e->getMessage());
    $rows = [];
}

/* Helper URL paginacji */
function pageUrlUsers($p) {
    return 'users_panel.php?' . http_build_query(['page' => $p]);
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
  <h2>Użytkownicy</h2>

  <p>Lista kont w systemie. Możesz edytować, zresetować hasło lub usunąć konto.</p>

  <div class="actions actions--toolbar" role="toolbar" aria-label="Akcje użytkowników" style="margin-bottom:10px;">
    <button id="addUserBtn" class="btn" type="button" title="Dodaj nowego użytkownika">＋ Dodaj użytkownika</button>
  </div>

  <?php if (empty($rows)): ?>
    <p class="info-message">Brak użytkowników.</p>
  <?php else: ?>
    <!-- Tabela ustawiona na szerokość 65% i wyśrodkowana -->
    <table class="tabela" role="table" aria-label="Lista użytkowników" style="width:75%; margin:0 auto;">
      <thead>
        <tr>
          <th>L.p.</th>
          <th>Login</th>
          <th>Nazwa</th>
          <th>Email</th>
          <th>Rola</th>
          <th>Utworzono</th>
          <!-- zwiększona szerokość kolumny Akcje -->
          <th class="col-actions" style="width:15%">Akcje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $idx => $r):
          $displayName = $r['imie_nazwisko'] ?: $r['nazwa_uzytkownika'];
        ?>
        <tr data-id="<?= (int)$r['id'] ?>">
          <td><?= h(($page - 1) * $perPage + $idx + 1) ?></td>
          <td title="<?= h($r['nazwa_uzytkownika']) ?>"><?= h($r['nazwa_uzytkownika']) ?></td>
          <td title="<?= h($displayName) ?>"><?= h($displayName) ?></td>
          <td><?= h($r['email'] ?? '') ?></td>
          <td><?= h($r['rola'] ?? '') ?></td>
          <td><?= h($r['created_at'] ?? '') ?></td>
          <!-- zastosuj klasę col-actions w komórce, by styl był spójny -->
          <td class="col-actions">
            <div class="akcje" role="group" aria-label="Akcje">
              <!-- Usunięto przycisk "Podgląd" zgodnie z prośbą -->
              <button type="button" class="btn-akcja edit open-modal" data-url="user_edit.php?id=<?= (int)$r['id'] ?>">Edytuj</button>
              <button type="button" class="btn-akcja edit open-modal" data-url="user_edit.php?id=<?= (int)$r['id'] ?>">Reset hasła</button>
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
          var url = window.currentContentUrl || 'users_panel.php?page=<?= $page ?>';
          window.loadContent(url);
          return;
        }
      } catch (e) { console.error(e); }
      window.location.href = 'users_panel.php?page=<?= $page ?>';
    }

    if (addBtn) {
      addBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (typeof window.openModal === 'function') window.openModal('user_add.php');
        else window.location.href = 'user_add.php';
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

        if (!confirm('Czy na pewno chcesz usunąć użytkownika: "' + name + '"?')) return;

        del.disabled = true;
        var origText = del.textContent;
        del.textContent = 'Usuwanie...';

        var fd = new FormData();
        fd.append('delete_id', id);

        fetch('users_panel.php', {
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