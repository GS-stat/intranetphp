<?php
require_once '../includes/config.php';
kravInloggning();

$users       = hamtaAllaAnvandare($pdo);
$tekniker_id = isset($_GET['tekniker_id']) && $_GET['tekniker_id'] !== ''
               ? (int)$_GET['tekniker_id']
               : null;
?>
<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h1><i class="fas fa-calendar-alt"></i> Kalender per tekniker</h1>
        <p class="text-muted">Se bokningar uppdelade per ansvarig tekniker</p>
    </div>
    <div class="col-md-6 text-end">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Tillbaka till dashboard
        </a>
    </div>
</div>

<!-- Tekniker-filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="row align-items-center g-2">
            <div class="col-auto">
                <label class="form-label mb-0 fw-bold">Visa kalender för:</label>
            </div>
            <div class="col-md-3">
                <select id="teknikerValj" class="form-select">
                    <option value="">Alla tekniker</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"
                            <?php echo $tekniker_id === (int)$u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['anvandarnamn']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <small class="text-muted">
                    Välj en tekniker för att filtrera bokningarna
                </small>
            </div>

            <!-- Snabb-statistik för vald tekniker -->
            <?php if ($tekniker_id):
                $stmt = $pdo->prepare("
                    SELECT
                        COUNT(*) as totalt,
                        SUM(CASE WHEN status='pågående' THEN 1 ELSE 0 END) as pagaende,
                        SUM(CASE WHEN status='inkommen' THEN 1 ELSE 0 END) as inkomna
                    FROM stat_projekt
                    WHERE ansvarig_tekniker = ?
                      AND status != 'avslutad'
                ");
                $stmt->execute([$tekniker_id]);
                $qs = $stmt->fetch();
            ?>
            <div class="col-md-auto ms-auto">
                <span class="badge bg-primary me-1">
                    <?php echo $qs['pagaende']; ?> pågående
                </span>
                <span class="badge bg-warning text-dark">
                    <?php echo $qs['inkomna']; ?> inkomna
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Kalender-container -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">
            <i class="fas fa-calendar-week"></i> Veckokalender
            <?php if ($tekniker_id):
                foreach ($users as $u) {
                    if ((int)$u['id'] === $tekniker_id) {
                        echo ' – ' . htmlspecialchars($u['anvandarnamn']);
                        break;
                    }
                }
            else: ?>
                – Alla tekniker
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body p-0">
        <div id="tekniker-kalender-container">
            <?php
            $idag = new DateTime();
            $idag->modify('monday this week');
            $veckaStart = $idag->format('Y-m-d');
            $bokningar  = hamtaVeckansBokningarPerTekniker($pdo, $veckaStart, $tekniker_id);
            include '../ajax/kalender_tekniker.php';
            ?>
        </div>
    </div>
</div>

<script>
document.getElementById('teknikerValj').addEventListener('change', function () {
    const id = this.value;
    let url = 'kalender_tekniker.php';
    if (id) url += '?tekniker_id=' + id;
    window.location.href = url;
});
</script>

<?php include '../includes/footer.php'; ?>
