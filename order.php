<?php
/**
 * order.php – Publik arbetsorder-sida
 *
 * Steg 1: Användaren anger PIN (4 siffror) för att låsa upp ordern.
 * Steg 2: Giltig PIN visar full arbetsorder (liknande projekt_utskrift.php).
 *
 * URL: /order.php?t=<token>
 */
require_once __DIR__ . '/includes/config.php';

$token = trim($_GET['t'] ?? '');

// Om inget token → visa felmeddelande direkt
if (!$token) {
    $fel = 'Ogiltig länk. Kontrollera att du kopierade hela länken från SMS:et.';
}

$projekt   = null;
$pinFel    = false;
$visaOrder = false;

if ($token && empty($fel)) {
    $projekt = hamtaProjektMedToken($pdo, $token);

    if (!$projekt) {
        $fel = 'Länken är ogiltig eller har gått ut (giltig i ' . ORDER_LINK_DAGAR . ' dagar).';
    }
}

// Hantera PIN-formulär (POST)
if ($projekt && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pinInput = trim($_POST['pin'] ?? '');

    if (valideraPublikPin($pdo, (int)$projekt['id'], $pinInput)) {
        $visaOrder = true;
    } else {
        $pinFel = true;
    }
}

// Hämta rader om vi ska visa ordern
$rader = [];
if ($visaOrder && $projekt) {
    $rader = hamtaProjektRader($pdo, (int)$projekt['id']);
}

function esc($val): string {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

$foretagsnamn = 'GS Motors';
$foretagsmail = 'kundservice@gsmotors.se';
$foretagstel  = '073-073 00 09';
$orgnr        = '559550-2062';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arbetsorder – <?php echo esc($foretagsnamn); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            padding: 20px 0 60px;
        }
        .pin-card {
            max-width: 420px;
            margin: 80px auto 0;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12);
        }
        .pin-card .card-header {
            border-radius: 12px 12px 0 0;
            background: #dc3545;
            color: white;
            text-align: center;
            padding: 24px;
        }
        .pin-card .card-header h2 {
            margin: 0;
            font-size: 1.4rem;
        }
        .pin-input {
            font-size: 2rem;
            text-align: center;
            letter-spacing: 8px;
            font-weight: bold;
        }
        /* Order layout */
        .order-wrapper {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }
        .order-header {
            border-bottom: 3px solid #dc3545;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo h1 { color: #dc3545; font-weight: bold; margin: 0; }
        .logo small { color: #666; font-size: 14px; }
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
        .pris { font-size: 22px; font-weight: bold; color: #dc3545; }
        .rad-tabell th { background: #343a40; color: #fff; padding: 8px 10px; }
        .rad-tabell td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
        .rad-tabell tfoot td { border-top: 2px solid #343a40; font-weight: bold; }
        .viktigt-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .order-footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 13px;
            color: #777;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .order-wrapper { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>

<?php if (!empty($fel)): ?>
<!-- ===== FELMEDDELANDE ===== -->
<div class="container" style="max-width:500px; margin-top:100px;">
    <div class="alert alert-danger text-center">
        <i class="fas fa-exclamation-circle fa-2x mb-3 d-block"></i>
        <strong>Länken är ogiltig</strong><br>
        <?php echo esc($fel); ?>
    </div>
    <div class="text-center mt-3">
        <small class="text-muted">
            Kontakta <?php echo esc($foretagsnamn); ?> om du behöver hjälp.<br>
            Tel: <?php echo esc($foretagstel); ?>
        </small>
    </div>
</div>

<?php elseif (!$visaOrder): ?>
<!-- ===== PIN-FORMULÄR ===== -->
<div class="container">
    <div class="card pin-card">
        <div class="card-header">
            <div class="mb-2"><i class="fas fa-lock fa-2x"></i></div>
            <h2><?php echo esc($foretagsnamn); ?></h2>
            <p class="mb-0 mt-1 opacity-75">Arbetsorder – ange PIN för att fortsätta</p>
        </div>
        <div class="card-body p-4">
            <?php if ($pinFel): ?>
            <div class="alert alert-danger text-center">
                <i class="fas fa-times-circle me-1"></i> Fel PIN-kod. Försök igen.
            </div>
            <?php endif; ?>

            <p class="text-muted text-center mb-4">
                Du fick PIN-koden i samma SMS som denna länk.
                Ange den 4-siffriga koden nedan.
            </p>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold text-center d-block">PIN-kod</label>
                    <input type="text"
                           name="pin"
                           class="form-control pin-input"
                           maxlength="4"
                           inputmode="numeric"
                           pattern="[0-9]{4}"
                           placeholder="____"
                           autofocus
                           autocomplete="one-time-code"
                           required>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-danger btn-lg">
                        <i class="fas fa-unlock me-1"></i> Öppna arbetsorder
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <small class="text-muted">
                    Fordon: <strong><?php echo esc($projekt['regnummer']); ?></strong>
                </small>
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <small class="text-muted">
            <?php echo esc($foretagsnamn); ?> &nbsp;|&nbsp;
            Tel: <?php echo esc($foretagstel); ?> &nbsp;|&nbsp;
            <?php echo esc($foretagsmail); ?>
        </small>
    </div>
</div>

<?php else: ?>
<!-- ===== ARBETSORDER ===== -->
<div class="container">
    <div class="no-print text-end mb-3">
        <button onclick="window.print()" class="btn btn-danger">
            <i class="fas fa-print"></i> Skriv ut
        </button>
    </div>

    <div class="order-wrapper">
        <!-- HEADER -->
        <div class="order-header">
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
                        <strong>Order nr:</strong> #<?php echo $projekt['id']; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- KUNDINFORMATION -->
        <div class="kundinfo">
            <h4><i class="fas fa-user"></i> Kundinformation</h4>
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="detalj-label">Kontaktperson</div>
                    <div><?php echo esc($projekt['kontakt_person_namn']); ?></div>
                </div>
                <div class="col-md-4">
                    <div class="detalj-label">Telefon</div>
                    <div><?php echo esc($projekt['kontakt_person_telefon']); ?></div>
                </div>
                <div class="col-md-4">
                    <div class="detalj-label">Registreringsnummer</div>
                    <div><strong><?php echo esc($projekt['regnummer']); ?></strong></div>
                </div>
            </div>
        </div>

        <!-- PROJEKTINFORMATION -->
        <div class="mb-4">
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
                    $statusKlass = match($projekt['status']) {
                        'inkommen' => 'status-inkommen',
                        'pågående' => 'status-pågående',
                        'avslutad' => 'status-avslutad',
                        default    => '',
                    };
                    ?>
                    <span class="status-badge <?php echo $statusKlass; ?>">
                        <?php echo ucfirst(esc($projekt['status'])); ?>
                    </span>
                    <?php if (!empty($projekt['betald'])): ?>
                        <span class="badge bg-success ms-2">Betald</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detalj-rad row">
                <div class="col-md-3 detalj-label">Inskickat:</div>
                <div class="col-md-9"><?php echo date('Y-m-d', strtotime($projekt['createdDate'])); ?></div>
            </div>

            <?php if (!empty($projekt['planDate'])): ?>
            <div class="detalj-rad row">
                <div class="col-md-3 detalj-label">Planerat datum:</div>
                <div class="col-md-9"><?php echo date('Y-m-d', strtotime($projekt['planDate'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- SPECIFIKATION -->
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
        <?php elseif (!empty($projekt['pris'])): ?>
        <div class="mt-4">
            <strong>Totalt pris:</strong>
            <span class="pris ms-2"><?php echo number_format((float)$projekt['pris'], 0, ',', ' '); ?> kr</span>
        </div>
        <?php endif; ?>

        <!-- VIKTIG INFORMATION -->
        <div class="viktigt-info mt-4">
            <h5><i class="fas fa-info-circle"></i> Viktig information</h5>
            <ul class="mb-0">
                <li>Arbetet utförs enligt lämnad beskrivning</li>
                <li>Priset är inklusive moms</li>
                <li>Vid frågor, kontakta oss på <?php echo esc($foretagsmail); ?></li>
                <li>Denna arbetsorder sparas för din trygghet</li>
            </ul>
        </div>

        <!-- FOOTER -->
        <div class="order-footer">
            <p>
                <strong><?php echo esc($foretagsnamn); ?></strong><br>
                Tel: <?php echo esc($foretagstel); ?> |
                E-post: <?php echo esc($foretagsmail); ?><br>
                Org.nr: <?php echo esc($orgnr); ?> | www.gsmotors.se
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>
