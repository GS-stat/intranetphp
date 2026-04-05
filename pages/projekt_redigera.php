<?php
require_once '../includes/config.php';
kravInloggning();

if (!isset($_GET['id'])) {
    header('Location: projekt_lista.php');
    exit;
}

$id      = (int)$_GET['id'];
$projekt = hamtaProjekt($pdo, $id);

if (!$projekt) {
    header('Location: projekt_lista.php');
    exit;
}

$users   = hamtaAllaAnvandare($pdo);
$rader   = hamtaProjektRader($pdo, $id);
$TIMPRIS = 850;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_project'])) {

    // Bygg rader och beräkna total
    $nyaRader = [];
    if (!empty($_POST['rader']) && is_array($_POST['rader'])) {
        foreach ($_POST['rader'] as $rad) {
            $beskrivning = trim($rad['beskrivning'] ?? '');
            if ($beskrivning === '') continue;

            $typ    = $rad['typ'] === 'arbete' ? 'arbete' : 'material';
            $antal  = max(0, (float)($rad['antal'] ?? 1));
            $pris   = ($typ === 'arbete') ? $TIMPRIS : max(0, (float)($rad['pris'] ?? 0));
            $rabatt = ($typ === 'arbete') ? max(0, (float)($rad['rabatt'] ?? 0)) : 0;

            $nyaRader[] = [
                'typ'         => $typ,
                'beskrivning' => $beskrivning,
                'pris'        => $pris,
                'antal'       => $antal,
                'rabatt'      => $rabatt,
            ];
        }
    }

    $prisFranRader = !empty($nyaRader) ? beraknaProjektSumma($nyaRader) : null;
    $manuellPris   = !empty($_POST['pris']) ? (float)$_POST['pris'] : null;
    $slutPris      = $prisFranRader !== null ? $prisFranRader : $manuellPris;

    $ansvarig = !empty($_POST['ansvarig_tekniker']) ? (int)$_POST['ansvarig_tekniker'] : null;

    $data = [
        'regnummer'             => strtoupper(trim($_POST['regnummer'])),
        'rubrik'                => trim($_POST['rubrik']),
        'beskrivning'           => trim($_POST['beskrivning'] ?? ''),
        'felsokning'            => trim($_POST['felsokning'] ?? '') ?: null,
        'atgard'                => trim($_POST['atgard'] ?? '') ?: null,
        'cmt'                   => trim($_POST['cmt'] ?? '') ?: null,
        'planDate'              => $_POST['planDate'] ?: null,
        'starttid'              => $_POST['starttid'] ?: null,
        'sluttid'               => $_POST['sluttid'] ?: null,
        'pris'                  => $slutPris,
        'betald'                => isset($_POST['betald']) ? 1 : 0,
        'dackforvaring'         => isset($_POST['dackforvaring']) ? 1 : 0,
        'dackforvaring_id'      => trim($_POST['dackforvaring_id'] ?? '') ?: null,
        'kontakt_person_namn'   => trim($_POST['kontakt_person_namn']),
        'kontakt_person_telefon'=> trim($_POST['kontakt_person_telefon']),
        'kontakt_person_email'  => trim($_POST['kontakt_person_email'] ?? '') ?: null,
        'kundTyp'               => $_POST['kundTyp'],
        'status'                => $_POST['status'],
        'ansvarig_tekniker'     => $ansvarig,
        'flagga'                => isset($_POST['flagga']) ? 1 : 0,
    ];

    try {
        if (uppdateraProjekt($pdo, $id, $data)) {
            sparaProjektRader($pdo, $id, $nyaRader);

            // Försök skicka SMS-kvittens om projektet nu är avslutad + betald.
            require_once '../includes/sms.php';
            skickaSmsKvittens($pdo, $id);

            header('Location: projekt_visa.php?id=' . $id . '&meddelande=Projekt+uppdaterat');
            exit;
        } else {
            $error = "Kunde inte uppdatera projektet.";
        }
    } catch (Throwable $e) {
        error_log('projekt_redigera spara fel: ' . $e->getMessage());
        $error = "Fel: " . $e->getMessage() . " i " . basename($e->getFile()) . " rad " . $e->getLine();
    }
}

// Beräkna initial display för ansvarig tekniker
// Visa om: status != inkommen OCH ansvarig är satt
$visaAnsvarig = ($projekt['status'] !== 'inkommen' && !empty($projekt['ansvarig_tekniker'])) ? 'block' : 'none';
?>
<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>
            <i class="fas fa-edit"></i> Redigera projekt #<?php echo $projekt['id']; ?>
            <?php if (!empty($projekt['flagga'])): ?>
                <span class="badge bg-warning text-dark ms-2">
                    <i class="fas fa-exclamation-circle"></i> Se över projekt
                </span>
            <?php endif; ?>
        </h1>
        <p class="text-muted">Ändra uppgifterna för projektet</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="projekt_visa.php?id=<?php echo $projekt['id']; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Tillbaka till projekt
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="projektForm">
            <input type="hidden" name="update_project" value="1">

            <!-- ================================ -->
            <!-- FORDONSINFORMATION & KONTAKT     -->
            <!-- ================================ -->
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3 border-bottom pb-2">
                        <i class="fas fa-car text-danger"></i> Fordonsinformation
                    </h5>

                    <div class="mb-3">
                        <label for="regnummer" class="form-label">Registreringsnummer *</label>
                        <input type="text" class="form-control" id="regnummer" name="regnummer" required
                               value="<?php echo htmlspecialchars($projekt['regnummer']); ?>"
                               style="text-transform: uppercase" placeholder="ABC123">
                    </div>

                    <div class="mb-3">
                        <label for="rubrik" class="form-label">Rubrik *</label>
                        <input type="text" class="form-control" id="rubrik" name="rubrik" required
                               value="<?php echo htmlspecialchars($projekt['rubrik']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="beskrivning" class="form-label">Beskrivning av arbete</label>
                        <textarea class="form-control" id="beskrivning" name="beskrivning" rows="3"><?php echo htmlspecialchars($projekt['beskrivning'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="felsokning" class="form-label">Felsökning</label>
                        <textarea class="form-control" id="felsokning" name="felsokning" rows="2"
                                  placeholder="Beskriv felet / symptomen..."><?php echo htmlspecialchars($projekt['felsokning'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="atgard" class="form-label">Åtgärd</label>
                        <textarea class="form-control" id="atgard" name="atgard" rows="2"
                                  placeholder="Genomförd åtgärd..."><?php echo htmlspecialchars($projekt['atgard'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="cmt" class="form-label">Interna kommentarer</label>
                        <textarea class="form-control" id="cmt" name="cmt" rows="2"
                                  placeholder="Syns bara internt..."><?php echo htmlspecialchars($projekt['cmt'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="flagga" name="flagga"
                                   <?php echo !empty($projekt['flagga']) ? 'checked' : ''; ?>>
                            <label class="form-check-label text-warning fw-bold" for="flagga">
                                <i class="fas fa-exclamation-circle"></i> SE ÖVER DETTA PROJEKT
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3 border-bottom pb-2">
                        <i class="fas fa-address-card text-danger"></i> Kontaktuppgifter
                    </h5>

                    <div class="mb-3">
                        <label class="form-label">Kundtyp *</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="kundTyp"
                                       id="privat" value="Privat"
                                       <?php echo $projekt['kundTyp'] == 'Privat' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="privat">
                                    <i class="fas fa-user"></i> Privat
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="kundTyp"
                                       id="foretag" value="Företag"
                                       <?php echo $projekt['kundTyp'] == 'Företag' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="foretag">
                                    <i class="fas fa-building"></i> Företag
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="kundTyp"
                                       id="forsakring" value="Försäkring"
                                       <?php echo $projekt['kundTyp'] == 'Försäkring' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="forsakring">
                                    <i class="fas fa-shield-alt"></i> Försäkring
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="kontakt_person_namn" class="form-label">Namn *</label>
                        <input type="text" class="form-control" id="kontakt_person_namn"
                               name="kontakt_person_namn" required
                               value="<?php echo htmlspecialchars($projekt['kontakt_person_namn']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="kontakt_person_telefon" class="form-label">Telefon *</label>
                        <input type="tel" class="form-control" id="kontakt_person_telefon"
                               name="kontakt_person_telefon" required
                               value="<?php echo htmlspecialchars($projekt['kontakt_person_telefon']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="kontakt_person_email" class="form-label">E-post</label>
                        <input type="email" class="form-control" id="kontakt_person_email"
                               name="kontakt_person_email"
                               value="<?php echo htmlspecialchars($projekt['kontakt_person_email'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- ================================ -->
            <!-- PLANERING / EKONOMI / DÄCK       -->
            <!-- ================================ -->
            <div class="row mt-3">
                <div class="col-md-4">
                    <h5 class="mb-3 border-bottom pb-2">
                        <i class="fas fa-calendar-alt text-danger"></i> Planering
                    </h5>

                    <div class="mb-3">
                        <label for="planDate" class="form-label">Planerat datum</label>
                        <input type="date" class="form-control" id="planDate" name="planDate"
                               value="<?php echo htmlspecialchars($projekt['planDate'] ?? ''); ?>">
                        <div id="planeradInfo" class="mt-2"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="starttid" class="form-label">Starttid</label>
                            <input type="time" class="form-control" id="starttid" name="starttid"
                                   value="<?php echo $projekt['starttid'] ? substr($projekt['starttid'], 0, 5) : '09:00'; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="sluttid" class="form-label">Sluttid</label>
                            <input type="time" class="form-control" id="sluttid" name="sluttid"
                                   value="<?php echo $projekt['sluttid'] ? substr($projekt['sluttid'], 0, 5) : '17:00'; ?>">
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="inkommen"  <?php echo $projekt['status'] == 'inkommen'  ? 'selected' : ''; ?>>Inkommen</option>
                            <option value="pågående"  <?php echo $projekt['status'] == 'pågående'  ? 'selected' : ''; ?>>Pågående</option>
                            <option value="avslutad"  <?php echo $projekt['status'] == 'avslutad'  ? 'selected' : ''; ?>>Avslutad</option>
                        </select>
                    </div>

                    <!-- Ansvarig tekniker:
                         Villkor 1: dölj om ansvarig är tomt (initial)
                         Villkor 2: visa om ansvarig är satt
                         Villkor 3: dölj om status = inkommen
                         Vid statusändring från inkommen → visa dropdown -->
                    <div class="mb-3" id="ansvarigTeknikerWrapper"
                         style="display: <?php echo $visaAnsvarig; ?>;">
                        <label for="ansvarig_tekniker" class="form-label">Ansvarig tekniker</label>
                        <select class="form-control" id="ansvarig_tekniker" name="ansvarig_tekniker">
                            <option value="">-- Välj tekniker --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo (int)$user['id']; ?>"
                                    <?php echo (int)$projekt['ansvarig_tekniker'] === (int)$user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['anvandarnamn']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <h5 class="mb-3 border-bottom pb-2">
                        <i class="fas fa-credit-card text-danger"></i> Ekonomi
                    </h5>

                    <div class="mb-3">
                        <label for="pris" class="form-label">Manuellt pris (kr)</label>
                        <input type="number" step="0.01" class="form-control" id="pris" name="pris"
                               value="<?php echo htmlspecialchars($projekt['pris'] ?? ''); ?>"
                               placeholder="Beräknas automatiskt från rader"
                               disabled>
                        <small class="text-muted">Lämna tomt om du använder projekt-rader nedan.</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="betald" name="betald"
                                   <?php echo !empty($projekt['betald']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="betald">
                                <i class="fas fa-check-circle"></i> Betald
                            </label>
                        </div>
                    </div>

                    <?php if (!empty($projekt['avslutad'])): ?>
                    <div class="mb-3">
                        <label class="form-label text-muted">Avslutad datum</label>
                        <p class="mb-0">
                            <?php echo date('Y-m-d H:i', strtotime($projekt['avslutadDatum'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <h5 class="mb-3 border-bottom pb-2">
                        <i class="fas fa-warehouse text-danger"></i> Däckförvaring
                    </h5>

                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="dackforvaring" name="dackforvaring"
                                   <?php echo !empty($projekt['dackforvaring']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="dackforvaring">
                                <i class="fas fa-check"></i> Däckförvaring
                            </label>
                        </div>

                        <div id="dackforvaringFields"
                             style="<?php echo !empty($projekt['dackforvaring']) ? 'display: block;' : 'display: none;'; ?>">
                            <label for="dackforvaring_id" class="form-label">Förvarings-ID</label>
                            <input type="text" class="form-control" id="dackforvaring_id"
                                   name="dackforvaring_id"
                                   value="<?php echo htmlspecialchars($projekt['dackforvaring_id'] ?? ''); ?>"
                                   placeholder="T.ex. HYLLA-42">
                            <small class="text-muted">Ange hylla/plats-nummer</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================ -->
            <!-- PROJEKT RADER                    -->
            <!-- ================================ -->
            <div class="mt-4">
                <h5 class="border-bottom pb-2">
                    <i class="fas fa-list-ul text-danger"></i> Projekt Rader
                    <small class="text-muted fw-normal ms-2">
                        Timpris arbete: <?php echo number_format($TIMPRIS, 0, ',', ' '); ?> kr/tim
                    </small>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="raderTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 130px;">Typ</th>
                                <th>Beskrivning</th>
                                <th style="width: 130px;">Pris (kr)</th>
                                <th style="width: 120px;">Antal</th>
                                <th style="width: 130px;">Rabatt (kr)</th>
                                <th style="width: 110px;">Total (kr)</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rader as $i => $rad): ?>
                            <tr data-index="<?php echo $i; ?>">
                                <td>
                                    <select name="rader[<?php echo $i; ?>][typ]" class="form-select form-select-sm typSelect">
                                        <option value="material" <?php echo $rad['typ'] !== 'arbete' ? 'selected' : ''; ?>>Material</option>
                                        <option value="arbete"  <?php echo $rad['typ'] === 'arbete' ? 'selected' : ''; ?>>Arbete</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="rader[<?php echo $i; ?>][beskrivning]"
                                           class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($rad['beskrivning']); ?>"
                                           placeholder="Beskrivning..." required>
                                </td>
                                <td>
                                    <input type="number" name="rader[<?php echo $i; ?>][pris]"
                                           class="form-control form-control-sm prisInput"
                                           value="<?php echo htmlspecialchars($rad['pris']); ?>"
                                           step="0.01" min="0"
                                           <?php echo $rad['typ'] === 'arbete' ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="number" name="rader[<?php echo $i; ?>][antal]"
                                           class="form-control form-control-sm antalInput"
                                           value="<?php echo htmlspecialchars($rad['antal']); ?>"
                                           step="0.01" min="0">
                                </td>
                                <td>
                                    <input type="number" name="rader[<?php echo $i; ?>][rabatt]"
                                           class="form-control form-control-sm rabattInput"
                                           value="<?php echo htmlspecialchars($rad['rabatt']); ?>"
                                           step="0.01" min="0"
                                           <?php echo $rad['typ'] !== 'arbete' ? 'disabled' : ''; ?>>
                                </td>
                                <td class="totalCell" data-value="0">0</td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm removeRadBtn" title="Ta bort rad">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end fw-bold">Totalt:</td>
                                <td id="grandTotal" class="fw-bold text-danger">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <button type="button" class="btn btn-outline-secondary btn-sm" id="addRadBtn">
                    <i class="fas fa-plus"></i> Lägg till rad
                </button>
            </div>

            <!-- ================================ -->
            <!-- SUBMIT                           -->
            <!-- ================================ -->
            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-save"></i> Spara ändringar
                </button>
                <a href="projekt_visa.php?id=<?php echo $projekt['id']; ?>" class="btn btn-secondary ms-2">
                    <i class="fas fa-times"></i> Avbryt
                </a>
            </div>

        </form>
    </div>
</div>

<!-- ======================================== -->
<!-- BILDGALLERI                              -->
<!-- ======================================== -->
<div class="row mt-4">
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
                        <div class="form-text">
                            Stödjer: JPG, PNG, GIF, WEBP. Max 10 MB.
                            Du kan ta foto direkt med kameran.
                        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const TIMPRIS = <?php echo (int)$TIMPRIS; ?>;

    // Auto-uppercase regnummer
    document.getElementById('regnummer').addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });

    // Däckförvaring toggle
    const dackCb = document.getElementById('dackforvaring');
    const dackFields = document.getElementById('dackforvaringFields');
    dackCb.addEventListener('change', function () {
        dackFields.style.display = this.checked ? 'block' : 'none';
        if (!this.checked) document.getElementById('dackforvaring_id').value = '';
    });

    // Ansvarig tekniker toggle
    // Visa när status ändras från inkommen → visa dropdown
    // Dölj när status sätts tillbaka till inkommen
    const statusEl = document.getElementById('status');
    const ansvarigWrapper = document.getElementById('ansvarigTeknikerWrapper');

    statusEl.addEventListener('change', function () {
        if (this.value !== 'inkommen') {
            ansvarigWrapper.style.display = 'block';
        } else {
            ansvarigWrapper.style.display = 'none';
        }
    });

    // Planerade jobb
    const planDate = document.getElementById('planDate');
    const planeradInfo = document.getElementById('planeradInfo');

    function hamtaPlaneradeJobb(datum) {
        if (!datum) { planeradInfo.innerHTML = ''; return; }
        planeradInfo.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Kollar lediga tider...</span>';
        fetch('../ajax/get_planerade_jobb.php?datum=' + encodeURIComponent(datum))
            .then(r => r.text())
            .then(html => { planeradInfo.innerHTML = html; })
            .catch(() => { planeradInfo.innerHTML = '<span class="text-danger">Kunde inte hämta information</span>'; });
    }

    if (planDate.value) hamtaPlaneradeJobb(planDate.value);
    planDate.addEventListener('change', function () { hamtaPlaneradeJobb(this.value); });

    // ------------------------------------------------
    // RADER - logik
    // ------------------------------------------------
    const tbody = document.querySelector('#raderTable tbody');
    const grandTotal = document.getElementById('grandTotal');

    function formatKr(val) {
        return val.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function updateGrandTotal() {
        let sum = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const cell = tr.querySelector('.totalCell');
            if (cell) sum += parseFloat(cell.dataset.value) || 0;
        });
        grandTotal.textContent = formatKr(sum);
    }

    function updateRad(tr) {
        const typ         = tr.querySelector('.typSelect').value;
        const prisInput   = tr.querySelector('.prisInput');
        const antalInput  = tr.querySelector('.antalInput');
        const rabattInput = tr.querySelector('.rabattInput');
        const totalCell   = tr.querySelector('.totalCell');

        if (typ === 'arbete') {
            prisInput.value      = TIMPRIS;
            prisInput.disabled   = true;
            rabattInput.disabled = false;
        } else {
            prisInput.disabled   = false;
            rabattInput.disabled = true;
            rabattInput.value    = '0';
        }

        const pris   = parseFloat(prisInput.value) || 0;
        const antal  = parseFloat(antalInput.value) || 0;
        const rabatt = parseFloat(rabattInput.value) || 0;
        const total  = (pris * antal) - rabatt;

        totalCell.textContent = formatKr(total);
        totalCell.dataset.value = total;
        updateGrandTotal();
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function byggRad(index) {
        const tr = document.createElement('tr');
        tr.dataset.index = index;
        tr.innerHTML = `
            <td>
                <select name="rader[${index}][typ]" class="form-select form-select-sm typSelect">
                    <option value="material">Material</option>
                    <option value="arbete">Arbete</option>
                </select>
            </td>
            <td>
                <input type="text" name="rader[${index}][beskrivning]"
                       class="form-control form-control-sm" placeholder="Beskrivning..." required>
            </td>
            <td>
                <input type="number" name="rader[${index}][pris]"
                       class="form-control form-control-sm prisInput" value="0" step="0.01" min="0">
            </td>
            <td>
                <input type="number" name="rader[${index}][antal]"
                       class="form-control form-control-sm antalInput" value="1" step="0.01" min="0">
            </td>
            <td>
                <input type="number" name="rader[${index}][rabatt]"
                       class="form-control form-control-sm rabattInput" value="0" step="0.01" min="0" disabled>
            </td>
            <td class="totalCell" data-value="0">0</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm removeRadBtn" title="Ta bort rad">
                    <i class="fas fa-trash"></i>
                </button>
            </td>`;
        return tr;
    }

    // Initiera befintliga rader
    tbody.querySelectorAll('tr').forEach(tr => updateRad(tr));

    document.getElementById('addRadBtn').addEventListener('click', function () {
        const index = tbody.querySelectorAll('tr').length;
        const tr = byggRad(index);
        tbody.appendChild(tr);
        updateRad(tr);
    });

    tbody.addEventListener('change', function (e) {
        const tr = e.target.closest('tr');
        if (tr) updateRad(tr);
    });

    tbody.addEventListener('input', function (e) {
        const tr = e.target.closest('tr');
        if (tr) updateRad(tr);
    });

    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('.removeRadBtn');
        if (btn) {
            btn.closest('tr').remove();
            updateGrandTotal();
        }
    });
});
</script>

<?php $extra_scripts = ['../assets/js/bildgalleri.js']; ?>
<?php include '../includes/footer.php'; ?>
