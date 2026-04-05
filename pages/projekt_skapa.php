<?php
require_once '../includes/config.php';
kravInloggning();

$users   = hamtaAllaAnvandare($pdo);
$TIMPRIS = 850;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Bygg ihop rader och beräkna total
    $rader = [];
    if (!empty($_POST['rader']) && is_array($_POST['rader'])) {
        foreach ($_POST['rader'] as $rad) {
            $beskrivning = trim($rad['beskrivning'] ?? '');
            if ($beskrivning === '') continue;

            $typ    = $rad['typ'] === 'arbete' ? 'arbete' : 'material';
            $antal  = max(0, (float)($rad['antal'] ?? 1));
            $pris   = ($typ === 'arbete') ? $TIMPRIS : max(0, (float)($rad['pris'] ?? 0));
            $rabatt = ($typ === 'arbete') ? max(0, (float)($rad['rabatt'] ?? 0)) : 0;

            $rader[] = [
                'typ'         => $typ,
                'beskrivning' => $beskrivning,
                'pris'        => $pris,
                'antal'       => $antal,
                'rabatt'      => $rabatt,
            ];
        }
    }

    // Använd summa från rader om de finns, annars manuellt pris
    $prisFranRader = !empty($rader) ? beraknaProjektSumma($rader) : null;
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
        'skapad_av'             => $_SESSION['anvandare_id'],
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

    $projekt_id = skapaProjekt($pdo, $data);

    if ($projekt_id) {
        if (!empty($rader)) {
            sparaProjektRader($pdo, $projekt_id, $rader);
        }

        // Skicka orderbekräftelse via e-post om kunden har e-postadress
        if (!empty($data['kontakt_person_email'])) {
            require_once '../includes/mailer.php';
            $projektData = array_merge($data, ['id' => $projekt_id]);
            skickaOrderBekraftelse($data['kontakt_person_email'], $projektData);
        }

        header('Location: projekt_lista.php?meddelande=Projekt+skapat');
        exit;
    } else {
        $error = "Kunde inte skapa projektet. Kontrollera att alla obligatoriska fält är ifyllda.";
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><i class="fas fa-plus-circle"></i> Skapa nytt projekt</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="" id="projektForm">

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
                                <input type="text" class="form-control" id="regnummer" name="regnummer"
                                       required placeholder="ABC123" style="text-transform: uppercase">
                            </div>

                            <div class="mb-3">
                                <label for="rubrik" class="form-label">Rubrik *</label>
                                <input type="text" class="form-control" id="rubrik" name="rubrik" required>
                            </div>

                            <div class="mb-3">
                                <label for="beskrivning" class="form-label">Beskrivning av arbete</label>
                                <textarea class="form-control" id="beskrivning" name="beskrivning" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="felsokning" class="form-label">Felsökning</label>
                                <textarea class="form-control" id="felsokning" name="felsokning" rows="2"
                                          placeholder="Beskriv felet / symptomen..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="atgard" class="form-label">Åtgärd</label>
                                <textarea class="form-control" id="atgard" name="atgard" rows="2"
                                          placeholder="Genomförd åtgärd..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="cmt" class="form-label">Interna kommentarer</label>
                                <textarea class="form-control" id="cmt" name="cmt" rows="2"
                                          placeholder="Syns bara internt..."></textarea>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="flagga" name="flagga">
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
                                               id="privat" value="Privat" checked>
                                        <label class="form-check-label" for="privat">
                                            <i class="fas fa-user"></i> Privat
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="kundTyp"
                                               id="foretag" value="Företag">
                                        <label class="form-check-label" for="foretag">
                                            <i class="fas fa-building"></i> Företag
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="kundTyp"
                                               id="forsakring" value="Försäkring">
                                        <label class="form-check-label" for="forsakring">
                                            <i class="fas fa-shield-alt"></i> Försäkring
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="kontakt_person_namn" class="form-label">Namn *</label>
                                <input type="text" class="form-control" id="kontakt_person_namn"
                                       name="kontakt_person_namn" required>
                            </div>

                            <div class="mb-3">
                                <label for="kontakt_person_telefon" class="form-label">Telefon *</label>
                                <input type="tel" class="form-control" id="kontakt_person_telefon"
                                       name="kontakt_person_telefon" required placeholder="0730730009">
                            </div>

                            <div class="mb-3">
                                <label for="kontakt_person_email" class="form-label">E-post</label>
                                <input type="email" class="form-control" id="kontakt_person_email"
                                       name="kontakt_person_email">
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
                                <input type="date" class="form-control" id="planDate" name="planDate">
                                <div id="planeradInfo" class="mt-2"></div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <label for="starttid" class="form-label">Starttid</label>
                                    <input type="time" class="form-control" id="starttid"
                                           name="starttid" value="09:00">
                                </div>
                                <div class="col-md-6">
                                    <label for="sluttid" class="form-label">Sluttid</label>
                                    <input type="time" class="form-control" id="sluttid"
                                           name="sluttid" value="17:00">
                                </div>
                            </div>

                            <div class="mb-3 mt-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="inkommen" selected>Inkommen</option>
                                    <option value="pågående">Pågående</option>
                                    <option value="avslutad">Avslutad</option>
                                </select>
                            </div>

                            <!-- Döljs om status = inkommen -->
                            <div class="mb-3" id="ansvarigWrapper" style="display: none;">
                                <label for="ansvarig_tekniker" class="form-label">Ansvarig tekniker</label>
                                <select class="form-control" id="ansvarig_tekniker" name="ansvarig_tekniker">
                                    <option value="">-- Välj tekniker --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo (int)$user['id']; ?>">
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
                                <input type="number" step="0.01" class="form-control" id="pris"
                                       name="pris" placeholder="Beräknas automatiskt från rader"
                                       disabled>
                                <small class="text-muted">
                                    Lämna tomt om du använder projekt-rader nedan.
                                </small>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="betald" name="betald">
                                    <label class="form-check-label" for="betald">Betald</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <h5 class="mb-3 border-bottom pb-2">
                                <i class="fas fa-warehouse text-danger"></i> Däckförvaring
                            </h5>

                            <div class="mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox"
                                           id="dackforvaring" name="dackforvaring">
                                    <label class="form-check-label" for="dackforvaring">
                                        Däckförvaring
                                    </label>
                                </div>

                                <div id="dackforvaringFields" style="display: none;">
                                    <label for="dackforvaring_id" class="form-label">Förvarings-ID</label>
                                    <input type="text" class="form-control" id="dackforvaring_id"
                                           name="dackforvaring_id" placeholder="T.ex. HYLLA-42">
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
                                    <!-- Rader genereras via JS -->
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
                            <i class="fas fa-save"></i> Skapa projekt
                        </button>
                        <a href="projekt_lista.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times"></i> Avbryt
                        </a>
                    </div>

                </form>
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

    // Ansvarig tekniker – visa när status != inkommen
    const statusEl = document.getElementById('status');
    const ansvarigWrapper = document.getElementById('ansvarigWrapper');

    function toggleAnsvarig() {
        ansvarigWrapper.style.display = statusEl.value !== 'inkommen' ? 'block' : 'none';
    }
    statusEl.addEventListener('change', toggleAnsvarig);
    toggleAnsvarig();

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
    planDate.addEventListener('change', function () { hamtaPlaneradeJobb(this.value); });

    // ------------------------------------------------
    // RADER - logik
    // ------------------------------------------------
    const tbody = document.querySelector('#raderTable tbody');
    const grandTotal = document.getElementById('grandTotal');

    function updateGrandTotal() {
        let sum = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const cell = tr.querySelector('.totalCell');
            if (cell) sum += parseFloat(cell.dataset.value) || 0;
        });
        grandTotal.textContent = formatKr(sum);
    }

    function formatKr(val) {
        return val.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function updateRad(tr) {
        const typ        = tr.querySelector('.typSelect').value;
        const prisInput  = tr.querySelector('.prisInput');
        const antalInput = tr.querySelector('.antalInput');
        const rabattInput= tr.querySelector('.rabattInput');
        const totalCell  = tr.querySelector('.totalCell');

        if (typ === 'arbete') {
            prisInput.value    = TIMPRIS;
            prisInput.disabled = true;
            rabattInput.disabled = false;
        } else {
            prisInput.disabled = false;
            rabattInput.disabled = true;
            rabattInput.value = '0';
        }

        const pris   = parseFloat(prisInput.value) || 0;
        const antal  = parseFloat(antalInput.value) || 0;
        const rabatt = parseFloat(rabattInput.value) || 0;
        const total  = (pris * antal) - rabatt;

        totalCell.textContent = formatKr(total);
        totalCell.dataset.value = total;
        updateGrandTotal();
    }

    function byggRad(index, data) {
        const tr = document.createElement('tr');
        tr.dataset.index = index;

        const typ        = data.typ || 'material';
        const beskrivning= data.beskrivning || '';
        const pris       = data.pris || 0;
        const antal      = data.antal || 1;
        const rabatt     = data.rabatt || 0;
        const arArbete   = typ === 'arbete';

        tr.innerHTML = `
            <td>
                <select name="rader[${index}][typ]" class="form-select form-select-sm typSelect">
                    <option value="material" ${!arArbete ? 'selected' : ''}>Material</option>
                    <option value="arbete"  ${arArbete  ? 'selected' : ''}>Arbete</option>
                </select>
            </td>
            <td>
                <input type="text" name="rader[${index}][beskrivning]" class="form-control form-control-sm"
                       value="${escHtml(beskrivning)}" placeholder="Beskrivning..." required>
            </td>
            <td>
                <input type="number" name="rader[${index}][pris]" class="form-control form-control-sm prisInput"
                       value="${arArbete ? TIMPRIS : pris}" step="0.01" min="0"
                       ${arArbete ? 'disabled' : ''}>
            </td>
            <td>
                <input type="number" name="rader[${index}][antal]" class="form-control form-control-sm antalInput"
                       value="${antal}" step="0.01" min="0">
            </td>
            <td>
                <input type="number" name="rader[${index}][rabatt]" class="form-control form-control-sm rabattInput"
                       value="${rabatt}" step="0.01" min="0"
                       ${!arArbete ? 'disabled' : ''}>
            </td>
            <td class="totalCell" data-value="0">0</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm removeRadBtn" title="Ta bort rad">
                    <i class="fas fa-trash"></i>
                </button>
            </td>`;
        return tr;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    document.getElementById('addRadBtn').addEventListener('click', function () {
        const index = tbody.querySelectorAll('tr').length;
        const tr = byggRad(index, {});
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

<?php include '../includes/footer.php'; ?>
