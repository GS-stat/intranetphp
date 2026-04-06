<?php
require_once '../includes/config.php';
kravInloggning();

$statistik = getProjektStatistik($pdo);
$prisStatistik = getPrisIntervallStatistik($pdo);
$kundTypStatistik = getKundTypStatistik($pdo);
$manadsStatistik = getManadsStatistik($pdo);
$projekt = hamtaAllaProjekt($pdo);
$notiser = getDashboardNotiser($pdo);
?>
<?php include '../includes/header.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard - Översikt</h1>
        <p class="text-muted">Välkommen tillbaka, <?php echo $_SESSION['anvandare_namn']; ?>!</p>
    </div>
    <div class="col-md-4 text-end">
        <button id="refreshStats" class="btn btn-outline-danger">
            <i class="fas fa-sync-alt"></i> Uppdatera statistik
        </button>
    </div>
</div>

<!-- Statistik-kort -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stat-kort text-center">
            <i class="fas fa-clipboard-list fa-2x text-primary mb-2"></i>
            <div class="stat-siffra"><?php echo $statistik['total']; ?></div>
            <div class="stat-etikett">Totalt uppdrag</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-kort text-center">
            <i class="fas fa-inbox fa-2x text-warning mb-2"></i>
            <div class="stat-siffra"><?php echo $statistik['inkommen']; ?></div>
            <div class="stat-etikett">Inkomna</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-kort text-center">
            <i class="fas fa-spinner fa-2x text-primary mb-2"></i>
            <div class="stat-siffra"><?php echo $statistik['pågående']; ?></div>
            <div class="stat-etikett">Pågående</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-kort text-center">
            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
            <div class="stat-siffra"><?php echo $statistik['avslutad']; ?></div>
            <div class="stat-etikett">Avslutade</div>
        </div>
    </div>
</div>
<?php if (!empty($notiser)): ?>
<!-- Notiser / Åtgärdskrävande -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-bell"></i> Åtgärder krävs
                    <span class="badge bg-dark ms-2"><?php echo count($notiser); ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($notiser as $n): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2
                                list-group-item-<?php echo $n['typ'] === 'danger' ? 'danger' : ($n['typ'] === 'warning' ? 'warning' : 'info'); ?>">
                        <div>
                            <i class="fas <?php echo htmlspecialchars($n['ikon']); ?> me-2"></i>
                            <?php echo $n['text']; ?>
                        </div>
                        <a href="<?php echo htmlspecialchars($n['link']); ?>"
                           class="btn btn-sm btn-outline-dark ms-3 flex-shrink-0">
                            <?php echo htmlspecialchars($n['link_text']); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Veckokalender för bokningar -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-week"></i> Veckokalender - Bokningar</h5>
            </div>
            <div class="card-body p-0">
                <div id="veckokalender-container">
                    <?php
                    // Hämta veckans bokningar
                    $idag = new DateTime();
                    $idag->modify('monday this week');
                    $veckaStart = $idag->format('Y-m-d');
                    $bokningar = hamtaVeckansBokningar($pdo, $veckaStart);
                    ?>
                    <?php include '../ajax/veckokalender.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Senaste projekt -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history"></i> Senaste projekten</h5>
                <a href="projekt_lista.php" class="btn btn-sm btn-light">Visa alla <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="dashboard-tabell" id="projektTabell">
                        <thead>
                            <tr>
                                <th style="width:6px" class="border-0"></th>
                                <th style="width:46px">ID</th>
                                <th style="width:100px">Regnr</th>
                                <th>Uppdrag</th>
                                <th>Kontakt</th>
                                <th style="width:105px">Status</th>
                                <th style="width:110px">Ekonomi</th>
                                <th style="width:90px">Skapad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($projekt, 0, 10) as $p): ?>
                            <tr onclick="window.location.href='projekt_visa.php?id=<?php echo $p['id']; ?>'"
                                class="<?php echo !empty($p['flagga']) ? 'db-rad-flaggad' : ($p['betald'] ? 'db-rad-betald' : 'db-rad-obetald'); ?>">
                                <td class="db-rad-indikator p-0">
                                    <?php if (!empty($p['flagga'])): ?>
                                        <div class="db-flagg-linje"></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">#<?php echo $p['id']; ?></td>
                                <td>
                                    <span class="db-regnr-pill"><?php echo htmlspecialchars($p['regnummer']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold lh-sm"><?php echo htmlspecialchars($p['rubrik']); ?></div>
                                    <div class="mt-1"><?php echo getKundTypBadge($p['kundTyp'] ?? 'Privat'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($p['kontakt_person_namn']); ?></td>
                                <td><?php echo getStatusBadge($p['status']); ?></td>
                                <td>
                                    <div class="fw-semibold lh-sm">
                                        <?php echo $p['pris'] ? number_format($p['pris'], 0, ',', ' ') . ' kr' : '–'; ?>
                                    </div>
                                    <?php if ($p['betald']): ?>
                                        <small class="db-betald-ja"><i class="fas fa-check-circle"></i> Betald</small>
                                    <?php else: ?>
                                        <small class="db-betald-nej"><i class="fas fa-circle"></i> Obetald</small>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?php echo date('d/m/y', strtotime($p['createdDate'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Diagram-rad 1 -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Projekt per prisintervall</h5>
            </div>
            <div class="card-body">
                <canvas id="prisChart" style="width:100%; min-height:300px;"></canvas>
                
                <div class="row mt-3 text-center">
                    <div class="col-4">
                        <small class="text-muted">Under 5000 kr</small>
                        <h6><?php echo $prisStatistik['under_5000']; ?> st</h6>
                        <small><?php echo number_format($prisStatistik['summa_under_5000'], 0); ?> kr</small>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">5000 - 25000 kr</small>
                        <h6><?php echo $prisStatistik['mellan_5000_25000']; ?> st</h6>
                        <small><?php echo number_format($prisStatistik['summa_mellan_5000_25000'], 0); ?> kr</small>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Över 25000 kr</small>
                        <h6><?php echo $prisStatistik['over_25000']; ?> st</h6>
                        <small><?php echo number_format($prisStatistik['summa_over_25000'], 0); ?> kr</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Fördelning per kundtyp</h5>
            </div>
            <div class="card-body">
                <canvas id="kundTypChart" style="width:100%; min-height:300px;"></canvas>
                
                <div class="row mt-3">
                    <?php foreach ($kundTypStatistik as $kund): ?>
                    <div class="col-4 text-center">
                        <?php
                        $icon = 'fa-user';
                        $color = 'primary';
                        if ($kund['kundTyp'] == 'Företag') {
                            $icon = 'fa-building';
                            $color = 'info';
                        } elseif ($kund['kundTyp'] == 'Försäkring') {
                            $icon = 'fa-shield-alt';
                            $color = 'warning';
                        }
                        ?>
                        <i class="fas <?php echo $icon; ?> fa-2x text-<?php echo $color; ?>"></i>
                        <h6><?php echo $kund['kundTyp']; ?></h6>
                        <strong><?php echo $kund['antal_projekt']; ?> st</strong><br>
                        <small><?php echo number_format($kund['total_intakt'], 0); ?> kr</small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Diagram-rad 2 -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Månadsstatistik <?php echo date('Y'); ?></h5>
            </div>
            <div class="card-body">
                <canvas id="manadsChart" style="width:100%; height:300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Chart.js version:', Chart.version);
    
    // 1. Prisintervall-diagram (cirkeldiagram)
    const prisCtx = document.getElementById('prisChart').getContext('2d');
    // Prisintervall-diagram med storlekskontroll
    new Chart(prisCtx, {
        type: 'doughnut',
        data: {
            labels: ['Under 5000 kr', '5000 - 25000 kr', 'Över 25000 kr'],
            datasets: [{
                data: [
                    <?php echo $prisStatistik['under_5000']; ?>,
                    <?php echo $prisStatistik['mellan_5000_25000']; ?>,
                    <?php echo $prisStatistik['over_25000']; ?>
                ],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 1.5, // Kontrollerar höjd/bredd-förhållande
            cutout: '55%',     // Gör hålet i mitten mindre (doughnut)
            radius: '70%',     // Kontrollerar diagrammets storlek
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: {
                            size: 11  // Mindre text
                        }
                    }
                }
            },
            layout: {
                padding: {
                    top: 10,
                    bottom: 10,
                    left: 10,
                    right: 10
                }
            }
        }
    });
    
    // 2. Kundtyp-diagram (stapeldiagram)
    const kundCtx = document.getElementById('kundTypChart').getContext('2d');
    
    // Förbered data från PHP
    const kundLabels = [];
    const kundData = [];
    const kundColors = [];
    
    <?php foreach ($kundTypStatistik as $kund): ?>
        kundLabels.push('<?php echo $kund['kundTyp']; ?>');
        kundData.push(<?php echo $kund['antal_projekt']; ?>);
        
        <?php if ($kund['kundTyp'] == 'Privat'): ?>
            kundColors.push('#007bff');
        <?php elseif ($kund['kundTyp'] == 'Företag'): ?>
            kundColors.push('#17a2b8');
        <?php else: ?>
            kundColors.push('#ffc107');
        <?php endif; ?>
    <?php endforeach; ?>
    
    new Chart(kundCtx, {
        type: 'bar',
        data: {
            labels: kundLabels,
            datasets: [{
                label: 'Antal projekt',
                data: kundData,
                backgroundColor: kundColors,
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Antal: ${context.raw} st`;
                        }
                    }
                }
            }
        }
    });
    
    // 3. Månadsdiagram (linjediagram)
    const manadsCtx = document.getElementById('manadsChart').getContext('2d');
    
    // Skapa arrayer för årets månader
    const manader = ['Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec'];
    const projektData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    const intaktData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    
    <?php foreach ($manadsStatistik as $manad): ?>
        projektData[<?php echo $manad['manad'] - 1; ?>] = <?php echo $manad['antal_projekt']; ?>;
        intaktData[<?php echo $manad['manad'] - 1; ?>] = <?php echo $manad['intakter'] ?: 0; ?>;
    <?php endforeach; ?>
    
    new Chart(manadsCtx, {
        type: 'line',
        data: {
            labels: manader,
            datasets: [
                {
                    label: 'Antal projekt',
                    data: projektData,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: '#dc3545',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    yAxisID: 'y-projekt'
                },
                {
                    label: 'Intäkter (kr)',
                    data: intaktData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: '#28a745',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    yAxisID: 'y-intakter'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                'y-projekt': {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Antal projekt'
                    },
                    grid: {
                        color: 'rgba(220, 53, 69, 0.1)'
                    },
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                },
                'y-intakter': {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Intäkter (kr)'
                    },
                    grid: {
                        drawOnChartArea: false,
                        color: 'rgba(40, 167, 69, 0.1)'
                    },
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('sv-SE') + ' kr';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.raw || 0;
                            if (context.dataset.label === 'Intäkter (kr)') {
                                return label + ': ' + value.toLocaleString('sv-SE') + ' kr';
                            }
                            return label + ': ' + value + ' st';
                        }
                    }
                },
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                }
            }
        }
    });
    
    console.log('Alla diagram skapade!');
});

// Refresh-funktionalitet
$('#refreshStats').click(function() {
    location.reload();
});
</script>

<style>
/* ── Dashboard senaste projekt-tabell ── */
.dashboard-tabell {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
}
.dashboard-tabell thead tr {
    background: #212529;
    color: #fff;
}
.dashboard-tabell thead th {
    padding: 10px 12px;
    font-weight: 600;
    font-size: 0.78rem;
    letter-spacing: .03em;
    text-transform: uppercase;
    border: none;
    white-space: nowrap;
}
.dashboard-tabell tbody tr {
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color .12s ease;
}
.dashboard-tabell tbody tr:hover { background: #fdf3f4; }
.dashboard-tabell tbody td { padding: 10px 12px; vertical-align: middle; }

.db-rad-indikator { padding: 0 !important; width: 6px; }
.db-flagg-linje {
    width: 6px; min-height: 44px; height: 100%;
    background: #dc3545; border-radius: 3px 0 0 3px;
}
.db-rad-flaggad   { background: #fff9f9; }
.db-rad-betald    { background: rgba(25, 135, 84, 0.07); }
.db-rad-betald:hover  { background: rgba(25, 135, 84, 0.16) !important; }
.db-rad-obetald   { background: rgba(220, 53, 69, 0.06); }
.db-rad-obetald:hover { background: rgba(220, 53, 69, 0.13) !important; }

.db-regnr-pill {
    display: inline-block;
    font-family: 'Courier New', monospace;
    font-weight: 700;
    font-size: 0.82rem;
    letter-spacing: .06em;
    color: #212529;
    background: #f8f9fa;
    border: 1.5px solid #dee2e6;
    border-radius: 4px;
    padding: 3px 8px;
    white-space: nowrap;
}
.db-betald-ja  { color: #198754; font-size: 0.75rem; }
.db-betald-nej { color: #b8110b; font-size: 0.75rem; }
</style>

<?php include '../includes/footer.php'; ?>