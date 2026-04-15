<?php
require_once '../includes/config.php';
kravInloggning();

// Skriv direkt till skärmen
function ut(string $rad, string $typ = 'info'): void {
    $farger = ['ok' => '#28a745', 'fel' => '#dc3545', 'info' => '#6c757d', 'rubrik' => '#212529'];
    $farg = $farger[$typ] ?? '#6c757d';
    echo '<div style="font-family:monospace;font-size:13px;padding:3px 8px;color:' . $farg . '">' . htmlspecialchars($rad) . '</div>';
    ob_flush(); flush();
}

?><!DOCTYPE html>
<html lang="sv">
<head><meta charset="UTF-8"><title>SMS-test</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4 bg-light">
<div class="card" style="max-width:700px">
    <div class="card-header bg-dark text-white"><strong>SMS-diagnostik</strong></div>
    <div class="card-body p-0">
        <div style="background:#1e1e1e;color:#d4d4d4;padding:16px;min-height:300px;font-family:monospace;font-size:13px;">
<?php
ob_start();

// ── STEG 1: Konfiguration ──────────────────────────────
ut('── STEG 1: Konfiguration ─────────────────────', 'rubrik');
ut('SMS_ENABLED   : ' . (SMS_ENABLED ? 'TRUE ✓' : 'FALSE ✗ — SMS är avstängt!'), SMS_ENABLED ? 'ok' : 'fel');
ut('SMS_API_USER  : ' . SMS_API_USER);
ut('SMS_API_PASS  : ' . str_repeat('*', strlen(SMS_API_PASS)));
ut('SMS_FROM      : ' . SMS_FROM);
ut('KONTAKT_TEL   : ' . KONTAKT_TELEFON);
ut('SITE_URL      : ' . SITE_URL);
ut('');

// ── STEG 2: PHP-version ────────────────────────────────
ut('── STEG 2: PHP-miljö ─────────────────────────', 'rubrik');
ut('PHP-version   : ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'fel');
ut('cURL             : ' . (function_exists('curl_init') ? 'tillgängligt ✓' : 'SAKNAS ✗'), function_exists('curl_init') ? 'ok' : 'fel');
ut('mbstring         : ' . (function_exists('mb_strlen') ? 'tillgängligt ✓' : 'SAKNAS ✗'), function_exists('mb_strlen') ? 'ok' : 'fel');
ut('allow_url_fopen  : ' . (ini_get('allow_url_fopen') ? 'ON ✓' : 'OFF ✗ — file_get_contents kan ej göra HTTP-anrop!'), ini_get('allow_url_fopen') ? 'ok' : 'fel');
ut('');

// ── STEG 3: Databas — kontrollera kolumner ─────────────
ut('── STEG 3: Databaskolumner ───────────────────', 'rubrik');
try {
    $cols = $pdo->query("SHOW COLUMNS FROM stat_projekt")->fetchAll(PDO::FETCH_COLUMN);
    $kravda = ['sms_skickat', 'sms_bokning_datum', 'publik_token', 'publik_pin_hash', 'publik_utgangsdatum'];
    foreach ($kravda as $kol) {
        $finns = in_array($kol, $cols);
        ut('stat_projekt.' . $kol . ' : ' . ($finns ? 'finns ✓' : 'SAKNAS ✗ — kör migration.sql!'), $finns ? 'ok' : 'fel');
    }
} catch (Exception $e) {
    ut('DB-fel: ' . $e->getMessage(), 'fel');
}
ut('');

// ── STEG 4: Normalisera telefonnummer ──────────────────
ut('── STEG 4: Normalisering av telefonnummer ────', 'rubrik');
require_once '../includes/sms.php';
$testNummer = '0730730009';
$normaliserat = normaliseTelefon($testNummer);
ut("normaliseTelefon('$testNummer') → " . ($normaliserat ?: 'false — misslyckades!'), $normaliserat ? 'ok' : 'fel');
ut('');

// ── STEG 5: Skicka test-SMS (rå cURL, visar allt) ─────
ut('── STEG 5: Skicka test-SMS ───────────────────', 'rubrik');

$tillNummer = $_GET['till'] ?? '';
if ($tillNummer) {
    $normTill = normaliseTelefon($tillNummer);
    ut("Råformat: $tillNummer → E.164: " . ($normTill ?: 'MISSLYCKADES'), $normTill ? 'ok' : 'fel');

    if ($normTill) {
        $payload = [
            'from'    => SMS_FROM,
            'to'      => $normTill,
            'message' => 'Test fran GS Motors. Fungerar SMS? // GS Motors',
        ];
        ut('Payload: ' . json_encode($payload));
        ut('Anropar https://api.46elks.com/a1/sms ...');

        // Försök 1: file_get_contents
        ut('--- Metod 1: file_get_contents ---');
        $urlFopenOn = ini_get('allow_url_fopen');
        ut('allow_url_fopen: ' . ($urlFopenOn ? 'ON' : 'OFF'), $urlFopenOn ? 'ok' : 'fel');

        if ($urlFopenOn) {
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => 'Authorization: Basic ' . base64_encode(SMS_API_USER . ':' . SMS_API_PASS) . "\r\n"
                               . "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($payload, '', '&'),
                    'timeout' => 10,
                ]
            ]);
            $response = @file_get_contents('https://api.46elks.com/a1/sms', false, $context);
            $header   = isset($http_response_header[0]) ? $http_response_header[0] : '(inget svar)';
            ut("HTTP: $header", strpos($header,'200') !== false ? 'ok' : 'fel');
            ut("Body: " . ($response ?: '(tomt)'), strpos($header,'200') !== false ? 'ok' : 'fel');
        } else {
            ut('Hoppar över — allow_url_fopen är OFF', 'fel');
        }

        // Försök 2: cURL
        ut('--- Metod 2: cURL ---');
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.46elks.com/a1/sms');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_USERPWD        => SMS_API_USER . ':' . SMS_API_PASS,
                CURLOPT_POSTFIELDS     => http_build_query($payload, '', '&'),
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $curlResp  = curl_exec($ch);
            $httpKod   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr   = curl_error($ch);
            curl_close($ch);
            if ($curlErr) {
                ut("cURL-fel: $curlErr", 'fel');
            } else {
                ut("HTTP-kod: $httpKod", ($httpKod >= 200 && $httpKod < 300) ? 'ok' : 'fel');
                ut("Body: " . ($curlResp ?: '(tomt)'), ($httpKod >= 200 && $httpKod < 300) ? 'ok' : 'fel');
            }
        } else {
            ut('cURL saknas', 'fel');
        }
    }
} else {
    ut('Inget testnummer angivet — se formulär nedan', 'info');
}
ut('');

// ── STEG 6: Senaste projekt med planDate ──────────────
ut('── STEG 6: Senaste projekt med planDate ──────', 'rubrik');
try {
    $stmt = $pdo->query("SELECT id, regnummer, kontakt_person_namn, kontakt_person_telefon, planDate, status, betald, sms_skickat, sms_bokning_datum FROM stat_projekt WHERE planDate IS NOT NULL ORDER BY id DESC LIMIT 3");
    $projekt = $stmt->fetchAll();
    if (empty($projekt)) {
        ut('Inga projekt med planDate hittades', 'info');
    }
    foreach ($projekt as $p) {
        ut("#{$p['id']} {$p['regnummer']} — telefon: {$p['kontakt_person_telefon']} — planDate: {$p['planDate']} — sms_bokning_datum: " . ($p['sms_bokning_datum'] ?: 'NULL') . " — status: {$p['status']} betald: {$p['betald']} sms_skickat: {$p['sms_skickat']}");
    }
} catch (Exception $e) {
    ut('DB-fel: ' . $e->getMessage(), 'fel');
}

echo ob_get_clean();
?>
        </div>
    </div>
    <div class="card-footer bg-light">
        <form method="GET" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">Skicka test-SMS till nummer (07XXXXXXXX)</label>
                <input type="tel" name="till" class="form-control form-control-sm"
                       pattern="07[0-9]{8}" placeholder="0730000000"
                       value="<?php echo htmlspecialchars($_GET['till'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn btn-sm btn-danger">Skicka test-SMS</button>
            <a href="dashboard.php" class="btn btn-sm btn-secondary">Tillbaka</a>
        </form>
    </div>
</div>
</body>
</html>
