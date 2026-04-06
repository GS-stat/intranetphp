<?php
require_once '../includes/config.php';
kravInloggning();

$offset = isset($_GET['vecka_offset']) ? (int)$_GET['vecka_offset'] : 0;

$idag = new DateTime();
$idag->modify('monday this week');
$idag->modify($offset . ' weeks');
$veckaStart = $idag->format('Y-m-d');
$veckaSlut  = date('Y-m-d', strtotime($veckaStart . ' +6 days'));

$bokningar = hamtaVeckansBokningar($pdo, $veckaStart);

$veckodagar = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];

// Tidslinje 07:00 – 18:00
$tider = [];
for ($i = 7; $i <= 18; $i++) {
    $tider[] = $i;
}

// ── Bokningsfärger – cyklar per bokning-ID ──────────────────────────────────
$bokningsFarger = ['#dc3545','#0d6efd','#198754','#fd7e14','#6f42c1','#0dcaf0'];

// ── Pre-beräkna kolumnplacering per dag (overlap-aware) ──────────────────────
// Detekterar verkliga tidsöverlapp istället för att gruppera per starttimme.
$kolumnerPerDag      = [];  // [datum][bokning_id] => kolumn (0, 1, 2)
$totalKolumnerPerDag = [];  // [datum][bokning_id] => totalt antal kolumner i overlap-gruppen

foreach ($bokningar as $datum => $dagensBokningar) {
    // Sortera bokningar efter starttid
    usort($dagensBokningar, function($a, $b) {
        return strtotime($a['starttid']) - strtotime($b['starttid']);
    });

    $kolumn     = [];  // bokning_id => tilldelad kolumn
    $startTider = [];  // bokning_id => unix timestamp start
    $slutTider  = [];  // bokning_id => unix timestamp slut

    foreach ($dagensBokningar as $b) {
        $startTs = $b['starttid'] ? strtotime($b['starttid']) : strtotime($datum . ' 09:00');
        $slutTs  = $b['sluttid']  ? strtotime($b['sluttid'])  : strtotime($datum . ' 10:00');
        $startTider[$b['id']] = $startTs;
        $slutTider[$b['id']]  = $slutTs;

        // Hitta kolumner upptagna av överlappande bokningar
        $upptagna = [];
        foreach ($kolumn as $otherId => $otherKol) {
            if ($startTs < $slutTider[$otherId] && $slutTs > $startTider[$otherId]) {
                $upptagna[] = $otherKol;
            }
        }

        // Tilldela lägsta lediga kolumn
        $k = 0;
        while (in_array($k, $upptagna)) $k++;
        $kolumn[$b['id']] = $k;
    }

    // Beräkna totalKolumner: för varje bokning, max kolumn bland alla den överlappar + 1
    $totalKol = [];
    foreach ($dagensBokningar as $b) {
        $maxKol = $kolumn[$b['id']];
        foreach ($kolumn as $otherId => $otherKol) {
            if ($otherId === $b['id']) continue;
            if ($startTider[$b['id']] < $slutTider[$otherId] && $slutTider[$b['id']] > $startTider[$otherId]) {
                $maxKol = max($maxKol, $otherKol);
            }
        }
        $totalKol[$b['id']] = min($maxKol + 1, 3);
    }

    $kolumnerPerDag[$datum]      = $kolumn;
    $totalKolumnerPerDag[$datum] = $totalKol;
}
?>

<div class="veckokalender-wrapper">
    <div class="vecka-nav">
        <button onclick="laddaVecka(-1)" class="btn btn-outline-danger btn-sm">
            <i class="fas fa-chevron-left"></i> Föregående vecka
        </button>
        <div class="nuvarande-vecka">
            <strong><?php echo date('d M', strtotime($veckaStart)); ?></strong> –
            <strong><?php echo date('d M Y', strtotime($veckaSlut)); ?></strong>
        </div>
        <button onclick="laddaVecka(0)" class="btn btn-danger btn-sm">
            <i class="fas fa-calendar-day"></i> Denna vecka
        </button>
        <button onclick="laddaVecka(1)" class="btn btn-outline-danger btn-sm">
            Nästa vecka <i class="fas fa-chevron-right"></i>
        </button>
    </div>

    <div class="veckokalender">
        <div class="kalender-grid">
            <!-- Header -->
            <div class="grid-header tid-header">Tid</div>
            <?php for ($i = 0; $i < 7; $i++):
                $datum    = date('Y-m-d', strtotime($veckaStart . " +$i days"));
                $arIdag   = (date('Y-m-d') === $datum);
            ?>
                <div class="grid-header <?php echo $arIdag ? 'idag-header' : ''; ?>">
                    <?php echo $veckodagar[$i]; ?><br>
                    <small><?php echo date('d/m', strtotime($datum)); ?></small>
                </div>
            <?php endfor; ?>

            <!-- Tidsrader -->
            <?php foreach ($tider as $timme): ?>
                <div class="tid-cell"><?php printf('%02d:00', $timme); ?></div>
                <?php for ($dagIndex = 0; $dagIndex < 7; $dagIndex++):
                    $datum           = date('Y-m-d', strtotime($veckaStart . " +$dagIndex days"));
                    $dagensBokningar = $bokningar[$datum] ?? [];
                ?>
                    <div class="dag-cell" data-datum="<?php echo $datum; ?>" data-timme="<?php echo $timme; ?>">
                        <?php foreach ($dagensBokningar as $bokning):
                            $startH   = $bokning['starttid'] ? (int)date('H', strtotime($bokning['starttid'])) : 9;
                            $startMin = $bokning['starttid'] ? (int)date('i', strtotime($bokning['starttid'])) : 0;

                            if ($startH !== $timme) continue;

                            $hojdPx  = beraknaBlockHojd($bokning['starttid'], $bokning['sluttid']) * 60;
                            $kolumn  = $kolumnerPerDag[$datum][$bokning['id']] ?? 0;
                            $total   = $totalKolumnerPerDag[$datum][$bokning['id']] ?? 1;
                            $farg    = $bokningsFarger[$bokning['id'] % count($bokningsFarger)];

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
                            <div class="bokning-block"
                                 style="<?php echo $blockStyle; ?>"
                                 onclick="window.location='projekt_visa.php?id=<?php echo $bokning['id']; ?>'"
                                 title="<?php echo htmlspecialchars($bokning['rubrik']); ?>">
                                <div class="bokning-tid">
                                    <?php echo formateraTid($bokning['starttid']); ?>&ndash;<?php echo formateraTid($bokning['sluttid']); ?>
                                </div>
                                <div class="bokning-rubrik"><?php echo htmlspecialchars($bokning['rubrik']); ?></div>
                                <div class="bokning-regnr"><?php echo htmlspecialchars($bokning['regnummer']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.veckokalender-wrapper { padding: 15px; }

.vecka-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.nuvarande-vecka {
    background: #f8f9fa;
    padding: 8px 20px;
    border-radius: 20px;
    color: #dc3545;
    font-size: 16px;
}

.veckokalender {
    background: white;
    border-radius: 12px;
    overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.kalender-grid {
    display: grid;
    grid-template-columns: 80px repeat(7, 1fr);
    min-width: 900px;
}

.grid-header {
    background: #343a40;
    color: white;
    padding: 12px 8px;
    text-align: center;
    font-weight: bold;
    border-right: 1px solid #495057;
    font-size: 14px;
}
.grid-header.idag-header { background: #dc3545; }
.tid-header { background: #343a40; }

.tid-cell {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    padding: 8px 4px;
    text-align: center;
    font-size: 12px;
    color: #6c757d;
}

.dag-cell {
    border-bottom: 1px solid #dee2e6;
    border-right: 1px solid #dee2e6;
    position: relative;
    height: 60px;
    background: white;
    overflow: visible;
}

.bokning-block {
    position: absolute;
    background: #dc3545;
    color: white;
    border-radius: 5px;
    padding: 3px 5px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.15s;
    overflow: hidden;
    z-index: 2;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.bokning-block:hover {
    filter: brightness(0.85);
    transform: scale(1.01);
    z-index: 10;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.bokning-tid    { font-weight: bold; font-size: 9px; opacity: .9; margin-bottom: 1px; }
.bokning-rubrik { font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 10px; }
.bokning-regnr  { font-size: 9px; opacity: .8; }

@media (max-width: 768px) {
    .bokning-rubrik { display: none; }
    .bokning-block  { font-size: 9px; padding: 2px 3px; }
}
</style>

<script>
let currentOffset = <?php echo $offset; ?>;

function laddaVecka(offsetChange) {
    if (offsetChange === 0) {
        currentOffset = 0;
    } else {
        currentOffset += offsetChange;
    }

    const container = document.getElementById('veckokalender-container');
    container.innerHTML = '<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x text-danger"></i><p class="mt-2">Laddar kalender...</p></div>';

    fetch('../ajax/veckokalender.php?vecka_offset=' + currentOffset)
        .then(response => response.text())
        .then(html => { container.innerHTML = html; })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="alert alert-danger m-3">Kunde inte ladda kalendern. Försök igen.</div>';
        });
}
</script>
