<?php
require_once '../includes/config.php';
kravInloggning();

// -------------------------------------------------------
// Inläsning av filter- och pagineringsparametrar från URL
// -------------------------------------------------------
$sok     = trim($_GET['sok']     ?? '');
$status  = trim($_GET['status']  ?? '');
$betald  = isset($_GET['betald']) && $_GET['betald'] !== '' ? $_GET['betald'] : '';
$sida    = max(1, (int)($_GET['sida']    ?? 1));
$flagga  = isset($_GET['flagga']) && $_GET['flagga'] !== '' ? $_GET['flagga'] : '';
$perSida = in_array((int)($_GET['per'] ?? 25), [10, 25, 50, 100])
           ? (int)($_GET['per'] ?? 25)
           : 25;

$filter = [
    'sok'    => $sok,
    'status' => $status,
    'betald' => $betald,
    'flagga' => $flagga,
];

$resultat = hamtaFiltereradeProjekt($pdo, $filter, $sida, $perSida);
$projekt  = $resultat['projekt'];
$total    = $resultat['total'];
$sidor    = $resultat['sidor'];
$sida     = $resultat['sida'];

// Hjälpfunktion: bygg URL med uppdaterad parameter
function paginUrl(array $extra = []): string {
    $params = array_merge([
        'sok'    => $_GET['sok']    ?? '',
        'status' => $_GET['status'] ?? '',
        'betald' => $_GET['betald'] ?? '',
        'per'    => $_GET['per']    ?? 25,
        'sida'   => $_GET['sida']   ?? 1,
    ], $extra);

    // Ta bort tomma parametrar
    $params = array_filter($params, fn($v) => $v !== '');
    return '?' . http_build_query($params);
}

// Räkna ut visningsintervall
$fran = $total === 0 ? 0 : ($sida - 1) * $perSida + 1;
$till = min($sida * $perSida, $total);
?>
<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-list"></i> Alla projekt</h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="projekt_skapa.php" class="btn btn-danger">
            <i class="fas fa-plus-circle"></i> Nytt projekt
        </a>
    </div>
</div>

<?php if (isset($_GET['meddelande'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($_GET['meddelande']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">

        <!-- ================================ -->
        <!-- FILTER (auto-submit via JS)      -->
        <!-- ================================ -->
        <form method="GET" id="filterForm" action="">
            <input type="hidden" name="sida" value="1" id="sidaInput">
            <input type="hidden" name="per"  value="<?php echo $perSida; ?>" id="perInput">

            <div class="row g-2 mb-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted small mb-1">Sök</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="sok" id="searchInput" class="form-control"
                               placeholder="Regnr, rubrik, kontakt..."
                               value="<?php echo htmlspecialchars($sok); ?>" autocomplete="off">
                        <?php if ($sok): ?>
                        <button type="button" class="btn btn-outline-secondary" id="clearSok" title="Rensa sökning">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Status</label>
                    <select name="status" id="statusFilter" class="form-select">
                        <option value="">Alla statusar</option>
                        <option value="inkommen" <?php echo $status === 'inkommen' ? 'selected' : ''; ?>>Inkommen</option>
                        <option value="pågående" <?php echo $status === 'pågående' ? 'selected' : ''; ?>>Pågående</option>
                        <option value="avslutad" <?php echo $status === 'avslutad' ? 'selected' : ''; ?>>Avslutad</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Betalstatus</label>
                    <select name="betald" id="betaldFilter" class="form-select">
                        <option value="">Alla</option>
                        <option value="1" <?php echo $betald === '1' ? 'selected' : ''; ?>>Betald</option>
                        <option value="0" <?php echo $betald === '0' ? 'selected' : ''; ?>>Obetald</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Per sida</label>
                    <select name="per" id="perSidaFilter" class="form-select">
                        <?php foreach ([10, 25, 50, 100] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $perSida === $opt ? 'selected' : ''; ?>>
                            <?php echo $opt; ?> rader
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 text-end">
                    <?php if ($sok || $status || $betald !== ''): ?>
                    <a href="projekt_lista.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Rensa filter
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- ================================ -->
        <!-- RESULTATINFO                     -->
        <!-- ================================ -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="text-muted">
                <?php if ($total === 0): ?>
                    Inga projekt hittades
                <?php else: ?>
                    Visar <strong><?php echo $fran; ?>–<?php echo $till; ?></strong>
                    av <strong><?php echo $total; ?></strong> projekt
                    <?php if ($sok || $status || $betald !== ''): ?>
                        <span class="text-primary">(filtrerat)</span>
                    <?php endif; ?>
                <?php endif; ?>
            </small>
            <small class="text-muted">
                <i class="fas fa-hand-pointer"></i> Klicka på en rad för att öppna projektet
            </small>
        </div>

        <!-- ================================ -->
        <!-- TABELL                           -->
        <!-- ================================ -->
        <div class="table-responsive">
            <table class="projekt-tabell" id="projektTabell">
                <thead>
                    <tr>
                        <th style="width:8px" class="border-0"></th>
                        <th style="width:46px">ID</th>
                        <th style="width:100px">Regnr</th>
                        <th>Uppdrag</th>
                        <th>Kontakt</th>
                        <th style="width:105px">Status</th>
                        <th style="width:110px">Ekonomi</th>
                        <th style="width:90px">Datum</th>
                        <th style="width:120px" class="text-center">Åtgärder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projekt as $p): ?>
                    <tr class="projekt-rad <?php echo !empty($p['flagga']) ? 'rad-flaggad' : ''; ?>"
                        onclick="window.location.href='projekt_visa.php?id=<?php echo $p['id']; ?>'">
                        <td class="rad-indikator p-0">
                            <?php if (!empty($p['flagga'])): ?>
                                <div class="flagg-linje" title="Se över projektet"></div>
                            <?php endif; ?>
                        </td>

                        <td class="text-muted small align-middle">#<?php echo $p['id']; ?></td>

                        <td class="align-middle">
                            <span class="regnr-pill"><?php echo htmlspecialchars($p['regnummer']); ?></span>
                        </td>

                        <td class="align-middle">
                            <div class="fw-semibold lh-sm"><?php echo htmlspecialchars($p['rubrik']); ?></div>
                            <div class="mt-1"><?php echo getKundTypBadge($p['kundTyp'] ?? 'Privat'); ?></div>
                        </td>

                        <td class="align-middle">
                            <div class="lh-sm"><?php echo htmlspecialchars($p['kontakt_person_namn']); ?></div>
                            <?php if (!empty($p['kontakt_person_telefon'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($p['kontakt_person_telefon']); ?></small>
                            <?php endif; ?>
                        </td>

                        <td class="cell-status align-middle"><?php echo getStatusBadge($p['status']); ?></td>

                        <td class="align-middle">
                            <div class="fw-semibold lh-sm">
                                <?php echo $p['pris'] ? number_format($p['pris'], 0, ',', ' ') . ' kr' : '–'; ?>
                            </div>
                            <div class="cell-betald mt-1">
                                <?php if ($p['betald']): ?>
                                    <small class="betald-ja"><i class="fas fa-check-circle"></i> Betald</small>
                                <?php else: ?>
                                    <small class="betald-nej"><i class="fas fa-circle"></i> Obetald</small>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="align-middle">
                            <?php if ($p['planDate']): ?>
                            <div class="small fw-semibold"><?php echo date('d/m', strtotime($p['planDate'])); ?></div>
                            <?php else: ?>
                            <div class="small text-muted">–</div>
                            <?php endif; ?>
                            <small class="text-muted"><?php echo date('d/m/y', strtotime($p['createdDate'])); ?></small>
                        </td>

                        <td onclick="event.stopPropagation();" class="text-center align-middle">
                            <div class="atgard-knappar">
                            <a href="projekt_redigera.php?id=<?php echo $p['id']; ?>"
                               class="atg-btn" title="Redigera">
                                <i class="fas fa-pen"></i>
                            </a>
                            <?php if ($p['status'] !== 'avslutad'): ?>
                            <button class="atg-btn atg-avsluta snabb-avsluta"
                                    data-id="<?php echo $p['id']; ?>"
                                    title="Avsluta projekt">
                                <i class="fas fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (!$p['betald']): ?>
                            <button class="atg-btn atg-betald snabb-betald"
                                    data-id="<?php echo $p['id']; ?>"
                                    title="Markera som betald">
                                <i class="fas fa-coins"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (arAdmin()): ?>
                            <a href="projekt_radera.php?id=<?php echo $p['id']; ?>"
                               class="atg-btn atg-radera"
                               title="Radera"
                               onclick="return confirm('Radera projektet?')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Tom lista -->
        <?php if ($total === 0): ?>
        <div class="text-center py-5">
            <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
            <?php if ($sok || $status || $betald !== ''): ?>
                <h5 class="text-muted">Inga projekt matchade sökningen</h5>
                <a href="projekt_lista.php" class="btn btn-outline-secondary mt-2">
                    <i class="fas fa-times"></i> Rensa filter
                </a>
            <?php else: ?>
                <h5 class="text-muted">Inga projekt ännu</h5>
                <a href="projekt_skapa.php" class="btn btn-danger mt-2">
                    <i class="fas fa-plus-circle"></i> Skapa första projektet
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ================================ -->
        <!-- PAGINERING                       -->
        <!-- ================================ -->
        <?php if ($sidor > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">

            <!-- Vänster: snabbinfo -->
            <small class="text-muted">
                Sida <?php echo $sida; ?> av <?php echo $sidor; ?>
            </small>

            <!-- Mitten: sidnavigation -->
            <nav aria-label="Sidnavigation">
                <ul class="pagination pagination-sm mb-0">

                    <!-- Föregående -->
                    <li class="page-item <?php echo $sida <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(paginUrl(['sida' => $sida - 1])); ?>"
                           aria-label="Föregående">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>

                    <?php
                    // Smart sidvisning: alltid visa första, sista och ±2 runt aktuell sida
                    $visaSidor = [];
                    for ($i = 1; $i <= $sidor; $i++) {
                        if ($i === 1 || $i === $sidor || abs($i - $sida) <= 2) {
                            $visaSidor[] = $i;
                        }
                    }
                    $prev = null;
                    foreach ($visaSidor as $s):
                        if ($prev !== null && $s - $prev > 1):
                    ?>
                    <li class="page-item disabled">
                        <span class="page-link">…</span>
                    </li>
                    <?php
                        endif;
                        $prev = $s;
                    ?>
                    <li class="page-item <?php echo $s === $sida ? 'active' : ''; ?>">
                        <a class="page-link"
                           href="<?php echo htmlspecialchars(paginUrl(['sida' => $s])); ?>">
                            <?php echo $s; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>

                    <!-- Nästa -->
                    <li class="page-item <?php echo $sida >= $sidor ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(paginUrl(['sida' => $sida + 1])); ?>"
                           aria-label="Nästa">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>

                </ul>
            </nav>

            <!-- Höger: hoppa till sida -->
            <form method="GET" class="d-flex align-items-center gap-1" id="hoppaTillForm">
                <?php if ($sok):    ?><input type="hidden" name="sok"    value="<?php echo htmlspecialchars($sok); ?>"><?php endif; ?>
                <?php if ($status): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>"><?php endif; ?>
                <?php if ($betald !== ''): ?><input type="hidden" name="betald" value="<?php echo htmlspecialchars($betald); ?>"><?php endif; ?>
                <input type="hidden" name="per" value="<?php echo $perSida; ?>">
                <label class="text-muted small mb-0 me-1">Gå till sida:</label>
                <input type="number" name="sida" class="form-control form-control-sm"
                       style="width: 65px;" min="1" max="<?php echo $sidor; ?>"
                       value="<?php echo $sida; ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

        </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Auto-submit vid filterändring (dropdowns)
    ['statusFilter', 'betaldFilter', 'perSidaFilter'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', function () {
            document.getElementById('sidaInput').value = 1;
            document.getElementById('filterForm').submit();
        });
    });

    // Sök: submit efter 400 ms utan tangentbordstryck (debounce)
    var searchInput = document.getElementById('searchInput');
    var searchTimer;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                document.getElementById('sidaInput').value = 1;
                document.getElementById('filterForm').submit();
            }, 400);
        });
    }

    // Rensa sök-knapp
    var clearSok = document.getElementById('clearSok');
    if (clearSok) {
        clearSok.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            document.getElementById('sidaInput').value = 1;
            document.getElementById('filterForm').submit();
        });
    }

    // ── Snabbåtgärder: Avsluta ──
    document.querySelectorAll('.snabb-avsluta').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!confirm('Sätt projektet som Avslutad?')) return;
            snabbAction(this, 'avsluta');
        });
    });

    // ── Snabbåtgärder: Betald ──
    document.querySelectorAll('.snabb-betald').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!confirm('Markera projektet som Betalt?')) return;
            snabbAction(this, 'betald');
        });
    });

    function snabbAction(knapp, action) {
        var id  = knapp.dataset.id;
        var rad = knapp.closest('tr');

        knapp.disabled = true;
        knapp.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        var fd = new FormData();
        fd.append('action',     action);
        fd.append('projekt_id', id);

        fetch('../ajax/snabb_action.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    if (action === 'avsluta') {
                        var statusCell = rad.querySelector('.cell-status');
                        if (statusCell) statusCell.innerHTML = '<span class="badge bg-success">Avslutad</span>';
                        knapp.remove();
                    } else if (action === 'betald') {
                        var betaldDiv = rad.querySelector('.cell-betald');
                        if (betaldDiv) betaldDiv.innerHTML = '<small class="betald-ja"><i class="fas fa-check-circle"></i> Betald</small>';
                        knapp.remove();
                    }
                    rad.style.transition = 'background-color 0.6s';
                    rad.style.backgroundColor = 'rgba(40,167,69,0.12)';
                    setTimeout(function () { rad.style.backgroundColor = ''; }, 1400);
                } else {
                    alert(data.message || 'Något gick fel');
                    knapp.disabled = false;
                    knapp.innerHTML = action === 'avsluta'
                        ? '<i class="fas fa-check"></i>'
                        : '<i class="fas fa-coins"></i>';
                }
            })
            .catch(function () {
                alert('Nätverksfel – försök igen');
                knapp.disabled = false;
                knapp.innerHTML = action === 'avsluta'
                    ? '<i class="fas fa-check"></i>'
                    : '<i class="fas fa-coins"></i>';
            });
    }
});
</script>

<style>
/* ── Projekt-tabell ── */
.projekt-tabell {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
}
.projekt-tabell thead tr {
    background: #212529;
    color: #fff;
}
.projekt-tabell thead th {
    padding: 10px 12px;
    font-weight: 600;
    font-size: 0.78rem;
    letter-spacing: .03em;
    text-transform: uppercase;
    border: none;
    white-space: nowrap;
}
.projekt-tabell tbody tr {
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color .12s ease, box-shadow .12s ease;
}
.projekt-tabell tbody tr:hover {
    background: #fdf3f4;
    box-shadow: inset 0 0 0 1px rgba(220,53,69,.15);
}
.projekt-tabell tbody td {
    padding: 10px 12px;
    vertical-align: middle;
}

/* Flagg-indikator: röd vänsterkant */
.rad-indikator { padding: 0 !important; width: 6px; }
.flagg-linje {
    width: 6px;
    height: 100%;
    min-height: 44px;
    background: #dc3545;
    border-radius: 3px 0 0 3px;
}
.rad-flaggad { background: #fff9f9; }
.rad-flaggad:hover { background: #fdf3f4; }

/* Regnr-pill */
.regnr-pill {
    display: inline-block;
    font-family: 'Courier New', monospace;
    font-weight: 700;
    font-size: 0.82rem;
    letter-spacing: .06em;
    color: #212529;
    background: #f8f9fa;
    border: 1.5px solid #dee2e6;
    border-radius: 4px;
    padding: 3px 8px;
    white-space: nowrap;
}

/* Betalstatus */
.betald-ja { color: #198754; font-size: 0.75rem; }
.betald-nej { color: #b8110b; font-size: 0.75rem; }

/* Åtgärds-knappar */
.atgard-knappar { display: flex; gap: 4px; justify-content: center; flex-wrap: nowrap; }
.atg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1.5px solid #dee2e6;
    background: #fff;
    color: #495057;
    font-size: 0.72rem;
    cursor: pointer;
    transition: all .12s ease;
    text-decoration: none;
}
.atg-btn:hover          { border-color: #adb5bd; background: #f8f9fa; color: #212529; }
.atg-avsluta            { border-color: #c3e6cb; color: #198754; }
.atg-avsluta:hover      { background: #198754; color: #fff; border-color: #198754; }
.atg-betald             { border-color: #b8daff; color: #0d6efd; }
.atg-betald:hover       { background: #0d6efd; color: #fff; border-color: #0d6efd; }
.atg-radera             { border-color: #f5c6cb; color: #dc3545; }
.atg-radera:hover       { background: #dc3545; color: #fff; border-color: #dc3545; }

/* Paginering */
.pagination .page-item.active .page-link {
    background-color: #dc3545;
    border-color: #dc3545;
}
.pagination .page-link { color: #dc3545; }
.pagination .page-item.disabled .page-link { color: #adb5bd; }
</style>

<?php include '../includes/footer.php'; ?>
