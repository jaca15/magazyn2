<?php
// zwrot_panel.php - fragment panelu zwrotów z paginacją (aktualizacja: reload fragmentu zamiast pełnej strony)
require_once 'auth.php';
require_login();
require_permission('return_equipment', 'Brak uprawnień do przyjmowania zwrotów.');
require 'polaczenie.php';

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$preselect_sprzet_id = (int)($_GET['sprzet_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Warunek WHERE (aktywny wypożyczenia)
//$where = " (w.status IS NULL OR w.status != 'zwrócono' OR w.data_zwrotu IS NULL) ";
$where = " (w.do_zwrotu >0) ";
$params = [];

// Opcjonalny filtr po sprzecie
if ($preselect_sprzet_id > 0) {
    $where .= " AND w.sprzet_id = :sprzet_id ";
    $params[':sprzet_id'] = $preselect_sprzet_id;
}

// Pobierz liczbę wszystkich wyników (do paginacji)
try {
    $countSql = "SELECT COUNT(*) AS cnt FROM wypozyczenia w WHERE $where";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn(0);
} catch (Throwable $e) {
    error_log('zwrot_panel.php count error: ' . $e->getMessage());
    $total = 0;
}

// Pobierz stronę wyników
$wypozyczenia = [];
if ($total > 0) {
    try {
        $sql = "
          SELECT w.id AS wyp_id, w.sprzet_id, w.ilosc AS wyp_ilosc, w.do_zwrotu AS wyp_zwrot_max, w.uzytkownik,
                 p.nazwa_skrocona AS wypozyczajacy_nazwa, w.data_wypozyczenia,
                 w.data_zwrotu, w.uwagi, w.uwagi_zw, w.status,
                 s.nazwa AS spr_nazwa, s.numer_inwentarzowy
          FROM wypozyczenia w
          LEFT JOIN podmioty p ON w.uzytkownik = p.id
          JOIN sprzet s ON s.id = w.sprzet_id
          WHERE $where
          ORDER BY w.data_wypozyczenia DESC
          LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        // bind params
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $wypozyczenia = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('zwrot_panel.php fetch error: ' . $e->getMessage());
        $wypozyczenia = [];
    }
}

// Funkcja generująca URL strony z zachowaniem filtra sprzet_id
function pageUrl($p, $sprzetId) {
    $q = ['page' => $p];
    if ($sprzetId > 0) $q['sprzet_id'] = $sprzetId;
    return 'zwrot_panel.php?' . http_build_query($q);
}

// Generowanie prostego bloku paginacji
$totalPages = ($per_page > 0) ? (int)ceil($total / $per_page) : 1;
$visiblePages = 7;
$half = floor($visiblePages / 2);
$start = max(1, $page - $half);
$end = min($totalPages, $start + $visiblePages - 1);
if ($end - $start + 1 < $visiblePages) {
    $start = max(1, $end - $visiblePages + 1);
}
?>
<div id="zwrot-panel" class="content">
  <h2>Panel zwrotów</h2>

  <p>W tym panelu możesz zwrócić jednocześnie wiele wypożyczeń. Zaznacz pozycje i podaj ilości do zwrotu (możesz zwrócić całość lub część).</p>

  <?php if (!$wypozyczenia): ?>
    <p class="info-message">Brak aktywnych wypożyczeń.</p>
  <?php else: ?>
    <!-- ACTIONS: przyciski dostosowane stylem aplikacji -->
    <div class="actions actions--toolbar" role="toolbar" aria-label="Akcje na liście">
      <button id="selectAll" class="btn btn-outline" type="button" title="Zaznacz wszystkie pozycje">
        ✓ Zaznacz wszystko
      </button>

      <button id="clearAll" class="btn btn-outline" type="button" title="Odznacz wszystkie pozycje">
        ⨯ Odznacz wszystko
      </button>

      <button id="submitReturns" class="btn btn-primary" type="button" title="Zwróć zaznaczone pozycje">
        ↺ Zwróć zaznaczone
      </button>
    </div>

    <form id="returnsForm">
     <!-- <table class="sprzet-table"> -->
          <table class="sprzet-table" style="width:100%; margin:0 auto;">
        <thead>
          <tr>
            <th></th>
            <th>ID wyp.</th>
            <th>Sprzęt</th>
            <th>Wypożyczający</th>
            <th>Data wypożyczenia</th>
            <th>Ilość wypożyczona</th>
            <th>Max. ilość zwrotu</th>
            <th>Uwagi</th>
            <th>Uwagi (Zwrot)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($wypozyczenia as $w): ?>
            <tr data-wyp-id="<?= (int)$w['wyp_id'] ?>" data-sprzet-id="<?= (int)$w['sprzet_id'] ?>" data-max="<?= (int)$w['wyp_ilosc'] ?>">
              <td><input type="checkbox" class="chk" name="sel[]" value="<?= (int)$w['wyp_id'] ?>"></td>
              <td><?= h($w['wyp_id']) ?></td>
              <td>
                <?= h($w['spr_nazwa']) ?>
                <br><span class="small">(nr inw.: <?= h($w['numer_inwentarzowy']) ?>)</span>
              </td>
              <td><?= h($w['wypozyczajacy_nazwa'] ?? 'Nieznany') ?></td>
              <td><?= h($w['data_wypozyczenia']) ?></td>
              <td><?= (int)$w['wyp_ilosc'] ?></td>
              <td>
                <input type="number" class="ret-qty" name="qty[<?= (int)$w['wyp_id'] ?>]"
                       min="1" max="<?= (int)$w['wyp_zwrot_max'] ?>"
                       value="<?= (int)$w['wyp_zwrot_max'] ?>">
              </td>
              <td class="row-msg"><?= h($w['uwagi']) ?></td>
              <td>
                <input type="text" name="uwagi_zw[<?= (int)$w['wyp_id'] ?>]" value="<?= h($w['uwagi_zw']) ?>">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </form>

    <!-- Paginacja -->
    <?php if ($totalPages > 1): ?>
      <nav class="pagination" aria-label="Paginacja">
        <ul>
          <?php if ($page > 1): ?>
            <li><a href="<?= h(pageUrl(1, $preselect_sprzet_id)) ?>">&laquo; Pierwsza</a></li>
            <li><a href="<?= h(pageUrl($page - 1, $preselect_sprzet_id)) ?>">&lsaquo; Poprzednia</a></li>
          <?php endif; ?>

          <?php for ($p = $start; $p <= $end; $p++): ?>
            <?php if ($p == $page): ?>
              <li class="current"><?= $p ?></li>
            <?php else: ?>
              <li><a href="<?= h(pageUrl($p, $preselect_sprzet_id)) ?>"><?= $p ?></a></li>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <li><a href="<?= h(pageUrl($page + 1, $preselect_sprzet_id)) ?>">Następna &rsaquo;</a></li>
            <li><a href="<?= h(pageUrl($totalPages, $preselect_sprzet_id)) ?>">Ostatnia &raquo;</a></li>
          <?php else: ?>
            <li class="disabled">Następna &rsaquo;</li>
            <li class="disabled">Ostatnia &raquo;</li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <div id="result"></div>

    <script>
    /*
      Inicjalizator panelu zwrotów (idempotentny).
      Loader dashboardu powinien wywołać window.initZwrotPanel() po wstawieniu HTML do DOM.
      Zamiast location.reload() używamy loadContent(...) jeśli jest dostępne, aby wrócić do panelu.
    */
    window.initZwrotPanel = function initZwrotPanel() {
      'use strict';
      var panel = document.getElementById('zwrot-panel');
      if (!panel) return;
      if (panel.dataset.zwrotInit === '1') return; // już zainicjalizowano
      panel.dataset.zwrotInit = '1';

      var resultBox = panel.querySelector('#result');
      var selectAllBtn = panel.querySelector('#selectAll');
      var clearAllBtn = panel.querySelector('#clearAll');
      var submitBtn = panel.querySelector('#submitReturns');
      var form = panel.querySelector('#returnsForm');

      function getCheckboxes() {
        return Array.prototype.slice.call(panel.querySelectorAll('.chk'));
      }

      if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function(e){
          e.preventDefault();
          getCheckboxes().forEach(function(chk){ chk.checked = true; });
        });
      }

      if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function(e){
          e.preventDefault();
          getCheckboxes().forEach(function(chk){ chk.checked = false; });
        });
      }

      // walidacja pól liczbowych przy wpisie (delegacja)
      panel.addEventListener('input', function(e){
        var t = e.target;
        if (!t) return;
        if (t.classList && t.classList.contains('ret-qty')) {
          var max = parseInt(t.getAttribute('max') || '0', 10) || 0;
          var val = parseInt(t.value || '0', 10) || 0;
          if (val < 1) t.value = 1;
          else if (val > max) t.value = max;
        }
      });

      if (submitBtn) {
        submitBtn.addEventListener('click', function(e){
          e.preventDefault();
          if (!form) return;
          resultBox.innerHTML = '';

          var selected = getCheckboxes().filter(function(c){ return c.checked; });
          if (!selected.length) {
            alert('Nie wybrano żadnych pozycji do zwrotu!');
            return;
          }

          var fd = new FormData();
          selected.forEach(function(chk){
            var row = chk.closest('tr');
            var id = chk.value;
            var qtyInput = row.querySelector('input[name="qty['+id+']"]');
            var uwagiZwInput = row.querySelector('input[name="uwagi_zw['+id+']"]');
            fd.append('ids[]', id);
            fd.append('qtys[]', qtyInput ? qtyInput.value : '0');
            fd.append('uwagi_zw['+id+']', uwagiZwInput ? uwagiZwInput.value : '');
          });

          submitBtn.disabled = true;
          fetch('zwrot_zapisz.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
          }).then(function(r){ return r.json(); })
          .then(function(json){
            submitBtn.disabled = false;
            if (!json) {
              resultBox.innerHTML = '<p class="error-message">Błędna odpowiedź serwera.</p>';
              return;
            }
            if (json.success) {
              alert ('Zwrot zakończony sukcesem.');
            } else {
              resultBox.innerHTML = '<p class="error-message">'+(json.message || 'Błąd')+'</p>';
            }
            if (Array.isArray(json.results)) {
              json.results.forEach(function(it){
                var row = panel.querySelector('tr[data-wyp-id="'+it.wyp_id+'"]');
                if (row) {
                  var cell = row.querySelector('.row-msg');
                  if (cell) cell.innerHTML = it.message ? '<span class="'+(it.success? 'ok':'error')+'">'+it.message+'</span>': '';
                }
              });
            }

            // zamiast location.reload() -> spróbuj przeładować fragment przez loadContent (dashboard),
            // w przeciwnym razie fallback do location.href lub location.reload()
            if (json.success) {
              setTimeout(function(){
                try {
                  if (typeof window.loadContent === 'function') {
                    // użyj poprzednio zapisanego url (loader powinien ustawić window.currentContentUrl)
                    var url = window.currentContentUrl || '<?= h("zwrot_panel.php?page={$page}") ?>';
                    window.loadContent(url);
                  } else if (window.location.href.indexOf('zwrot_panel.php') !== -1) {
                    // bez dashboardu - przeładuj stronę fragmentu
                    location.reload();
                  } else {
                    // fallback: przejdź na stronę panelu zwrotów
                    window.location.href = 'zwrot_panel.php?page=<?= $page ?>';
                  }
                } catch (e) {
                  // jeśli coś pójdzie nie tak - bezpieczne odświeżenie całej strony
                  console.error('reload fragmentu zwrot_panel błąd:', e);
                  location.reload();
                }
              }, 700);
            }
          }).catch(function(err){
            submitBtn.disabled = false;
            resultBox.innerHTML = '<p class="error-message">Błąd serwera: '+(err.message||err)+'</p>';
          });
        });
      }
    }; // koniec initZwrotPanel
    </script>
  <?php endif; ?>
</div>