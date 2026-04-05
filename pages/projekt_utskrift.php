<?php
require_once '../includes/config.php';
kravInloggning();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: projekt_lista.php');
    exit;
}

$projekt = hamtaProjekt($pdo, $id);
if (!$projekt) {
    header('Location: projekt_lista.php');
    exit;
}

$rader = hamtaProjektRader($pdo, $id);
$users = hamtaAllaAnvandare($pdo);

// Hitta ansvarig tekniker-namn
$ansvarigNamn = null;
if (!empty($projekt['ansvarig_tekniker'])) {
    foreach ($users as $u) {
        if ((int)$u['id'] === (int)$projekt['ansvarig_tekniker']) {
            $ansvarigNamn = $u['anvandarnamn'];
            break;
        }
    }
}

$foretagsnamn = 'GS Motors';
$foretagsmail = 'kundservice@gsmotors.se';
$foretagstel  = '0730730009';
$orgnr        = '559550-2062';

function esc($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projekt #<?php echo $projekt['id']; ?> - <?php echo esc($foretagsnamn); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 20px;
            background: white;
            color: #333;
        }
        .header {
            border-bottom: 3px solid #dc3545;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #dc3545;
            font-weight: bold;
            margin: 0;
        }
        .logo small {
            color: #666;
            font-size: 14px;
        }
        .kundinfo {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .detalj-rad {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .detalj-label {
            font-weight: bold;
            color: #555;
            min-width: 160px;
            display: inline-block;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: bold;
            color: white;
            font-size: 13px;
        }
        .status-inkommen { background: #ffc107; color: #000; }
        .status-pågående { background: #007bff; }
        .status-avslutad { background: #28a745; }
        .pris {
            font-size: 22px;
            font-weight: bold;
            color: #dc3545;
        }
        .rad-tabell th {
            background: #343a40;
            color: #fff;
            padding: 8px 10px;
        }
        .rad-tabell td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        .rad-tabell tfoot td {
            border-top: 2px solid #343a40;
            font-weight: bold;
        }
        .viktigt-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 13px;
            color: #777;
        }
        .flagga-varning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 6px 14px;
            border-radius: 6px;
            display: inline-block;
            font-weight: bold;
            color: #856404;
            margin-bottom: 15px;
        }
        @media print {
            .btn-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Utskriftsknapp (syns bara på skärm) -->
    <div class="text-end btn-print mb-3">
        <button onclick="window.print()" class="btn btn-danger">
            <i class="fas fa-print"></i> Skriv ut
        </button>
        <a href="projekt_visa.php?id=<?php echo $id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Tillbaka
        </a>
    </div>

    <!-- ================================ -->
    <!-- HEADER                           -->
    <!-- ================================ -->
    <div class="header">
        <div class="row align-items-center">
            <div class="col-8">
                <div class="logo">
                    <h1><?php echo esc($foretagsnamn); ?></h1>
                    <small>Bilverkstad &amp; Fordonsservice</small>
                </div>
            </div>
            <div class="col-4 text-end">
                <p class="mb-0">
                    <strong>Datum:</strong> <?php echo date('Y-m-d'); ?><br>
                    <strong>Projekt nr:</strong> #<?php echo $projekt['id']; ?>
                </p>
            </div>
        </div>
    </div>

    <?php if (!empty($projekt['flagga'])): ?>
    <div class="flagga-varning">
        <i class="fas fa-exclamation-circle"></i> OBS: SE ÖVER DETTA PROJEKT
    </div>
    <?php endif; ?>

    <!-- ================================ -->
    <!-- KUNDINFORMATION                  -->
    <!-- ================================ -->
    <div class="kundinfo">
        <h4><i class="fas fa-user"></i> Kundinformation</h4>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="detalj-label">Kundtyp</div>
                <div><?php echo esc($projekt['kundTyp'] ?? 'Privat'); ?></div>
            </div>
            <div class="col-md-4">
                <div class="detalj-label">Kontaktperson</div>
                <div><?php echo esc($projekt['kontakt_person_namn']); ?></div>
            </div>
            <div class="col-md-4">
                <div class="detalj-label">Telefon</div>
                <div><?php echo esc($projekt['kontakt_person_telefon']); ?></div>
            </div>
            <div class="col-md-4 mt-2">
                <div class="detalj-label">E-post</div>
                <div><?php echo esc($projekt['kontakt_person_email'] ?: '-'); ?></div>
            </div>
            <div class="col-md-4 mt-2">
                <div class="detalj-label">Registreringsnummer</div>
                <div><strong><?php echo esc($projekt['regnummer']); ?></strong></div>
            </div>
            <?php if ($ansvarigNamn): ?>
            <div class="col-md-4 mt-2">
                <div class="detalj-label">Ansvarig tekniker</div>
                <div><?php echo esc($ansvarigNamn); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================================ -->
    <!-- PROJEKTINFORMATION               -->
    <!-- ================================ -->
    <div class="projekt-detaljer">
        <h4><i class="fas fa-wrench"></i> Projektinformation</h4>

        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Rubrik:</div>
            <div class="col-md-9"><?php echo esc($projekt['rubrik']); ?></div>
        </div>

        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Beskrivning:</div>
            <div class="col-md-9"><?php echo nl2br(esc($projekt['beskrivning'] ?: '-')); ?></div>
        </div>

        <?php if (!empty($projekt['felsokning'])): ?>
        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Felsökning:</div>
            <div class="col-md-9"><?php echo nl2br(esc($projekt['felsokning'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($projekt['atgard'])): ?>
        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Åtgärd:</div>
            <div class="col-md-9"><?php echo nl2br(esc($projekt['atgard'])); ?></div>
        </div>
        <?php endif; ?>

        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Status:</div>
            <div class="col-md-9">
                <?php
                $statusClass = '';
                switch ($projekt['status']) {
                    case 'inkommen': $statusClass = 'status-inkommen'; break;
                    case 'pågående': $statusClass = 'status-pågående'; break;
                    case 'avslutad': $statusClass = 'status-avslutad'; break;
                }
                ?>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php echo ucfirst(esc($projekt['status'])); ?>
                </span>
            </div>
        </div>

        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Planerat datum:</div>
            <div class="col-md-9">
                <?php echo $projekt['planDate']
                    ? date('Y-m-d', strtotime($projekt['planDate']))
                    : 'Ej planerat'; ?>
            </div>
        </div>

        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Inskickat datum:</div>
            <div class="col-md-9"><?php echo date('Y-m-d', strtotime($projekt['createdDate'])); ?></div>
        </div>

        <?php if (!empty($projekt['avslutad'])): ?>
        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Avslutat datum:</div>
            <div class="col-md-9"><?php echo date('Y-m-d', strtotime($projekt['avslutadDatum'])); ?></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($projekt['dackforvaring'])): ?>
        <div class="detalj-rad row">
            <div class="col-md-3 detalj-label">Däckförvaring:</div>
            <div class="col-md-9">
                Ja<?php if (!empty($projekt['dackforvaring_id'])): ?> – ID: <?php echo esc($projekt['dackforvaring_id']); ?><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================================ -->
    <!-- SPECIFIKATION (RADER)            -->
    <!-- ================================ -->
    <?php if (!empty($rader)): ?>
    <div class="mt-4">
        <h4><i class="fas fa-list-ul"></i> Specifikation</h4>
        <table class="table rad-tabell mt-3">
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Beskrivning</th>
                    <th class="text-end">Pris</th>
                    <th class="text-end">Antal</th>
                    <th class="text-end">Rabatt</th>
                    <th class="text-end">Summa</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $radTotal = 0;
                foreach ($rader as $rad):
                    $summa = ((float)$rad['pris'] * (float)$rad['antal']) - (float)$rad['rabatt'];
                    $radTotal += $summa;
                ?>
                <tr>
                    <td><?php echo esc(ucfirst($rad['typ'])); ?></td>
                    <td><?php echo esc($rad['beskrivning']); ?></td>
                    <td class="text-end"><?php echo number_format($rad['pris'], 0, ',', ' '); ?> kr</td>
                    <td class="text-end">
                        <?php echo rtrim(rtrim(number_format((float)$rad['antal'], 2, ',', ''), '0'), ','); ?>
                        <?php echo $rad['typ'] === 'arbete' ? 'tim' : 'st'; ?>
                    </td>
                    <td class="text-end">
                        <?php echo $rad['rabatt'] > 0
                            ? number_format($rad['rabatt'], 0, ',', ' ') . ' kr'
                            : '-'; ?>
                    </td>
                    <td class="text-end"><?php echo number_format($summa, 0, ',', ' '); ?> kr</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" class="text-end">Totalt:</td>
                    <td class="text-end pris"><?php echo number_format($radTotal, 0, ',', ' '); ?> kr</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
    <!-- Fallback: enkelt pris om inga rader finns -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-credit-card"></i> Prisinformation</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="detalj-label">Totalt pris:</span>
                        <span class="pris">
                            <?php echo $projekt['pris']
                                ? number_format($projekt['pris'], 0, ',', ' ') . ' kr'
                                : 'Ej angett'; ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="detalj-label">Betalningsstatus:</span>
                        <span>
                            <?php if (!empty($projekt['betald'])): ?>
                                <span class="badge bg-success fs-6">Betald</span>
                            <?php else: ?>
                                <span class="badge bg-danger fs-6">Obetald</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Betalningsstatus om rader finns -->
    <?php if (!empty($rader)): ?>
    <div class="mt-2">
        <strong>Betalningsstatus:</strong>
        <?php if (!empty($projekt['betald'])): ?>
            <span class="badge bg-success">Betald</span>
        <?php else: ?>
            <span class="badge bg-danger">Obetald</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ================================ -->
    <!-- VIKTIG INFORMATION               -->
    <!-- ================================ -->
    <div class="viktigt-info mt-4">
        <h5><i class="fas fa-info-circle"></i> Viktig information</h5>
        <ul class="mb-0">
            <li>Arbetet utförs enligt lämnad beskrivning</li>
            <li>Priset är inklusive moms</li>
            <li>Vid frågor, kontakta oss på <?php echo esc($foretagsmail); ?></li>
            <li>Denna orderbekräftelse sparas för din trygghet</li>
        </ul>
    </div>

    <!-- ================================ -->
    <!-- FOOTER                           -->
    <!-- ================================ -->
    <div class="footer">
        <p>
            <strong><?php echo esc($foretagsnamn); ?></strong><br>
            Tel: <?php echo esc($foretagstel); ?> |
            E-post: <?php echo esc($foretagsmail); ?><br>
            Org.nr: <?php echo esc($orgnr); ?> | www.gsmotors.se
        </p>
    </div>

</div>
</body>
</html>
