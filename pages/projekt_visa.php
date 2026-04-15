<?php
require_once '../includes/config.php';
kravInloggning();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: projekt_lista.php');
    exit;
}

$projekt = hamtaProjekt($pdo, $id);
if (!$projekt) {
    header('Location: projekt_lista.php');
    exit;
}

$users = hamtaAllaAnvandare($pdo);
$rader = hamtaProjektRader($pdo, $id);
$projektkostnader = hamtaProjektKostnader($pdo, $id);
$totalProjektkostnad = beraknaTotalProjektKostnad($pdo, $id);

// Hantera POST: lägg till / radera projektkostnad
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pkAction = $_POST['pk_action'] ?? '';
    if ($pkAction === 'lagg_till') {
        $beskrivning  = trim($_POST['pk_beskrivning'] ?? '');
        $belopp       = (float)($_POST['pk_belopp'] ?? 0);
        $moms_procent = (int)($_POST['pk_moms'] ?? 25);
        $datum        = $_POST['pk_datum'] ?? date('Y-m-d');
        if ($beskrivning !== '' && $belopp > 0) {
            laggTillProjektKostnad($pdo, $id, $beskrivning, $belopp, $moms_procent, $datum);
        }
        header("Location: projekt_visa.php?id=$id&pk=ok");
        exit;
    }
    if ($pkAction === 'radera') {
        $pkId = (int)($_POST['pk_id'] ?? 0);
        if ($pkId > 0) raderaProjektKostnad($pdo, $pkId);
        header("Location: projekt_visa.php?id=$id");
        exit;
    }
    if ($pkAction === 'uppdatera') {
        $pkId         = (int)($_POST['pk_id'] ?? 0);
        $beskrivning  = trim($_POST['pk_beskrivning'] ?? '');
        $belopp       = (float)($_POST['pk_belopp'] ?? 0);
        $moms_procent = (int)($_POST['pk_moms'] ?? 25);
        $datum        = $_POST['pk_datum'] ?? date('Y-m-d');
        if ($pkId > 0 && $beskrivning !== '') {
            uppdateraProjektKostnad($pdo, $pkId, $beskrivning, $belopp, $moms_procent, $datum);
        }
        header("Location: projekt_visa.php?id=$id");
        exit;
    }
}

// Uppdatera efter POST (omladdning)
$projektkostnader    = hamtaProjektKostnader($pdo, $id);
$totalProjektkostnad = beraknaTotalProjektKostnad($pdo, $id);

// Hitta ansvarig tekniker-namn
$ansvarigNamn = '-';
if (!empty($projekt['ansvarig_tekniker'])) {
    foreach ($users as $u) {
        if ((int)$u['id'] === (int)$projekt['ansvarig_tekniker']) {
            $ansvarigNamn = htmlspecialchars($u['anvandarnamn']);
            break;
        }
    }
}

function e($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<?php include '../includes/header.php'; ?>

<?php if (isset($_GET['meddelande'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo e($_GET['meddelande']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>
            <i class="fas fa-file-alt"></i>
            Projekt #<?php echo $projekt['id']; ?> - <?php echo e($projekt['regnummer']); ?>
            <?php if (!empty($projekt['flagga'])): ?>
                <span class="badge bg-warning text-dark ms-2" title="Se över detta projekt">
                    <i class="fas fa-exclamation-circle"></i> Se över projekt
                </span>
            <?php endif; ?>
        </h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="projekt_lista.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Tillbaka
        </a>
        <a href="projekt_redigera.php?id=<?php echo $projekt['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Redigera
        </a>
        <a href="projekt_utskrift.php?id=<?php echo $projekt['id']; ?>" class="btn btn-info" target="_blank">
            <i class="fas fa-print"></i> Skriv ut
        </a>
    </div>
</div>

<div class="row">

    <!-- FORDONSINFORMATION -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-car"></i> Fordonsinformation</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="150">Regnummer:</th>
                        <td><strong><?php echo e($projekt['regnummer']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Rubrik:</th>
                        <td><?php echo e($projekt['rubrik']); ?></td>
                    </tr>
                    <tr>
                        <th>Beskrivning:</th>
                        <td><?php echo nl2br(e($projekt['beskrivning'])); ?></td>
                    </tr>
                    <?php if (!empty($projekt['felsokning'])): ?>
                    <tr>
                        <th>Felsökning:</th>
                        <td><?php echo nl2br(e($projekt['felsokning'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($projekt['atgard'])): ?>
                    <tr>
                        <th>Åtgärd:</th>
                        <td><?php echo nl2br(e($projekt['atgard'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($projekt['cmt'])): ?>
                    <tr>
                        <th>Kommentarer:</th>
                        <td><?php echo nl2br(e($projekt['cmt'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- KONTAKTPERSON -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Kontaktperson</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="150">Namn:</th>
                        <td><?php echo e($projekt['kontakt_person_namn']); ?></td>
                    </tr>
                    <tr>
                        <th>Telefon:</th>
                        <td><?php echo e($projekt['kontakt_person_telefon']); ?></td>
                    </tr>
                    <tr>
                        <th>E-post:</th>
                        <td><?php echo e($projekt['kontakt_person_email']); ?></td>
                    </tr>
                    <tr>
                        <th>Kundtyp:</th>
                        <td><?php echo getKundTypBadge($projekt['kundTyp'] ?? 'Privat'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="row">

    <!-- STATUS & PLANERING -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Status & Planering</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Status:</th>
                        <td><?php echo getStatusBadge($projekt['status']); ?></td>
                    </tr>
                    <tr>
                        <th>Skapad:</th>
                        <td><?php echo date('Y-m-d H:i', strtotime($projekt['createdDate'])); ?></td>
                    </tr>
                    <tr>
                        <th>Planerat datum:</th>
                        <td><?php echo $projekt['planDate'] ? date('Y-m-d', strtotime($projekt['planDate'])) : '-'; ?></td>
                    </tr>
                    <tr>
                        <th>Tid:</th>
                        <td>
                            <?php
                            echo $projekt['starttid']
                                ? date('H:i', strtotime($projekt['starttid'])) . ' – ' . date('H:i', strtotime($projekt['sluttid']))
                                : 'Ej angivet';
                            ?>
                        </td>
                    </tr>
                    <?php if (!empty($projekt['ansvarig_tekniker'])): ?>
                    <tr>
                        <th>Ansvarig tekniker:</th>
                        <td><?php echo $ansvarigNamn; ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Skapad av:</th>
                        <td><?php echo e($projekt['skapad_av_namn']); ?></td>
                    </tr>
                    <?php if (!empty($projekt['avslutad'])): ?>
                    <tr>
                        <th>Avslutad:</th>
                        <td><?php echo date('Y-m-d H:i', strtotime($projekt['avslutadDatum'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- EKONOMI -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-credit-card"></i> Ekonomi</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($rader)): ?>
                    <!-- Visa rader med specifikation -->
                    <table class="table table-sm table-borderless mb-2">
                        <thead>
                            <tr class="text-muted small">
                                <th>Beskrivning</th>
                                <th class="text-end">Summa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $radTotal = 0;
                            foreach ($rader as $rad):
                                $summa = ((float)$rad['pris'] * (float)$rad['antal']) - (float)$rad['rabatt'];
                                $radTotal += $summa;
                            ?>
                            <tr>
                                <td>
                                    <small class="text-muted"><?php echo e(ucfirst($rad['typ'])); ?></small><br>
                                    <?php echo e($rad['beskrivning']); ?>
                                    <small class="text-muted d-block">
                                        <?php echo number_format($rad['pris'], 0, ',', ' '); ?> kr
                                        × <?php echo rtrim(rtrim(number_format($rad['antal'], 2, ',', ' '), '0'), ','); ?>
                                        <?php echo $rad['typ'] === 'arbete' ? 'tim' : 'st'; ?>
                                        <?php if ($rad['rabatt'] > 0): ?>
                                            – rabatt <?php echo number_format($rad['rabatt'], 0, ',', ' '); ?> kr
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td class="text-end align-middle">
                                    <?php echo number_format($summa, 0, ',', ' '); ?> kr
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>Totalt:</strong>
                        <strong class="text-danger" style="font-size: 1.1rem;">
                            <?php echo number_format($radTotal, 0, ',', ' '); ?> kr
                        </strong>
                    </div>
                <?php else: ?>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <th width="130">Pris:</th>
                            <td>
                                <strong>
                                    <?php echo $projekt['pris']
                                        ? number_format($projekt['pris'], 2, ',', ' ') . ' kr'
                                        : 'Ej angivet'; ?>
                                </strong>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
                <div class="mt-2">
                    <?php if (!empty($projekt['betald'])): ?>
                        <span class="badge bg-success">Betald</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Obetald</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- DÄCKFÖRVARING -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-warehouse"></i> Däckförvaring</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($projekt['dackforvaring'])): ?>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <th>Aktiv:</th>
                            <td><span class="badge bg-success">Ja</span></td>
                        </tr>
                        <tr>
                            <th>Förvarings-ID:</th>
                            <td><?php echo e($projekt['dackforvaring_id'] ?: 'Ej angett'); ?></td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p class="text-muted mb-0">Ingen däckförvaring</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- ======================================== -->
<!-- INTERNA PROJEKTKOSTNADER (ej för kund)  -->
<!-- ======================================== -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-lock"></i> Interna projektkostnader
                    <small class="fw-normal ms-2 opacity-75">– syns ej för kund</small>
                </h5>
                <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#nyProjektkostnadModal">
                    <i class="fas fa-plus"></i> Lägg till kostnad
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($projektkostnader)): ?>
                <div class="text-center text-muted py-3 small">
                    <i class="fas fa-tools me-1"></i> Inga interna kostnader registrerade
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Datum</th>
                                <th>Beskrivning</th>
                                <th class="text-end">Ex. moms</th>
                                <th class="text-center">Moms</th>
                                <th class="text-end">Ink. moms</th>
                                <th style="width:90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projektkostnader as $pk):
                                $inkMoms = $pk['belopp'] * (1 + $pk['moms_procent'] / 100);
                            ?>
                            <tr>
                                <td class="small text-muted"><?php echo date('d/m/Y', strtotime($pk['datum'])); ?></td>
                                <td><?php echo e($pk['beskrivning']); ?></td>
                                <td class="text-end"><?php echo number_format($pk['belopp'], 0, ',', ' '); ?> kr</td>
                                <td class="text-center small text-muted"><?php echo $pk['moms_procent']; ?>%</td>
                                <td class="text-end"><?php echo number_format($inkMoms, 0, ',', ' '); ?> kr</td>
                                <td>
                                    <button class="btn btn-xs btn-outline-warning btn-sm"
                                            onclick="oppnaRedigeraPK(<?php echo $pk['id']; ?>, <?php echo json_encode($pk['beskrivning']); ?>, <?php echo $pk['belopp']; ?>, <?php echo $pk['moms_procent']; ?>, '<?php echo $pk['datum']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Ta bort kostnaden?')">
                                        <input type="hidden" name="pk_action" value="radera">
                                        <input type="hidden" name="pk_id" value="<?php echo $pk['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-warning">
                            <tr>
                                <td colspan="2" class="fw-bold">Totalt projektkostnader</td>
                                <td class="text-end fw-bold"><?php echo number_format($totalProjektkostnad, 0, ',', ' '); ?> kr</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php
            // Beräkna intäkt och vinst
            $intakt = !empty($rader) ? beraknaProjektSumma($rader) : (float)($projekt['pris'] ?? 0);
            $vinst  = $intakt - $totalProjektkostnad;
            $vinstFarg = $vinst >= 0 ? 'success' : 'danger';
            ?>
            <div class="card-footer bg-warning bg-opacity-25">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="small text-muted">Intäkt</div>
                        <div class="fw-bold text-success"><?php echo number_format($intakt, 0, ',', ' '); ?> kr</div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Projektkostnader</div>
                        <div class="fw-bold text-danger"><?php echo number_format($totalProjektkostnad, 0, ',', ' '); ?> kr</div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Vinst (proj. nivå)</div>
                        <div class="fw-bold text-<?php echo $vinstFarg; ?>"><?php echo number_format($vinst, 0, ',', ' '); ?> kr</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ny projektkostnad -->
<div class="modal fade" id="nyProjektkostnadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="pk_action" value="lagg_till">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Lägg till intern kostnad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Beskrivning <span class="text-danger">*</span></label>
                        <input type="text" name="pk_beskrivning" class="form-control" placeholder="T.ex. Reservdelar, underleverantör..." required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Belopp ex. moms (kr) <span class="text-danger">*</span></label>
                            <input type="number" name="pk_belopp" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Momssats</label>
                            <select name="pk_moms" class="form-select">
                                <option value="0">0% (ingen moms)</option>
                                <option value="12">12%</option>
                                <option value="25" selected>25%</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Datum</label>
                        <input type="date" name="pk_datum" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Spara</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Redigera projektkostnad -->
<div class="modal fade" id="redigeraPKModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="pk_action" value="uppdatera">
                <input type="hidden" name="pk_id" id="rPKid">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Redigera kostnad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Beskrivning</label>
                        <input type="text" name="pk_beskrivning" id="rPKbesk" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Belopp ex. moms (kr)</label>
                            <input type="number" name="pk_belopp" id="rPKbelopp" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Momssats</label>
                            <select name="pk_moms" id="rPKmoms" class="form-select">
                                <option value="0">0%</option>
                                <option value="12">12%</option>
                                <option value="25">25%</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Datum</label>
                        <input type="date" name="pk_datum" id="rPKdatum" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Uppdatera</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function oppnaRedigeraPK(id, beskrivning, belopp, moms, datum) {
    document.getElementById('rPKid').value    = id;
    document.getElementById('rPKbesk').value  = beskrivning;
    document.getElementById('rPKbelopp').value = belopp;
    document.getElementById('rPKmoms').value  = moms;
    document.getElementById('rPKdatum').value = datum;
    new bootstrap.Modal(document.getElementById('redigeraPKModal')).show();
}
<?php if (isset($_GET['pk'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('nyProjektkostnadModal')); // stäng vid redirect
});
<?php endif; ?>
</script>

<!-- ======================================== -->
<!-- BILDGALLERI                              -->
<!-- ======================================== -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-images"></i> Bildgalleri - Dokumentation</h5>
                <button type="button" class="btn btn-sm btn-danger"
                        data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-camera"></i> Ladda upp bild
                </button>
            </div>
            <div class="card-body">
                <div id="bildgalleri" class="row g-3">
                    <div class="text-center p-5 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Laddar bilder...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- UPLOAD MODAL -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-camera"></i> Ladda upp bild</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="projekt_id" id="projekt_id"
                           value="<?php echo $projekt['id']; ?>">
                    <div class="mb-3">
                        <label for="bild" class="form-label">Välj bild</label>
                        <input type="file" class="form-control" id="bild" name="bild" accept="image/*">
                        <div class="form-text">Stödjer: JPG, PNG, GIF, WEBP. Max 10 MB.</div>
                    </div>
                    <div id="uploadProgress" class="progress d-none">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             style="width: 0%">0%</div>
                    </div>
                    <div id="uploadMessage" class="mt-2"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Avbryt</button>
                <button type="button" class="btn btn-danger" id="uploadBtn">Ladda upp</button>
            </div>
        </div>
    </div>
</div>

<!-- LIGHTBOX MODAL -->
<div class="modal fade" id="lightboxModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark">
            <div class="modal-header border-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="lightboxImage" src="" class="img-fluid" style="max-height: 80vh;">
                <div class="mt-3 text-white" id="lightboxCaption"></div>
            </div>
        </div>
    </div>
</div>

<?php $extra_scripts = ['../assets/js/bildgalleri.js']; ?>
<?php include '../includes/footer.php'; ?>
