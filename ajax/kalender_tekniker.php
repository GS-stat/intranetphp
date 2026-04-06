<?php
require_once '../includes/config.php';
kravInloggning();

$offset      = isset($_GET['vecka_offset'])  ? (int)$_GET['vecka_offset']  : 0;
$tekniker_id = isset($_GET['tekniker_id']) && $_GET['tekniker_id'] !== ''
               ? (int)$_GET['tekniker_id']
               : null;

$idag = new DateTime();
$idag->modify('monday this week');
$idag->modify($offset . ' weeks');
$veckaStart = $idag->format('Y-m-d');
$veckaSlut  = date('Y-m-d', strtotime($veckaStart . ' +6 days'));

$bokningar  = hamtaVeckansBokningarPerTekniker($pdo, $veckaStart, $tekniker_id);
$veckodagar = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];
$tider      = [];
for ($i = 7; $i <= 18; $i++) $tider[] = $i;

$totalBokningar = array_sum(array_map('count', $bokningar));

// ── Bokningsfärger – cyklar per bokning-ID ──────────────────────────────────
$bokningsFarger = ['#dc3545','#0d6efd','#198754','#fd7e14','#6f42c1','#0dcaf0'];

// ── Pre-beräkna kolumnplacering per dag och starttimme ──────────────────────
$kolumnerPerDag      = [];
$antalPerTimmePerDag = [];

foreach ($bokningar as $datum => $dagensBokningar) {
    $perTimme = [];
    foreach ($dagensBokningar as $b) {
        $startH = $b['starttid'] ? (int)date('H', strtotime($b['starttid'])) : 9;
        $perTimme[$startH][] = $b['id'];
    }
    foreach ($perTimme as $h => $ids) {
        $antalPerTimmePerDag[$datum][$h] = count($ids);
        foreach ($ids as $idx => $id) {
            $kolumnerPerDag[$datum][$id] = $idx;
        }
    }
}
?>

<div class="veckokalender-wrapper">
    <div class="vecka-nav d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <button onclick="laddaTeknikerVecka(-1)" class="btn btn-outline-danger btn-sm">
            <i class="fas fa-chevron-left"></i> Föregående
        </button>
        <div class="nuvarande-vecka">
            <strong><?php echo date('d M', strtotime($veckaStart)); ?></strong>
            – <strong><?php echo date('d M Y', strtotime($veckaSlut)); ?></strong>
            <span class="badge bg-secondary ms-2"><?php echo $totalBokningar; ?> bokning<?php echo $totalBokningar !== 1 ? 'ar' : ''; ?></span>
        </div>
        <button onclick="laddaTeknikerVecka(0)" class="btn btn-danger btn-sm">
            <i class="fas fa-calendar-day"></i> Denna vecka
        </button>
        <button onclick="laddaTeknikerVecka(1)" class="btn btn-outline-danger btn-sm">
            Nästa <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <?php if ($totalBokningar === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-calendar-check fa-3x mb-3"></i>
        <p>Inga bokningar denna vecka<?php echo $tekniker_id ? ' för vald tekniker' : ''; ?></p>
    </div>
    <?php else: ?>
    <div class="veckokalender-tek">
        <div class="kalender-grid-tek">
            <!-- Header -->
            <div class="grid-header-tek tid-header-tek">Tid</div>
            <?php for ($i = 0; $i < 7; $i++):
                $datum  = date('Y-m-d', strtotime($veckaStart . " +$i days"));
                $arIdag = date('Y-m-d') === $datum;
                $antal  = count($bokningar[$datum] ?? []);
            ?>
                <div class="grid-header-tek <?php echo $arIdag ? 'idag-header' : ''; ?>">
                    <?php echo $veckodagar[$i]; ?><br>
                    <small><?php echo date('d/m', strtotime($datum)); ?></small>
                    <?php if ($antal > 0): ?>
                        <br><span class="badge bg-danger"><?php echo $antal; ?></span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>

            <!-- Tidsrader -->
            <?php foreach ($tider as $timme): ?>
                <div class="tid-cell-tek"><?php printf('%02d:00', $timme); ?></div>
                <?php for ($dagIndex = 0; $dagIndex < 7; $dagIndex++):
                    $datum           = date('Y-m-d', strtotime($veckaStart . " +$dagIndex days"));
                    $dagensBokningar = $bokningar[$datum] ?? [];
                ?>
                    <div class="dag-cell-tek">
                        <?php foreach ($dagensBokningar as $b):
                            $startH   = $b['starttid'] ? (int)date('H', strtotime($b['starttid'])) : 9;
                            $startMin = $b['starttid'] ? (int)date('i', strtotime($b['starttid'])) : 0;

                            if ($startH !== $timme) continue;

                            $hojdPx  = beraknaBlockHojd($b['starttid'], $b['sluttid']) * 60;
                            $kolumn  = $kolumnerPerDag[$datum][$b['id']] ?? 0;
                            $total   = min($antalPerTimmePerDag[$datum][$timme] ?? 1, 3);
                            $tekNamn = $b['tekniker_namn'] ?? 'Ej tilldelad';
                            $farg    = $bokningsFarger[$b['id'] % count($bokningsFarger)];

                            $baseStyle = "height:{$hojdPx}px; top:{$startMin}px; background:{$farg};";
                            if ($total === 1) {
                                $blockStyle = $baseStyle . "left:2px; right:2px;";
                            } elseif ($total === 2) {
                                if ($kolumn === 0) {
                                    $blockStyle = $baseStyle . "left:1px; width:calc(50% - 2px);";
                                } else {
                                    $blockStyle = $baseStyle . "left:calc(50% + 1px); width:calc(50% - 2px);";
                                }
                            } else {
                                if ($kolumn === 0) {
                                    $blockStyle = $baseStyle . "left:1px; width:calc(33.33% - 2px);";
                                } elseif ($kolumn === 1) {
                                    $blockStyle = $baseStyle . "left:calc(33.33% + 1px); width:calc(33.33% - 2px);";
                                } else {
                                    $blockStyle = $baseStyle . "left:calc(66.66% + 1px); width:calc(33.34% - 2px);";
                                }
                            }
                        ?>
                            <div class="bokning-block-tek"
                                 style="<?php echo $blockStyle; ?>"
                                 onclick="window.location='projekt_visa.php?id=<?php echo $b['id']; ?>'"
                                 title="<?php echo htmlspecialchars($b['rubrik'] . ' – ' . $tekNamn); ?>">
                                <div class="btek-tid">
                                    <?php echo formateraTid($b['starttid']); ?>–<?php echo formateraTid($b['sluttid']); ?>
                                </div>
                                <div class="btek-rubrik"><?php echo htmlspecialchars($b['rubrik']); ?></div>
                                <div class="btek-regnr"><?php echo htmlspecialchars($b['regnummer']); ?></div>
                                <?php if (!$tekniker_id): ?>
                                <div class="btek-tekniker"><?php echo htmlspecialchars($tekNamn); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.veckokalender-wrapper { padding: 15px; }
.nuvarande-vecka { background:#f8f9fa; padding:8px 16px; border-radius:20px; color:#dc3545; }

.veckokalender-tek { background:white; border-radius:12px; overflow-x:auto; box-shadow:0 1px 3px rgba(0,0,0,.1); }
.kalender-grid-tek {
    display:grid;
    grid-template-columns: 70px repeat(7, 1fr);
    min-width: 780px;
}
.grid-header-tek {
    background:#343a40; color:white; padding:10px 6px;
    text-align:center; font-weight:bold; border-right:1px solid #495057; font-size:13px;
}
.grid-header-tek.idag-header { background:#dc3545; }
.tid-header-tek { background:#343a40; }
.tid-cell-tek {
    background:#f8f9fa; border-bottom:1px solid #dee2e6; border-right:1px solid #dee2e6;
    padding:6px 3px; text-align:center; font-size:11px; color:#6c757d;
}
.dag-cell-tek {
    border-bottom:1px solid #dee2e6; border-right:1px solid #dee2e6;
    position:relative; height:60px; background:white; overflow:visible;
}
.bokning-block-tek {
    position:absolute;
    background:#dc3545; color:white; border-radius:5px;
    padding:3px 5px; font-size:10px; cursor:pointer;
    transition:all .15s; overflow:hidden; z-index:2;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.bokning-block-tek:hover { filter:brightness(0.85); transform:scale(1.01); z-index:10; box-shadow:0 4px 8px rgba(0,0,0,.2); }
.btek-tid      { font-weight:bold; font-size:9px; opacity:.9; }
.btek-rubrik   { font-weight:bold; font-size:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.btek-regnr    { font-size:9px; opacity:.75; }
.btek-tekniker { font-size:9px; opacity:.85; font-style:italic; }
</style>

<script>
let teknikerOffset = <?php echo $offset; ?>;
let teknikerId     = <?php echo $tekniker_id ?? 'null'; ?>;

function laddaTeknikerVecka(offsetChange) {
    if (offsetChange === 0) teknikerOffset = 0;
    else teknikerOffset += offsetChange;

    const container = document.getElementById('tekniker-kalender-container');
    container.innerHTML = '<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x text-danger"></i><p class="mt-2">Laddar...</p></div>';

    let url = '../ajax/kalender_tekniker.php?vecka_offset=' + teknikerOffset;
    if (teknikerId) url += '&tekniker_id=' + teknikerId;

    fetch(url).then(r => r.text()).then(html => { container.innerHTML = html; })
              .catch(() => { container.innerHTML = '<div class="alert alert-danger m-3">Kunde inte ladda kalendern.</div>'; });
}
</script>
