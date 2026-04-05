<?php
require_once '../includes/config.php';
kravInloggning();

// Hämta veckans offset (0 = denna vecka, -1 = förra, 1 = nästa)
$offset = isset($_GET['vecka_offset']) ? (int)$_GET['vecka_offset'] : 0;

// Räkna ut veckans start (måndag)
$idag = new DateTime();
$idag->modify('monday this week');
$idag->modify($offset . ' weeks');
$veckaStart = $idag->format('Y-m-d');
$veckaSlut = date('Y-m-d', strtotime($veckaStart . ' +6 days'));

$bokningar = hamtaVeckansBokningar($pdo, $veckaStart);

// Svenska veckodagar
$veckodagar = ['Måndag', 'Tisdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lördag', 'Söndag'];

// Tidslinje 07:00 - 18:00
$tider = [];
for ($i = 7; $i <= 18; $i++) {
    $tider[] = sprintf('%02d:00', $i);
}
?>

<div class="veckokalender-wrapper">
    <div class="vecka-nav">
        <button onclick="laddaVecka(-1)" class="btn btn-outline-danger btn-sm">
            <i class="fas fa-chevron-left"></i> Föregående vecka
        </button>
        <div class="nuvarande-vecka">
            <strong><?php echo date('d M', strtotime($veckaStart)); ?></strong> - 
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
                $datum = date('Y-m-d', strtotime($veckaStart . " +$i days"));
                $dagNamn = $veckodagar[$i];
                $dagSiffra = date('d/m', strtotime($datum));
                $arIdag = (date('Y-m-d') == $datum);
            ?>
                <div class="grid-header <?php echo $arIdag ? 'idag-header' : ''; ?>">
                    <?php echo $dagNamn; ?><br>
                    <small><?php echo $dagSiffra; ?></small>
                </div>
            <?php endfor; ?>
            
            <!-- Tidsrader -->
            <?php foreach ($tider as $tid): 
                $timme = (int)substr($tid, 0, 2);
            ?>
                <div class="tid-cell"><?php echo $tid; ?></div>
                <?php for ($dagIndex = 0; $dagIndex < 7; $dagIndex++): 
                    $datum = date('Y-m-d', strtotime($veckaStart . " +$dagIndex days"));
                    $dagensBokningar = $bokningar[$datum] ?? [];
                ?>
                    <div class="dag-cell" data-datum="<?php echo $datum; ?>" data-timme="<?php echo $timme; ?>">
                        <?php
                        // Visa bokningar som startar vid denna tid
                        foreach ($dagensBokningar as $bokning):
                            $startTid = $bokning['starttid'] ? date('H:i', strtotime($bokning['starttid'])) : '09:00';
                            $slutTid = $bokning['sluttid'] ? date('H:i', strtotime($bokning['sluttid'])) : '17:00';
                            $slutTimme = (int)substr($slutTid, 0, 2);
                            $slutMinut = (int)substr($slutTid, 3, 2);
                            
                            // Beräkna höjd i pixels (60px per timme)
                            $hojdTimmar = beraknaBlockHojd($bokning['starttid'], $bokning['sluttid']);
                            $hojdPx = $hojdTimmar * 60;
                            
                            if ($startTid == $tid):
                        ?>
                                <div class="bokning-block" 
                                     style="height: <?php echo $hojdPx; ?>px; top: 0;"
                                     onclick="window.location='projekt_visa.php?id=<?php echo $bokning['id']; ?>'"
                                     title="<?php echo htmlspecialchars($bokning['rubrik']); ?>">
                                    <div class="bokning-tid">
                                        <?php echo formateraTid($bokning['starttid']); ?> - <?php echo formateraTid($bokning['sluttid']); ?>
                                    </div>
                                    <div class="bokning-rubrik"><?php echo htmlspecialchars($bokning['rubrik']); ?></div>
                                    <div class="bokning-regnr"><?php echo htmlspecialchars($bokning['regnummer']); ?></div>
                                </div>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                <?php endfor; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.veckokalender-wrapper {
    padding: 15px;
}

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

.grid-header.idag-header {
    background: #dc3545;
}

.tid-header {
    background: #343a40;
}

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
    min-height: 60px;
    background: white;
    height: 60px;
}

.bokning-block {
    position: absolute;
    left: 2px;
    right: 2px;
    background: #dc3545;
    color: white;
    border-radius: 6px;
    padding: 4px 6px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
    overflow: hidden;
    z-index: 2;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.bokning-block:hover {
    background: #bb2d3b;
    transform: scale(1.01);
    z-index: 10;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.bokning-tid {
    font-weight: bold;
    font-size: 10px;
    opacity: 0.9;
    margin-bottom: 2px;
}

.bokning-rubrik {
    font-weight: bold;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 11px;
}

.bokning-regnr {
    font-size: 9px;
    opacity: 0.8;
}

@media (max-width: 768px) {
    .bokning-rubrik {
        display: none;
    }
    .bokning-block {
        font-size: 9px;
        padding: 2px 4px;
    }
}

@media (max-width: 1200px) {
    .grid-header {
        font-size: 12px;
        padding: 8px 4px;
    }
    .tid-cell {
        font-size: 10px;
        padding: 6px 2px;
    }
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
    
    // Visa laddningsindikator
    const container = document.getElementById('veckokalender-container');
    container.innerHTML = '<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x text-danger"></i><p class="mt-2">Laddar kalender...</p></div>';
    
    fetch('../ajax/veckokalender.php?vecka_offset=' + currentOffset)
        .then(response => response.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="alert alert-danger m-3">Kunde inte ladda kalendern. Försök igen.</div>';
        });
}
</script>