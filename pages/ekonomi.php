<?php
require_once '../includes/config.php';
kravInloggning();

$ar = isset($_GET['ar']) ? (int)$_GET['ar'] : (int)date('Y');

$manadsData  = getEkonomiManadsoversikt($pdo, $ar);
$arsSumma    = getEkonomiArssummering($pdo, $ar);
$projektData = getProjektMedVinst($pdo, $ar);

$manadsNamn = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];
?>
<?php include '../includes/header.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-chart-line"></i> Ekonomiöversikt</h1>
        <p class="text-muted">Intäkter, kostnader och nettoresultat – intern vy</p>
    </div>
    <div class="col-md-4 text-end d-flex gap-2 justify-content-end align-items-start">
        <select class="form-select form-select-sm w-auto" onchange="window.location='ekonomi.php?ar='+this.value">
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
            <option value="<?php echo $y; ?>" <?php echo $y === $ar ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <a href="utgifter_lista.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-receipt"></i> Utgifter
        </a>
        <a href="../ajax/export_ekonomi.php?typ=ekonomi&ar=<?php echo $ar; ?>" class="btn btn-sm btn-outline-dark">
            <i class="fas fa-file-csv"></i> CSV
        </a>
    </div>
</div>

<!-- Årssummering – 4 kort -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 h-100" style="background:#e8f5e9;">
            <div class="card-body text-center py-3">
                <i class="fas fa-arrow-trend-up fa-lg text-success mb-1"></i>
                <div class="text-muted small">Intäkter <?php echo $ar; ?></div>
                <div class="fs-4 fw-bold text-success"><?php echo number_format($arsSumma['intakter'], 0, ',', ' '); ?> kr</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 h-100" style="background:#fff3e0;">
            <div class="card-body text-center py-3">
                <i class="fas fa-tools fa-lg text-warning mb-1"></i>
                <div class="text-muted small">Projektkostnader</div>
                <div class="fs-4 fw-bold text-warning"><?php echo number_format($arsSumma['proj_kostnader'], 0, ',', ' '); ?> kr</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 h-100" style="background:#fce4ec;">
            <div class="card-body text-center py-3">
                <i class="fas fa-receipt fa-lg text-danger mb-1"></i>
                <div class="text-muted small">Allmänna utgifter</div>
                <div class="fs-4 fw-bold text-danger"><?php echo number_format($arsSumma['allm_kostnader'], 0, ',', ' '); ?> kr</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <?php $nettoFarg = $arsSumma['netto'] >= 0 ? 'success' : 'danger'; ?>
        <div class="card border-0 h-100" style="background:<?php echo $arsSumma['netto'] >= 0 ? '#e3f2fd' : '#ffebee'; ?>;">
            <div class="card-body text-center py-3">
                <i class="fas fa-scale-balanced fa-lg text-<?php echo $nettoFarg; ?> mb-1"></i>
                <div class="text-muted small">Nettoresultat</div>
                <div class="fs-4 fw-bold text-<?php echo $nettoFarg; ?>"><?php echo number_format($arsSumma['netto'], 0, ',', ' '); ?> kr</div>
            </div>
        </div>
    </div>
</div>

<!-- Månadsdiagram -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Månadsöversikt <?php echo $ar; ?></h5>
    </div>
    <div class="card-body">
        <canvas id="manadsEkonomiChart" style="height:300px;"></canvas>
    </div>
</div>

<!-- Månadsdetaljer tabell -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-table"></i> Månadsdetaljer</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Månad</th>
                        <th class="text-end text-success">Intäkter</th>
                        <th class="text-end text-warning">Projektkostnad</th>
                        <th class="text-end text-danger">Allm. utgifter</th>
                        <th class="text-end">Totala kostn.</th>
                        <th class="text-end">Netto</th>
                        <th class="text-center">Marginal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($manadsData as $m):
                        $marginal = $m['intakter'] > 0 ? round($m['netto'] / $m['intakter'] * 100) : null;
                        $nettoFarg = $m['netto'] >= 0 ? 'text-success' : 'text-danger';
                    ?>
                    <tr>
                        <td class="fw-semibold"><?php echo $manadsNamn[$m['manad']]; ?></td>
                        <td class="text-end text-success"><?php echo $m['intakter'] > 0 ? number_format($m['intakter'], 0, ',', ' ') . ' kr' : '–'; ?></td>
                        <td class="text-end text-warning"><?php echo $m['proj_kostnader'] > 0 ? number_format($m['proj_kostnader'], 0, ',', ' ') . ' kr' : '–'; ?></td>
                        <td class="text-end text-danger"><?php echo $m['allm_kostnader'] > 0 ? number_format($m['allm_kostnader'], 0, ',', ' ') . ' kr' : '–'; ?></td>
                        <td class="text-end"><?php echo $m['totala_kostnader'] > 0 ? number_format($m['totala_kostnader'], 0, ',', ' ') . ' kr' : '–'; ?></td>
                        <td class="text-end fw-bold <?php echo $nettoFarg; ?>">
                            <?php echo ($m['intakter'] > 0 || $m['totala_kostnader'] > 0) ? number_format($m['netto'], 0, ',', ' ') . ' kr' : '–'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($marginal !== null):
                                $mFarg = $marginal >= 20 ? 'success' : ($marginal >= 0 ? 'warning' : 'danger');
                            ?>
                            <span class="badge bg-<?php echo $mFarg; ?>"><?php echo $marginal; ?>%</span>
                            <?php else: ?>–<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark fw-bold">
                    <tr>
                        <td>Totalt</td>
                        <td class="text-end text-success"><?php echo number_format($arsSumma['intakter'], 0, ',', ' '); ?> kr</td>
                        <td class="text-end text-warning"><?php echo number_format($arsSumma['proj_kostnader'], 0, ',', ' '); ?> kr</td>
                        <td class="text-end text-danger"><?php echo number_format($arsSumma['allm_kostnader'], 0, ',', ' '); ?> kr</td>
                        <td class="text-end"><?php echo number_format($arsSumma['totala_kostnader'], 0, ',', ' '); ?> kr</td>
                        <td class="text-end <?php echo $arsSumma['netto'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($arsSumma['netto'], 0, ',', ' '); ?> kr
                        </td>
                        <td class="text-center">
                            <?php
                            $totMarginal = $arsSumma['intakter'] > 0 ? round($arsSumma['netto'] / $arsSumma['intakter'] * 100) : null;
                            if ($totMarginal !== null):
                                $mFarg = $totMarginal >= 20 ? 'success' : ($totMarginal >= 0 ? 'warning' : 'danger');
                            ?>
                            <span class="badge bg-<?php echo $mFarg; ?>"><?php echo $totMarginal; ?>%</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Projekt med vinst/förlust -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Projekt <?php echo $ar; ?> – vinst per uppdrag</h5>
        <a href="../ajax/export_ekonomi.php?typ=projekt&ar=<?php echo $ar; ?>" class="btn btn-sm btn-light">
            <i class="fas fa-file-csv"></i> CSV
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($projektData)): ?>
        <div class="text-center text-muted py-4">Inga projekt för <?php echo $ar; ?></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-sm">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Regnr</th>
                        <th>Uppdrag</th>
                        <th>Kund</th>
                        <th>Status</th>
                        <th class="text-end">Intäkt</th>
                        <th class="text-end">Kostnader</th>
                        <th class="text-end">Vinst</th>
                        <th class="text-center">Marginal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projektData as $p):
                        $vinstFarg = (float)$p['vinst'] >= 0 ? 'text-success' : 'text-danger';
                        $marginal  = (float)$p['intakt'] > 0 ? round((float)$p['vinst'] / (float)$p['intakt'] * 100) : null;
                    ?>
                    <tr onclick="window.location.href='projekt_visa.php?id=<?php echo $p['id']; ?>'" style="cursor:pointer">
                        <td class="text-muted small">#<?php echo $p['id']; ?></td>
                        <td><span class="font-monospace small fw-bold"><?php echo htmlspecialchars($p['regnummer']); ?></span></td>
                        <td><?php echo htmlspecialchars($p['rubrik']); ?></td>
                        <td class="small"><?php echo htmlspecialchars($p['kontakt_person_namn']); ?></td>
                        <td><?php echo getStatusBadge($p['status']); ?></td>
                        <td class="text-end"><?php echo (float)$p['intakt'] > 0 ? number_format($p['intakt'], 0, ',', ' ') . ' kr' : '–'; ?></td>
                        <td class="text-end text-danger small"><?php echo (float)$p['kostnader'] > 0 ? number_format($p['kostnader'], 0, ',', ' ') . ' kr' : '–'; ?></td>
                        <td class="text-end fw-semibold <?php echo $vinstFarg; ?>">
                            <?php echo number_format((float)$p['vinst'], 0, ',', ' '); ?> kr
                        </td>
                        <td class="text-center">
                            <?php if ($marginal !== null):
                                $mFarg = $marginal >= 20 ? 'success' : ($marginal >= 0 ? 'warning' : 'danger');
                            ?>
                            <span class="badge bg-<?php echo $mFarg; ?>"><?php echo $marginal; ?>%</span>
                            <?php else: ?>–<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const manader = [<?php echo implode(',', array_map(fn($m) => json_encode($manadsNamn[$m['manad']]), $manadsData)); ?>];
    const intakter   = [<?php echo implode(',', array_map(fn($m) => $m['intakter'],         $manadsData)); ?>];
    const projKost   = [<?php echo implode(',', array_map(fn($m) => $m['proj_kostnader'],    $manadsData)); ?>];
    const almKost    = [<?php echo implode(',', array_map(fn($m) => $m['allm_kostnader'],    $manadsData)); ?>];
    const netto      = [<?php echo implode(',', array_map(fn($m) => $m['netto'],             $manadsData)); ?>];

    new Chart(document.getElementById('manadsEkonomiChart'), {
        type: 'bar',
        data: {
            labels: manader,
            datasets: [
                {
                    label: 'Intäkter',
                    data: intakter,
                    backgroundColor: 'rgba(40,167,69,0.7)',
                    borderColor: '#28a745',
                    borderWidth: 1,
                    borderRadius: 3,
                    order: 2,
                },
                {
                    label: 'Projektkostnader',
                    data: projKost,
                    backgroundColor: 'rgba(255,193,7,0.7)',
                    borderColor: '#ffc107',
                    borderWidth: 1,
                    borderRadius: 3,
                    order: 3,
                },
                {
                    label: 'Allmänna utgifter',
                    data: almKost,
                    backgroundColor: 'rgba(220,53,69,0.7)',
                    borderColor: '#dc3545',
                    borderWidth: 1,
                    borderRadius: 3,
                    order: 4,
                },
                {
                    label: 'Netto',
                    data: netto,
                    type: 'line',
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    tension: 0.3,
                    fill: false,
                    order: 1,
                    yAxisID: 'y',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => v.toLocaleString('sv-SE') + ' kr'
                    }
                }
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': ' + ctx.raw.toLocaleString('sv-SE') + ' kr'
                    }
                }
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
