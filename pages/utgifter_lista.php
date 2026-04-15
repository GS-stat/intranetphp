<?php
require_once '../includes/config.php';
kravInloggning();

$meddelande = '';
$felmeddelande = '';

$kategorier = ['Hyra', 'El', 'Vatten', 'Internet', 'Telefon', 'Försäkring', 'Lön', 'Mat/Fika', 'Fordon', 'Verktyg', 'Marknadsföring', 'Bokföring', 'Övrigt'];
$momsAlternativ = [0 => '0% (ingen moms)', 12 => '12%', 25 => '25%'];

// ── HANTERA POST-ÅTGÄRDER ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'skapa') {
        $data = [
            ':kategori'     => trim($_POST['kategori'] ?? 'Övrigt'),
            ':beskrivning'  => trim($_POST['beskrivning'] ?? ''),
            ':belopp'       => (float)($_POST['belopp'] ?? 0),
            ':moms_procent' => (int)($_POST['moms_procent'] ?? 25),
            ':datum'        => $_POST['datum'] ?? date('Y-m-d'),
            ':aterkommande' => isset($_POST['aterkommande']) ? 1 : 0,
        ];
        if ($data[':beskrivning'] !== '' && $data[':belopp'] > 0) {
            skapaUtgift($pdo, $data);
            $meddelande = 'Utgiften sparades.';
        } else {
            $felmeddelande = 'Fyll i beskrivning och belopp.';
        }
    }

    if ($action === 'uppdatera') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            ':kategori'     => trim($_POST['kategori'] ?? 'Övrigt'),
            ':beskrivning'  => trim($_POST['beskrivning'] ?? ''),
            ':belopp'       => (float)($_POST['belopp'] ?? 0),
            ':moms_procent' => (int)($_POST['moms_procent'] ?? 25),
            ':datum'        => $_POST['datum'] ?? date('Y-m-d'),
            ':aterkommande' => isset($_POST['aterkommande']) ? 1 : 0,
        ];
        if ($id > 0 && $data[':beskrivning'] !== '') {
            uppdateraUtgift($pdo, $id, $data);
            $meddelande = 'Utgiften uppdaterades.';
        }
    }

    if ($action === 'radera') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            raderaUtgift($pdo, $id);
            $meddelande = 'Utgiften togs bort.';
        }
    }
}

// ── FILTER ────────────────────────────────────────────────
$filterAr    = isset($_GET['ar'])    ? (int)$_GET['ar']    : (int)date('Y');
$filterManad = isset($_GET['manad']) ? (int)$_GET['manad'] : 0; // 0 = alla månader

$utgifter = hamtaUtgifter($pdo, $filterAr, $filterManad > 0 ? $filterManad : null);

$totalExMoms = array_sum(array_column($utgifter, 'belopp'));
$totalInkMoms = 0;
foreach ($utgifter as $u) {
    $totalInkMoms += $u['belopp'] * (1 + $u['moms_procent'] / 100);
}

// Hämta utgift för redigering
$redigeraUtgift = null;
if (isset($_GET['redigera'])) {
    $redigeraUtgift = hamtaUtgift($pdo, (int)$_GET['redigera']);
}

$manadsNamn = ['', 'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni', 'Juli', 'Augusti', 'September', 'Oktober', 'November', 'December'];
?>
<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-receipt"></i> Allmänna utgifter</h1>
        <p class="text-muted">Hyra, el, försäkring och övriga löpande kostnader</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="ekonomi.php" class="btn btn-secondary me-2">
            <i class="fas fa-chart-line"></i> Ekonomiöversikt
        </a>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#nyUtgiftModal">
            <i class="fas fa-plus"></i> Ny utgift
        </button>
    </div>
</div>

<?php if ($meddelande): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($meddelande); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($felmeddelande): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($felmeddelande); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small mb-1">År</label>
                <select name="ar" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y === $filterAr ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Månad</label>
                <select name="manad" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0" <?php echo $filterManad === 0 ? 'selected' : ''; ?>>Alla månader</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m === $filterManad ? 'selected' : ''; ?>><?php echo $manadsNamn[$m]; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Summering -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center border-0 bg-light">
            <div class="card-body py-3">
                <div class="text-muted small">Totalt ex. moms</div>
                <div class="fs-4 fw-bold text-danger"><?php echo number_format($totalExMoms, 0, ',', ' '); ?> kr</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-0 bg-light">
            <div class="card-body py-3">
                <div class="text-muted small">Totalt ink. moms</div>
                <div class="fs-4 fw-bold text-dark"><?php echo number_format($totalInkMoms, 0, ',', ' '); ?> kr</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-0 bg-light">
            <div class="card-body py-3">
                <div class="text-muted small">Antal poster</div>
                <div class="fs-4 fw-bold"><?php echo count($utgifter); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabell -->
<div class="card">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list"></i> Utgiftsposter <?php echo $filterAr; ?><?php echo $filterManad > 0 ? ' – ' . $manadsNamn[$filterManad] : ''; ?></h5>
        <a href="../ajax/export_ekonomi.php?typ=utgifter&ar=<?php echo $filterAr; ?>&manad=<?php echo $filterManad; ?>" class="btn btn-sm btn-light">
            <i class="fas fa-file-csv"></i> Exportera CSV
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($utgifter)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-receipt fa-2x mb-2"></i><br>Inga utgifter registrerade för vald period
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Datum</th>
                        <th>Kategori</th>
                        <th>Beskrivning</th>
                        <th class="text-end">Ex. moms</th>
                        <th class="text-center">Moms</th>
                        <th class="text-end">Ink. moms</th>
                        <th class="text-center">Återkom.</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utgifter as $u):
                        $inkMoms = $u['belopp'] * (1 + $u['moms_procent'] / 100);
                    ?>
                    <tr class="<?php echo $u['aterkommande'] ? 'table-info' : ''; ?>">
                        <td class="text-muted small"><?php echo date('d/m/Y', strtotime($u['datum'])); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['kategori']); ?></span></td>
                        <td><?php echo htmlspecialchars($u['beskrivning']); ?></td>
                        <td class="text-end fw-semibold"><?php echo number_format($u['belopp'], 0, ',', ' '); ?> kr</td>
                        <td class="text-center small text-muted"><?php echo $u['moms_procent']; ?>%</td>
                        <td class="text-end"><?php echo number_format($inkMoms, 0, ',', ' '); ?> kr</td>
                        <td class="text-center">
                            <?php if ($u['aterkommande']): ?>
                                <span class="badge bg-info text-dark"><i class="fas fa-sync-alt"></i> Ja</span>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?redigera=<?php echo $u['id']; ?>&ar=<?php echo $filterAr; ?>&manad=<?php echo $filterManad; ?>"
                               class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Ta bort utgiften?')">
                                <input type="hidden" name="action" value="radera">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td colspan="3" class="fw-bold">Totalt</td>
                        <td class="text-end fw-bold"><?php echo number_format($totalExMoms, 0, ',', ' '); ?> kr</td>
                        <td></td>
                        <td class="text-end fw-bold"><?php echo number_format($totalInkMoms, 0, ',', ' '); ?> kr</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Ny utgift -->
<div class="modal fade" id="nyUtgiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="skapa">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Ny utgift</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="kategori" class="form-select">
                            <?php foreach ($kategorier as $kat): ?>
                            <option value="<?php echo $kat; ?>"><?php echo $kat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beskrivning <span class="text-danger">*</span></label>
                        <input type="text" name="beskrivning" class="form-control" placeholder="T.ex. Hyra maj 2026" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Belopp ex. moms (kr) <span class="text-danger">*</span></label>
                            <input type="number" name="belopp" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Momssats</label>
                            <select name="moms_procent" class="form-select">
                                <?php foreach ($momsAlternativ as $pct => $label): ?>
                                <option value="<?php echo $pct; ?>" <?php echo $pct === 25 ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Datum</label>
                        <input type="date" name="datum" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="aterkommande" id="aterkommande" class="form-check-input" value="1">
                        <label class="form-check-label" for="aterkommande">
                            <i class="fas fa-sync-alt text-info"></i> Återkommande varje månad
                            <small class="text-muted d-block">Beloppet räknas automatiskt in varje månad i statistiken</small>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-save"></i> Spara</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Redigera utgift -->
<?php if ($redigeraUtgift): ?>
<div class="modal fade" id="redigeraUtgiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="uppdatera">
                <input type="hidden" name="id" value="<?php echo $redigeraUtgift['id']; ?>">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Redigera utgift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="kategori" class="form-select">
                            <?php foreach ($kategorier as $kat): ?>
                            <option value="<?php echo $kat; ?>" <?php echo $redigeraUtgift['kategori'] === $kat ? 'selected' : ''; ?>><?php echo $kat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beskrivning</label>
                        <input type="text" name="beskrivning" class="form-control" value="<?php echo htmlspecialchars($redigeraUtgift['beskrivning']); ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Belopp ex. moms (kr)</label>
                            <input type="number" name="belopp" class="form-control" step="0.01" min="0" value="<?php echo $redigeraUtgift['belopp']; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Momssats</label>
                            <select name="moms_procent" class="form-select">
                                <?php foreach ($momsAlternativ as $pct => $label): ?>
                                <option value="<?php echo $pct; ?>" <?php echo (int)$redigeraUtgift['moms_procent'] === $pct ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Datum</label>
                        <input type="date" name="datum" class="form-control" value="<?php echo $redigeraUtgift['datum']; ?>">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="aterkommande" id="aterkommande_edit" class="form-check-input" value="1" <?php echo $redigeraUtgift['aterkommande'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aterkommande_edit">
                            <i class="fas fa-sync-alt text-info"></i> Återkommande varje månad
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="utgifter_lista.php?ar=<?php echo $filterAr; ?>" class="btn btn-secondary">Avbryt</a>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Uppdatera</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('redigeraUtgiftModal')).show();
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
