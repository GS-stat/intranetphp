<?php
require_once __DIR__ . '/includes/config.php';

$token = trim($_GET['t'] ?? '');
$fel   = '';

if (!$token) {
    $fel = 'Ogiltig länk. Kontrollera att du kopierade hela länken från SMS:et.';
}

$projekt   = null;
$pinFel    = false;
$visaOrder = false;

if ($token && !$fel) {
    $projekt = hamtaProjektMedToken($pdo, $token);
    if (!$projekt) {
        $fel = 'Länken är ogiltig eller har gått ut (giltig i ' . ORDER_LINK_DAGAR . ' dagar).';
    }
}

if ($projekt && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pinInput = trim($_POST['pin'] ?? '');
    if (valideraPublikPin($pdo, (int)$projekt['id'], $pinInput)) {
        $visaOrder = true;
    } else {
        $pinFel = true;
    }
}

$rader = [];
if ($visaOrder && $projekt) {
    $rader = hamtaProjektRader($pdo, (int)$projekt['id']);
}

function esc($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function statusFarg($status) {
    if ($status === 'avslutad') return '#28a745';
    if ($status === 'pågående') return '#0d6efd';
    return '#ffc107';
}

function statusText($status) {
    if ($status === 'avslutad') return 'Avslutad';
    if ($status === 'pågående') return 'Pågående';
    if ($status === 'inkommen') return 'Inkommen';
    return ucfirst($status);
}

$foretagsnamn = 'GS Motors AB';
$foretagstel  = '073-073 00 09';
$foretagsmail = 'kundservice@gsmotors.se';
$foretagsadr  = 'Åkervägen 10, 17741 Järfälla';
$orgnr        = '559550-2062';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Arbetsorder – GS Motors</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f2f2f7;
            color: #1c1c1e;
            font-size: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ── TOP BAR ── */
        .topbar {
            background: #dc3545;
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .topbar-logo {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -.02em;
            flex: 1;
        }
        .topbar-sub { font-size: .8rem; opacity: .85; }

        /* ── CARD ── */
        .card {
            background: white;
            border-radius: 16px;
            margin: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }
        .card-header {
            background: #f2f2f7;
            padding: 12px 16px;
            font-size: .78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6c6c70;
        }

        /* ── PIN-SIDA ── */
        .pin-wrap {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 20px;
            background: #f2f2f7;
        }
        .pin-box {
            width: 100%;
            max-width: 380px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
            overflow: hidden;
        }
        .pin-box-header {
            background: #dc3545;
            color: white;
            text-align: center;
            padding: 32px 24px 24px;
        }
        .pin-box-header svg { margin-bottom: 12px; }
        .pin-box-header h1 { font-size: 1.4rem; font-weight: 700; }
        .pin-box-header p  { font-size: .9rem; opacity: .85; margin-top: 4px; }
        .pin-box-body { padding: 28px 24px; }
        .pin-input {
            width: 100%;
            font-size: 2.2rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 14px 8px;
            outline: none;
            transition: border-color .2s;
            -webkit-appearance: none;
        }
        .pin-input:focus { border-color: #dc3545; }
        .pin-hint { text-align: center; color: #6c6c70; font-size: .85rem; margin-bottom: 20px; }
        .pin-error {
            background: #fff0f0;
            color: #dc3545;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            text-align: center;
            font-size: .9rem;
        }
        .btn-red {
            display: block;
            width: 100%;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 15px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            -webkit-appearance: none;
        }
        .btn-red:active { background: #b02a37; }
        .pin-regnr {
            text-align: center;
            margin-top: 20px;
            color: #6c6c70;
            font-size: .85rem;
        }
        .regnr-pill {
            display: inline-block;
            background: #f2f2f7;
            border: 1.5px solid #d1d1d6;
            border-radius: 6px;
            padding: 3px 12px;
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: .06em;
            color: #1c1c1e;
        }

        /* ── ORDER-SIDA ── */
        .page { padding-bottom: 40px; }

        /* Statusbanner */
        .status-banner {
            margin: 16px;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .status-banner .status-icon {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: rgba(255,255,255,.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
        }
        .status-banner .status-title { font-weight: 700; font-size: 1.05rem; }
        .status-banner .status-sub   { font-size: .85rem; opacity: .85; }

        /* Info-rader */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 13px 16px;
            border-bottom: 1px solid #f2f2f7;
            gap: 12px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #6c6c70; font-size: .9rem; flex-shrink: 0; }
        .info-value { font-weight: 500; text-align: right; font-size: .95rem; }

        /* Specrad (mobil-first: en rad per artikel) */
        .spec-item {
            padding: 14px 16px;
            border-bottom: 1px solid #f2f2f7;
        }
        .spec-item:last-child { border-bottom: none; }
        .spec-desc { font-weight: 500; margin-bottom: 4px; }
        .spec-meta { font-size: .82rem; color: #6c6c70; }
        .spec-summa { font-weight: 700; color: #1c1c1e; margin-top: 4px; }

        /* Totalt */
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #1c1c1e;
            color: white;
            border-radius: 0 0 16px 16px;
        }
        .total-label { font-weight: 600; }
        .total-belopp { font-size: 1.4rem; font-weight: 700; color: #ff453a; }

        /* Kontakt-kort */
        .kontakt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            background: #f2f2f7;
        }
        .kontakt-item {
            background: white;
            padding: 14px 16px;
        }
        .kontakt-item:first-child { border-radius: 0; }
        .kontakt-icon { font-size: 1.2rem; margin-bottom: 4px; }
        .kontakt-label { font-size: .78rem; color: #6c6c70; text-transform: uppercase; letter-spacing: .04em; }
        .kontakt-val { font-weight: 600; font-size: .9rem; margin-top: 2px; }
        .kontakt-val a { color: #dc3545; text-decoration: none; }

        /* Felmeddelande */
        .fel-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f2f2f7;
        }
        .fel-box {
            max-width: 340px;
            text-align: center;
        }
        .fel-icon { font-size: 3rem; color: #dc3545; margin-bottom: 16px; }
        .fel-box h2 { font-size: 1.2rem; margin-bottom: 8px; }
        .fel-box p  { color: #6c6c70; font-size: .9rem; }

        /* Footer */
        .order-footer {
            text-align: center;
            padding: 20px 16px;
            font-size: .8rem;
            color: #aeaeb2;
        }
    </style>
</head>
<body>

<?php if ($fel): ?>
<!-- ═══════════════════════════════════════ -->
<!--  FELMEDDELANDE                         -->
<!-- ═══════════════════════════════════════ -->
<div class="fel-wrap">
    <div class="fel-box">
        <div class="fel-icon">&#9888;</div>
        <h2>Ogiltig länk</h2>
        <p><?php echo esc($fel); ?></p>
        <p style="margin-top:16px;">
            Kontakta oss:<br>
            <a href="tel:<?php echo preg_replace('/[^0-9+]/','',$foretagstel); ?>" style="color:#dc3545;font-weight:600;">
                <?php echo esc($foretagstel); ?>
            </a>
        </p>
    </div>
</div>

<?php elseif (!$visaOrder): ?>
<!-- ═══════════════════════════════════════ -->
<!--  PIN-FORMULÄR                          -->
<!-- ═══════════════════════════════════════ -->
<div class="pin-wrap">
    <div class="pin-box">
        <div class="pin-box-header">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <h1><?php echo esc($foretagsnamn); ?></h1>
            <p>Ange PIN-koden från SMS:et för att se din arbetsorder</p>
        </div>
        <div class="pin-box-body">
            <?php if ($pinFel): ?>
            <div class="pin-error">&#10005; Fel PIN-kod — försök igen</div>
            <?php endif; ?>

            <p class="pin-hint">Du fick en 4-siffrig kod i samma SMS som denna länk.</p>

            <form method="POST" autocomplete="off">
                <input type="text"
                       name="pin"
                       class="pin-input"
                       maxlength="4"
                       inputmode="numeric"
                       pattern="[0-9]{4}"
                       placeholder="• • • •"
                       autofocus
                       autocomplete="one-time-code"
                       required>

                <button type="submit" class="btn-red">Öppna arbetsorder</button>
            </form>

            <div class="pin-regnr">
                Fordon: <span class="regnr-pill"><?php echo esc($projekt['regnummer']); ?></span>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════ -->
<!--  ARBETSORDER                           -->
<!-- ═══════════════════════════════════════ -->
<div class="page">
    <!-- Topbar -->
    <div class="topbar">
        <div>
            <div class="topbar-logo"><?php echo esc($foretagsnamn); ?></div>
            <div class="topbar-sub">Arbetsorder #<?php echo $projekt['id']; ?></div>
        </div>
        <span class="regnr-pill" style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.3);color:white;">
            <?php echo esc($projekt['regnummer']); ?>
        </span>
    </div>

    <!-- Statusbanner -->
    <?php
    $sBg   = statusFarg($projekt['status']);
    $sText = statusText($projekt['status']);
    $sIcon = $projekt['status'] === 'avslutad' ? '✓' : ($projekt['status'] === 'pågående' ? '⚙' : '→');
    ?>
    <div class="status-banner" style="background:<?php echo $sBg; ?>; color:white;">
        <div class="status-icon"><?php echo $sIcon; ?></div>
        <div>
            <div class="status-title"><?php echo $sText; ?></div>
            <div class="status-sub">
                <?php if (!empty($projekt['betald'])): ?>
                    Betald &#10003;
                <?php else: ?>
                    Ej betald
                <?php endif; ?>
                &nbsp;·&nbsp; Order <?php echo date('Y-m-d', strtotime($projekt['createdDate'])); ?>
            </div>
        </div>
    </div>

    <!-- Fordon & Kund -->
    <div class="card">
        <div class="card-header">Fordon &amp; Kund</div>
        <div class="info-row">
            <span class="info-label">Registreringsnummer</span>
            <span class="info-value"><span class="regnr-pill"><?php echo esc($projekt['regnummer']); ?></span></span>
        </div>
        <div class="info-row">
            <span class="info-label">Kontaktperson</span>
            <span class="info-value"><?php echo esc($projekt['kontakt_person_namn']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Telefon</span>
            <span class="info-value">
                <a href="tel:<?php echo preg_replace('/[^0-9+]/','',$projekt['kontakt_person_telefon']); ?>" style="color:#dc3545;">
                    <?php echo esc($projekt['kontakt_person_telefon']); ?>
                </a>
            </span>
        </div>
    </div>

    <!-- Uppdrag -->
    <div class="card">
        <div class="card-header">Uppdrag</div>
        <div class="info-row">
            <span class="info-label">Rubrik</span>
            <span class="info-value"><?php echo esc($projekt['rubrik']); ?></span>
        </div>
        <?php if (!empty($projekt['beskrivning'])): ?>
        <div class="info-row" style="flex-direction:column;gap:4px;">
            <span class="info-label">Beskrivning</span>
            <span style="font-size:.9rem;"><?php echo nl2br(esc($projekt['beskrivning'])); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($projekt['felsokning'])): ?>
        <div class="info-row" style="flex-direction:column;gap:4px;">
            <span class="info-label">Felsökning</span>
            <span style="font-size:.9rem;"><?php echo nl2br(esc($projekt['felsokning'])); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($projekt['atgard'])): ?>
        <div class="info-row" style="flex-direction:column;gap:4px;">
            <span class="info-label">Åtgärd</span>
            <span style="font-size:.9rem;"><?php echo nl2br(esc($projekt['atgard'])); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($projekt['pris'])): ?>
        <div class="info-row">
            <span class="info-label">Pris</span>
            <span class="info-value"><?php echo number_format((float)$projekt['pris'], 0, ',', ' '); ?> kr</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($projekt['planDate'])): ?>
        <div class="info-row">
            <span class="info-label">Planerat datum</span>
            <span class="info-value"><?php echo date('Y-m-d', strtotime($projekt['planDate'])); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Specifikation -->
    <?php if (!empty($rader)): ?>
    <?php
    $radTotal = 0;
    foreach ($rader as $rad) {
        $radTotal += ((float)$rad['pris'] * (float)$rad['antal']) - (float)$rad['rabatt'];
    }
    ?>
    <div class="card" style="border-radius:16px;overflow:hidden;">
        <div class="card-header">Specifikation</div>
        <?php foreach ($rader as $rad):
            $summa = ((float)$rad['pris'] * (float)$rad['antal']) - (float)$rad['rabatt'];
            $antalFmt = rtrim(rtrim(number_format((float)$rad['antal'], 2, ',', ''), '0'), ',');
            $enhet = $rad['typ'] === 'arbete' ? 'tim' : 'st';
        ?>
        <div class="spec-item">
            <div class="spec-desc"><?php echo esc($rad['beskrivning']); ?></div>
            <div class="spec-meta">
                <?php echo number_format($rad['pris'], 0, ',', ' '); ?> kr
                × <?php echo $antalFmt; ?> <?php echo $enhet; ?>
                <?php if ($rad['rabatt'] > 0): ?>
                    &nbsp;– rabatt <?php echo number_format($rad['rabatt'], 0, ',', ' '); ?> kr
                <?php endif; ?>
            </div>
            <div class="spec-summa"><?php echo number_format($summa, 0, ',', ' '); ?> kr</div>
        </div>
        <?php endforeach; ?>
        <div class="total-row">
            <span class="total-label">Totalt att betala</span>
            <span class="total-belopp"><?php echo number_format($radTotal, 0, ',', ' '); ?> kr</span>
        </div>
    </div>
    <?php elseif (!empty($projekt['pris'])): ?>
    <div class="card">
        <div class="total-row" style="border-radius:16px;">
            <span class="total-label">Totalt att betala</span>
            <span class="total-belopp"><?php echo number_format((float)$projekt['pris'], 0, ',', ' '); ?> kr</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Kontakt -->
    <div class="card">
        <div class="card-header">Kontakta oss</div>
        <div class="kontakt-grid">
            <div class="kontakt-item">
                <div class="kontakt-icon">📞</div>
                <div class="kontakt-label">Telefon</div>
                <div class="kontakt-val">
                    <a href="tel:<?php echo preg_replace('/[^0-9+]/','',$foretagstel); ?>">
                        <?php echo esc($foretagstel); ?>
                    </a>
                </div>
            </div>
            <div class="kontakt-item">
                <div class="kontakt-icon">✉️</div>
                <div class="kontakt-label">E-post</div>
                <div class="kontakt-val">
                    <a href="mailto:<?php echo esc($foretagsmail); ?>">
                        <?php echo esc($foretagsmail); ?>
                    </a>
                </div>
            </div>
            <div class="kontakt-item" style="grid-column:1/-1;">
                <div class="kontakt-icon">📍</div>
                <div class="kontakt-label">Adress</div>
                <div class="kontakt-val">
                    <a href="https://maps.google.com/?q=<?php echo urlencode($foretagsadr); ?>" target="_blank" style="color:#dc3545;">
                        <?php echo esc($foretagsadr); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="order-footer">
        <?php echo esc($foretagsnamn); ?> · Org.nr <?php echo esc($orgnr); ?><br>
        Arbetsorder #<?php echo $projekt['id']; ?> · <?php echo date('Y-m-d'); ?>
    </div>
</div>

<?php endif; ?>

</body>
</html>
