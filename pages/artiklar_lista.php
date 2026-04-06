<?php
require_once '../includes/config.php';
kravAdmin();

$meddelande = '';
$error = '';

// ── Hantera POST-åtgärder (skapa / uppdatera / radera) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'skapa') {
        $data = [
            'namn'          => trim($_POST['namn'] ?? ''),
            'pris'          => max(0, (float)($_POST['pris'] ?? 0)),
            'tillat_rabatt' => isset($_POST['tillat_rabatt']) ? 1 : 0,
            'pris_disabled' => isset($_POST['pris_disabled']) ? 1 : 0,
            'aktiv'         => 1,
        ];
        if ($data['namn'] === '') {
            $error = 'Artikelnamn är obligatoriskt.';
        } elseif (skapaArtikel($pdo, $data)) {
            $meddelande = 'Artikeln skapades.';
        } else {
            $error = 'Kunde inte skapa artikeln.';
        }

    } elseif ($action === 'uppdatera') {
        $id   = (int)($_POST['id'] ?? 0);
        $data = [
            'namn'          => trim($_POST['namn'] ?? ''),
            'pris'          => max(0, (float)($_POST['pris'] ?? 0)),
            'tillat_rabatt' => isset($_POST['tillat_rabatt']) ? 1 : 0,
            'pris_disabled' => isset($_POST['pris_disabled']) ? 1 : 0,
            'aktiv'         => isset($_POST['aktiv']) ? 1 : 0,
        ];
        if ($data['namn'] === '') {
            $error = 'Artikelnamn är obligatoriskt.';
        } elseif (uppdateraArtikel($pdo, $id, $data)) {
            $meddelande = 'Artikeln uppdaterades.';
        } else {
            $error = 'Kunde inte uppdatera artikeln.';
        }

    } elseif ($action === 'radera') {
        $id = (int)($_POST['id'] ?? 0);
        if (raderaArtikel($pdo, $id)) {
            $meddelande = 'Artikeln raderades.';
        } else {
            $error = 'Kunde inte radera artikeln.';
        }
    }
}

$artiklar = hamtaAllaArtiklar($pdo);

// Artikel att redigera (från GET)
$redigeraArtikel = null;
if (isset($_GET['redigera'])) {
    $redigeraArtikel = hamtaArtikel($pdo, (int)$_GET['redigera']);
}
?>
<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-tags"></i> Artikelregister</h1>
        <p class="text-muted">Hantera produkter och tjänster som används i projektets prisrader.</p>
    </div>
    <div class="col-md-4 text-end">
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#nySkapaModal">
            <i class="fas fa-plus"></i> Ny artikel
        </button>
    </div>
</div>

<?php if ($meddelande): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($meddelande); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ================================ -->
<!-- ARTIKELTABELL                    -->
<!-- ================================ -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($artiklar)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-tags fa-3x mb-3"></i>
            <h5>Inga artiklar ännu</h5>
            <p>Skapa din första artikel med knappen ovan.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="artikel-tabell">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Namn</th>
                        <th style="width:130px">Pris</th>
                        <th style="width:130px" class="text-center">Tillåt rabatt</th>
                        <th style="width:130px" class="text-center">Pris låst</th>
                        <th style="width:100px" class="text-center">Status</th>
                        <th style="width:100px" class="text-center">Åtgärder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($artiklar as $a): ?>
                    <tr class="<?php echo $a['aktiv'] ? '' : 'artikel-inaktiv'; ?>">
                        <td class="text-muted small">#<?php echo $a['id']; ?></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($a['namn']); ?></td>
                        <td class="fw-bold"><?php echo number_format($a['pris'], 2, ',', ' '); ?> kr</td>
                        <td class="text-center">
                            <?php if ($a['tillat_rabatt']): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i> Ja</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="fas fa-times"></i> Nej</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($a['pris_disabled']): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-lock"></i> Låst</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border"><i class="fas fa-unlock"></i> Fritt</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($a['aktiv']): ?>
                                <span class="badge bg-success">Aktiv</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inaktiv</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button class="atg-btn atg-redigera"
                                    onclick="oppnaRedigera(<?php echo htmlspecialchars(json_encode($a)); ?>)"
                                    title="Redigera">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" style="display:inline"
                                  onsubmit="return confirm('Radera artikeln \'<?php echo htmlspecialchars(addslashes($a['namn'])); ?>\'?')">
                                <input type="hidden" name="action" value="radera">
                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                <button type="submit" class="atg-btn atg-radera" title="Radera">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ================================ -->
<!-- MODAL: NY ARTIKEL                -->
<!-- ================================ -->
<div class="modal fade" id="nySkapaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="skapa">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Ny artikel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php echo renderArtikelForm(); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-save"></i> Spara</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================================ -->
<!-- MODAL: REDIGERA ARTIKEL          -->
<!-- ================================ -->
<div class="modal fade" id="redigeraModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="uppdatera">
                <input type="hidden" name="id" id="redigeraId">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Redigera artikel</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="redigeraModalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-save"></i> Spara ändringar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
/**
 * Renderar formulärfälten för skapa/redigera
 * Används för "ny artikel"-modalen (tomma värden)
 */
function renderArtikelForm(array $a = []) {
    $namn          = htmlspecialchars($a['namn'] ?? '');
    $pris          = $a['pris'] ?? '';
    $tillat_rabatt = isset($a['tillat_rabatt']) ? (bool)$a['tillat_rabatt'] : true;
    $pris_disabled = isset($a['pris_disabled']) ? (bool)$a['pris_disabled'] : false;
    $aktiv         = isset($a['aktiv'])         ? (bool)$a['aktiv']         : true;

    ob_start(); ?>
    <div class="mb-3">
        <label class="form-label">Artikelnamn *</label>
        <input type="text" name="namn" class="form-control" value="<?php echo $namn; ?>" required
               placeholder="T.ex. Oljebyte, Däckmontering...">
    </div>
    <div class="mb-3">
        <label class="form-label">Pris (kr)</label>
        <input type="number" name="pris" class="form-control" value="<?php echo $pris; ?>"
               step="0.01" min="0" placeholder="0.00">
    </div>
    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="tillat_rabatt" id="tillat_rabatt_<?php echo uniqid(); ?>"
                   <?php echo $tillat_rabatt ? 'checked' : ''; ?>>
            <label class="form-check-label">
                <i class="fas fa-percent text-success"></i> Tillåt rabatt
                <small class="text-muted d-block">Användare kan ange rabatt på denna artikel</small>
            </label>
        </div>
    </div>
    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="pris_disabled" id="pris_disabled_<?php echo uniqid(); ?>"
                   <?php echo $pris_disabled ? 'checked' : ''; ?>>
            <label class="form-check-label">
                <i class="fas fa-lock text-warning"></i> Lås priset
                <small class="text-muted d-block">Användare kan inte ändra priset på denna artikel</small>
            </label>
        </div>
    </div>
    <?php if (!empty($a)): // Visa aktiv-toggle bara vid redigering ?>
    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="aktiv"
                   <?php echo $aktiv ? 'checked' : ''; ?>>
            <label class="form-check-label">Aktiv (visas vid val av artikel)</label>
        </div>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
?>

<script>
function oppnaRedigera(artikel) {
    document.getElementById('redigeraId').value = artikel.id;

    const body = document.getElementById('redigeraModalBody');
    body.innerHTML = `
        <div class="mb-3">
            <label class="form-label">Artikelnamn *</label>
            <input type="text" name="namn" class="form-control" value="${escHtml(artikel.namn)}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Pris (kr)</label>
            <input type="number" name="pris" class="form-control" value="${artikel.pris}" step="0.01" min="0">
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="tillat_rabatt" id="tr_${artikel.id}"
                       ${parseInt(artikel.tillat_rabatt) ? 'checked' : ''}>
                <label class="form-check-label" for="tr_${artikel.id}">
                    <i class="fas fa-percent text-success"></i> Tillåt rabatt
                    <small class="text-muted d-block">Användare kan ange rabatt på denna artikel</small>
                </label>
            </div>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="pris_disabled" id="pd_${artikel.id}"
                       ${parseInt(artikel.pris_disabled) ? 'checked' : ''}>
                <label class="form-check-label" for="pd_${artikel.id}">
                    <i class="fas fa-lock text-warning"></i> Lås priset
                    <small class="text-muted d-block">Användare kan inte ändra priset</small>
                </label>
            </div>
        </div>
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="aktiv" id="ak_${artikel.id}"
                       ${parseInt(artikel.aktiv) ? 'checked' : ''}>
                <label class="form-check-label" for="ak_${artikel.id}">Aktiv</label>
            </div>
        </div>
    `;

    new bootstrap.Modal(document.getElementById('redigeraModal')).show();
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/"/g,'&quot;')
        .replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<style>
.artikel-tabell {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
}
.artikel-tabell thead tr { background: #212529; color: #fff; }
.artikel-tabell thead th {
    padding: 10px 14px;
    font-weight: 600;
    font-size: 0.78rem;
    letter-spacing: .03em;
    text-transform: uppercase;
    border: none;
    white-space: nowrap;
}
.artikel-tabell tbody tr {
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color .12s ease;
}
.artikel-tabell tbody tr:hover { background: #fdf3f4; }
.artikel-tabell tbody td { padding: 10px 14px; vertical-align: middle; }
.artikel-inaktiv { opacity: 0.55; }
.artikel-inaktiv:hover { opacity: 0.8; }

.atg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px; height: 30px;
    border-radius: 6px;
    border: 1.5px solid #dee2e6;
    background: #fff;
    color: #495057;
    font-size: 0.72rem;
    cursor: pointer;
    transition: all .12s ease;
    text-decoration: none;
}
.atg-redigera { border-color: #ffc107; color: #856404; }
.atg-redigera:hover { background: #ffc107; color: #212529; }
.atg-radera   { border-color: #f5c6cb; color: #dc3545; }
.atg-radera:hover { background: #dc3545; color: #fff; border-color: #dc3545; }
</style>

<?php include '../includes/footer.php'; ?>
