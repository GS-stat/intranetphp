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

$users    = hamtaAllaAnvandare($pdo);
$rader    = hamtaProjektRader($pdo, $id);
$artiklar = hamtaAllaArtiklar($pdo, true); // Endast aktiva

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_project'])) {

    $nyaRader = [];
    if (!empty($_POST['rader']) && is_array($_POST['rader'])) {
        foreach ($_POST['rader'] as $rad) {
            $beskrivning = trim($rad['beskrivning'] ?? '');
            if ($beskrivning === '') continue;

            $artikel_id = !empty($rad['artikel_id']) ? (int)$rad['artikel_id'] : null;
            $antal      = max(0, (float)($rad['antal'] ?? 1));
            $pris       = max(0, (float)($rad['pris']  ?? 0));
            $rabatt     = max(0, (float)($rad['rabatt'] ?? 0));

            $nyaRader[] = [
                'artikel_id'  => $artikel_id,
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

            require_once '../includes/sms.php';

            try {
                // Boknings-SMS: skickas om planDate är nytt/ändrat
                if (!empty($data['planDate'])) {
                    skickaSmsBokning($pdo, $id, $data['planDate'], $data['starttid'] ?? null);
                }
                // Kvittens-SMS: skickas om avslutad + betald
                skickaSmsKvittens($pdo, $id);
            } catch (Throwable $e) {
                error_log('[SMS] Fel i projekt_redigera: ' . $e->getMessage());
            }

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

$visaAnsvarig = ($projekt['status'] !== 'inkommen' && !empty($projekt['ansvarig_tekniker'])) ? 'block' : 'none';

// Serialisera artiklar för JS
$artiklarJson = json_encode(array_map(fn($a) => [
    'id'            => (int)$a['id'],
    'namn'          => $a['namn'],
    'pris'          => (float)$a['pris'],
    'pris_disabled' => (bool)$a['pris_disabled'],
    'tillat_rabatt' => (bool)$a['tillat_rabatt'],
], $artiklar));

// Serialisera befintliga rader för JS (inklusive artikel_id)
$raderJson = json_encode(array_map(fn($r) => [
    'artikel_id'  => $r['artikel_id'] ? (int)$r['artikel_id'] : null,
    'beskrivning' => $r['beskrivning'],
    'pris'        => (float)$r['pris'],
    'antal'       => (float)$r['antal'],
    'rabatt'      => (float)$r['rabatt'],
], $rader));
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

                    <!-- Slutpris visas dynamiskt -->
                    <div id="slutprisWrapper" class="mt-3">
                        <div class="alert alert-dark mb-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Totalt att betala:</span>
                                <span class="fw-bold text-danger fs-5" id="slutprisDisplay">0 kr</span>
                            </div>
                        </div>
                    </div>
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
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================================ -->
            <!-- PROJEKT RADER                    -->
            <!-- ================================ -->
            <div class="mt-4">
                <h5 class="border-bottom pb-2">
                    <i class="fas fa-list-ul text-danger"></i> Artiklar / Priser
                    <?php if (!empty($artiklar)): ?>
                    <small class="text-muted fw-normal ms-2">
                        Välj från <?php echo count($artiklar); ?> aktiva artiklar
                    </small>
                    <?php endif; ?>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="raderTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 220px;">Artikel</th>
                                <th>Beskrivning</th>
                                <th style="width: 130px;">Pris (kr)</th>
                                <th style="width: 110px;">Antal</th>
                                <th style="width: 130px;">Rabatt (kr)</th>
                                <th style="width: 120px;">Summa (kr)</th>
                                <th style="width: 46px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Fylls via JS från $raderJson -->
                        </tbody>
                        <tfoot>
                            <tr class="table-dark">
                                <td colspan="5" class="text-end fw-bold">Totalt:</td>
                                <td id="grandTotal" class="fw-bold text-danger fs-6">0,00 kr</td>
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

<!-- BILDGALLERI -->
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
                        <div class="form-text">Stödjer: JPG, PNG, GIF, WEBP. Max 10 MB.</div>
                    </div>
                    <div id="uploadProgress" class="progress d-none">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
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
    const ARTIKLAR    = <?php echo $artiklarJson; ?>;
    const BEFINTLIGA  = <?php echo $raderJson; ?>;

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
    const statusEl = document.getElementById('status');
    const ansvarigWrapper = document.getElementById('ansvarigTeknikerWrapper');
    statusEl.addEventListener('change', function () {
        ansvarigWrapper.style.display = this.value !== 'inkommen' ? 'block' : 'none';
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
    const grandTotalEl   = document.getElementById('grandTotal');
    const slutprisDisplay = document.getElementById('slutprisDisplay');

    function formatKr(val) {
        return val.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' kr';
    }

    function updateGrandTotal() {
        let sum = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const cell = tr.querySelector('.totalCell');
            if (cell) sum += parseFloat(cell.dataset.value) || 0;
        });
        grandTotalEl.textContent = formatKr(sum);
        if (slutprisDisplay) slutprisDisplay.textContent = formatKr(sum);
    }

    function getArtikelById(id) {
        return ARTIKLAR.find(a => a.id === parseInt(id)) || null;
    }

    // Applicera artikelns inställningar (kallas bara när artikel ÄNDRAS)
    function applyArtikel(tr, artikel, forcePris) {
        const artikelIdInput = tr.querySelector('.artikelIdInput');
        const prisInput      = tr.querySelector('.prisInput');
        const rabattInput    = tr.querySelector('.rabattInput');

        if (artikelIdInput) artikelIdInput.value = artikel ? artikel.id : '';

        if (artikel) {
            if (artikel.pris_disabled) {
                prisInput.value    = artikel.pris;
                prisInput.readOnly = true;
                prisInput.classList.add('pris-locked');
            } else {
                // Pre-fyll bara om forcePris=true (initial load) eller fältet är tomt/noll
                if (forcePris || !prisInput.value || parseFloat(prisInput.value) === 0) {
                    prisInput.value = artikel.pris;
                }
                prisInput.readOnly = false;
                prisInput.classList.remove('pris-locked');
            }
            rabattInput.disabled = !artikel.tillat_rabatt;
            if (rabattInput.disabled) rabattInput.value = '0';
        } else {
            prisInput.readOnly = false;
            prisInput.classList.remove('pris-locked');
            rabattInput.disabled = false;
        }
        beraknaTotal(tr);
    }

    // Beräkna rad-total (kallas vid varje input-ändring)
    function beraknaTotal(tr) {
        const prisInput   = tr.querySelector('.prisInput');
        const antalInput  = tr.querySelector('.antalInput');
        const rabattInput = tr.querySelector('.rabattInput');
        const totalCell   = tr.querySelector('.totalCell');

        const pris   = parseFloat(prisInput.value)   || 0;
        const antal  = parseFloat(antalInput.value)  || 0;
        const rabatt = parseFloat(rabattInput.value) || 0;
        const total  = (pris * antal) - rabatt;

        totalCell.textContent   = formatKr(total);
        totalCell.dataset.value = total;
        updateGrandTotal();
    }

    function updateRad(tr) { beraknaTotal(tr); }

    function byggArtikelOptions(valdArtikelId) {
        let opts = '<option value="">-- Välj artikel / anpassad rad --</option>';
        ARTIKLAR.forEach(a => {
            const pris = parseFloat(a.pris).toFixed(2).replace('.', ',');
            opts += `<option value="${a.id}" ${parseInt(valdArtikelId) === a.id ? 'selected' : ''}>`
                  + `${escHtml(a.namn)} (${pris} kr)</option>`;
        });
        return opts;
    }

    function byggRad(index, data) {
        data = data || {};
        const tr = document.createElement('tr');
        tr.dataset.index = index;

        const artikel_id  = data.artikel_id  || '';
        const beskrivning = data.beskrivning || '';
        const pris        = data.pris        !== undefined ? data.pris  : '';
        const antal       = data.antal       !== undefined ? data.antal : 1;
        const rabatt      = data.rabatt      !== undefined ? data.rabatt : 0;

        tr.innerHTML = `
            <td>
                <select class="form-select form-select-sm artikelSelect">
                    ${byggArtikelOptions(artikel_id)}
                </select>
                <input type="hidden" name="rader[${index}][artikel_id]" class="artikelIdInput" value="${artikel_id}">
            </td>
            <td>
                <input type="text" name="rader[${index}][beskrivning]" class="form-control form-control-sm beskrivningInput"
                       value="${escHtml(beskrivning)}" placeholder="Beskrivning...">
            </td>
            <td>
                <input type="number" name="rader[${index}][pris]" class="form-control form-control-sm prisInput"
                       value="${pris}" step="0.01" min="0" placeholder="0">
            </td>
            <td>
                <input type="number" name="rader[${index}][antal]" class="form-control form-control-sm antalInput"
                       value="${antal}" step="0.01" min="0">
            </td>
            <td>
                <input type="number" name="rader[${index}][rabatt]" class="form-control form-control-sm rabattInput"
                       value="${rabatt}" step="0.01" min="0">
            </td>
            <td class="totalCell fw-semibold text-danger" data-value="0">0,00 kr</td>
            <td>
                <button type="button" class="btn btn-outline-danger btn-sm removeRadBtn" title="Ta bort rad">
                    <i class="fas fa-trash"></i>
                </button>
            </td>`;
        return tr;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Ladda befintliga rader
    BEFINTLIGA.forEach((rad, i) => {
        const tr = byggRad(i, rad);
        tbody.appendChild(tr);
        const artikelSelect = tr.querySelector('.artikelSelect');
        const artikel = rad.artikel_id ? getArtikelById(rad.artikel_id) : null;
        if (artikel && artikelSelect) artikelSelect.value = rad.artikel_id;
        if (artikel) {
            applyArtikel(tr, artikel, false); // false = behåll sparat pris
            // Återställ pris till sparat värde efter applyArtikel
            const prisInput = tr.querySelector('.prisInput');
            if (prisInput && !prisInput.readOnly) {
                prisInput.value = rad.pris;
            }
        }
        beraknaTotal(tr);
    });

    document.getElementById('addRadBtn').addEventListener('click', function () {
        const index = tbody.querySelectorAll('tr').length;
        const tr = byggRad(index, {});
        tbody.appendChild(tr);
        beraknaTotal(tr);
    });

    tbody.addEventListener('change', function (e) {
        const tr = e.target.closest('tr');
        if (!tr) return;
        if (e.target.classList.contains('artikelSelect')) {
            const artikel = e.target.value ? getArtikelById(e.target.value) : null;
            const beskEl  = tr.querySelector('.beskrivningInput');
            if (artikel && beskEl && beskEl.value.trim() === '') {
                beskEl.value = artikel.namn;
            }
            applyArtikel(tr, artikel, true); // true = pre-fyll pris från artikel
        } else {
            beraknaTotal(tr);
        }
    });

    tbody.addEventListener('input', function (e) {
        const tr = e.target.closest('tr');
        if (tr) beraknaTotal(tr);
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

<style>
.prisInput[readonly], .pris-locked {
    background-color: #e9ecef !important;
    cursor: not-allowed;
    color: #6c757d;
    opacity: 1;
}
</style>

<?php $extra_scripts = ['../assets/js/bildgalleri.js']; ?>
<?php include '../includes/footer.php'; ?>
