<?php
require_once '../includes/config.php';
kravInloggning();

$statistik = getTeknikerStatistikDetaljerad($pdo);

// Hämta per-tekniker senaste projekt (topp 5)
function senasteProjektForTekniker($pdo, int $tekniker_id, int $limit = 5): array {
    $stmt = $pdo->prepare("
        SELECT id, regnummer, rubrik, status, pris, createdDate
        FROM stat_projekt
        WHERE ansvarig_tekniker = ?
        ORDER BY createdDate DESC
        LIMIT ?
    ");
    $stmt->execute([$tekniker_id, $limit]);
    return $stmt->fetchAll();
}
?>
<?php include '../includes/header.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-user-chart"></i> Statistik per tekniker</h1>
        <p class="text-muted">Avslutade uppdrag, intäkter och genomsnittstid</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Tillbaka
        </a>
    </div>
</div>

<!-- Sammanfattningskort per tekniker -->
<div class="row g-3 mb-4">
    <?php foreach ($statistik as $t):
        $intakt       = (float)($t['intakt'] ?? 0);
        $snittTimmar  = $t['snitt_timmar'] !== null ? round($t['snitt_timmar'], 1) : null;
    ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-tie me-1"></i> <?php echo htmlspecialchars($t['anvandarnamn']); ?></span>
                <span class="badge bg-<?php echo $t['pagaende'] > 0 ? 'primary' : 'secondary'; ?>">
                    <?php echo $t['pagaende']; ?> pågående
                </span>
            </div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <div class="col-4">
                        <div class="p-2 rounded bg-light">
                            <div class="fw-bold fs-4 text-success"><?php echo $t['avslutade']; ?></div>
                            <small class="text-muted">Avslutade</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded bg-light">
                            <div class="fw-bold fs-4 text-primary"><?php echo $t['totalt']; ?></div>
                            <small class="text-muted">Totalt</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded bg-light">
                            <div class="fw-bold fs-4 text-warning"><?php echo $t['inkomna']; ?></div>
                            <small class="text-muted">Inkomna</small>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted"><i class="fas fa-money-bill-wave me-1"></i> Intäkt (betalda)</span>
                    <strong class="text-danger">
                        <?php echo $intakt > 0 ? number_format($intakt, 0, ',', ' ') . ' kr' : '–'; ?>
                    </strong>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted"><i class="fas fa-stopwatch me-1"></i> Snitt handläggning</span>
                    <strong>
                        <?php if ($snittTimmar !== null):
                            $dagar  = floor($snittTimmar / 24);
                            $timmar = $snittTimmar % 24;
                            echo $dagar > 0 ? $dagar . ' d ' . $timmar . ' h' : $timmar . ' h';
                        else: ?>–<?php endif; ?>
                    </strong>
                </div>

                <?php if ($t['avslutade'] > 0): ?>
                <div class="mt-3">
                    <small class="text-muted">Slutförandegrad</small>
                    <?php
                    $grad = $t['totalt'] > 0 ? round($t['avslutade'] / $t['totalt'] * 100) : 0;
                    $farg = $grad >= 80 ? 'success' : ($grad >= 50 ? 'warning' : 'danger');
                    ?>
                    <div class="progress mt-1" style="height: 8px;">
                        <div class="progress-bar bg-<?php echo $farg; ?>" style="width: <?php echo $grad; ?>%"
                             title="<?php echo $grad; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $grad; ?>%</small>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-end">
                <a href="kalender_tekniker.php?tekniker_id=<?php echo $t['id']; ?>"
                   class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-calendar-alt"></i> Kalender
                </a>
                <a href="projekt_lista.php?sok=<?php echo urlencode($t['anvandarnamn']); ?>"
                   class="btn btn-sm btn-outline-secondary ms-1">
                    <i class="fas fa-list"></i> Projekt
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Jämförelsediagram -->
<?php if (count($statistik) > 0): ?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Avslutade uppdrag per tekniker</h5>
            </div>
            <div class="card-body">
                <canvas id="avslutadeChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Intäkt per tekniker (betalda)</h5>
            </div>
            <div class="card-body">
                <canvas id="intaktChart"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = [
        <?php foreach ($statistik as $t): ?>
            <?php echo json_encode($t['anvandarnamn']); ?>,
        <?php endforeach; ?>
    ];

    const avslutadeData = [
        <?php foreach ($statistik as $t): echo (int)$t['avslutade'] . ','; endforeach; ?>
    ];

    const intaktData = [
        <?php foreach ($statistik as $t): echo (float)($t['intakt'] ?? 0) . ','; endforeach; ?>
    ];

    const colors = ['#dc3545','#0d6efd','#198754','#ffc107','#0dcaf0','#6f42c1'];

    // Avslutade
    new Chart(document.getElementById('avslutadeChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Avslutade uppdrag',
                data: avslutadeData,
                backgroundColor: colors,
                borderRadius: 4,
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
            }
        }
    });

    // Intäkt
    new Chart(document.getElementById('intaktChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Intäkt (kr)',
                data: intaktData,
                backgroundColor: colors,
                borderRadius: 4,
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.raw.toLocaleString('sv-SE') + ' kr'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => v.toLocaleString('sv-SE') + ' kr' }
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
