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
