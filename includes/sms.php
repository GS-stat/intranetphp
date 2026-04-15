<?php
/**
 * sms.php – Skicka SMS via 46elks REST API
 *
 * Kräver att SMS_ENABLED = true och giltiga API-nycklar i config.php.
 * Dokumentation: https://46elks.com/docs/send-sms
 */

function smsLog(string $meddelande): void {
    error_log('[SMS] ' . $meddelande);
}

/**
 * Skicka ett enskilt SMS
 */
function skickaSms(string $till, string $meddelande): bool {
    smsLog("skickaSms() anropad — till råformat: '$till'");

    if (!SMS_ENABLED) {
        smsLog("AVBROTT: SMS_ENABLED = false — inget SMS skickas");
        return false;
    }

    if (empty($till)) {
        smsLog("AVBROTT: tomt telefonnummer");
        return false;
    }

    // Normalisera nummer
    $normaliserat = normaliseTelefon($till);
    smsLog("normaliseTelefon('$till') → " . ($normaliserat ?: 'false (ogiltigt format)'));

    if (!$normaliserat) {
        smsLog("AVBROTT: kunde inte normalisera numret till E.164");
        return false;
    }

    $payload = [
        'from'    => SMS_FROM,
        'to'      => $normaliserat,
        'message' => $meddelande,
    ];
    smsLog("Payload till 46elks: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
    smsLog("Meddelandelängd: " . mb_strlen($meddelande) . " tecken");
    smsLog("API-användare: " . SMS_API_USER);

    $ch = curl_init('https://api.46elks.com/a1/sms');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => SMS_API_USER . ':' . SMS_API_PASS,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_TIMEOUT        => 10,
    ]);

    smsLog("Skickar cURL-anrop till 46elks...");
    $response = curl_exec($ch);
    $httpKod  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    smsLog("cURL klar — HTTP-kod: $httpKod");

    if ($curlErrno) {
        smsLog("AVBROTT: cURL-fel ($curlErrno): $curlError");
        return false;
    }

    smsLog("46elks svar: " . ($response ?: '(tomt)'));

    if ($httpKod !== 200 && $httpKod !== 201) {
        smsLog("AVBROTT: HTTP $httpKod — 46elks nekade anropet");
        return false;
    }

    smsLog("OK: SMS skickat till $normaliserat");
    return true;
}

/**
 * Skicka SMS-kvittens när projekt är avslutad + betald
 */
function skickaSmsKvittens($pdo, int $projekt_id): bool {
    smsLog("skickaSmsKvittens() anropad — projekt_id: $projekt_id");

    try {
        $stmt = $pdo->prepare("
            SELECT id, regnummer, kontakt_person_telefon,
                   status, betald, sms_skickat
            FROM stat_projekt
            WHERE id = ?
        ");
        $stmt->execute([$projekt_id]);
        $p = $stmt->fetch();

        if (!$p) {
            smsLog("AVBROTT: projekt $projekt_id hittades inte i databasen");
            return false;
        }

        smsLog("Projekt hämtat — regnr: {$p['regnummer']}, status: {$p['status']}, betald: {$p['betald']}, sms_skickat: {$p['sms_skickat']}, telefon: '{$p['kontakt_person_telefon']}'");

        if ($p['status'] !== 'avslutad') {
            smsLog("AVBROTT: status är '{$p['status']}' — måste vara 'avslutad'");
            return false;
        }
        if (!$p['betald']) {
            smsLog("AVBROTT: projektet är inte markerat som betalt");
            return false;
        }
        if ($p['sms_skickat']) {
            smsLog("AVBROTT: SMS redan skickat tidigare för detta projekt");
            return false;
        }

        smsLog("Alla villkor OK — genererar token...");
        $token = genereraPublikToken($pdo, $projekt_id);
        $url   = SITE_URL . '/order.php?t=' . $token['token'];
        smsLog("Token: {$token['token']}, PIN: {$token['pin']}, URL: $url");

        $text = "Hej! Din bil " . $p['regnummer']
            . " är klar och betald. "
            . "Se din arbetsorder: " . $url
            . " — Kod: " . $token['pin']
            . " // GS Motors";

        smsLog("SMS-text att skicka: '$text'");

        $resultat = skickaSms($p['kontakt_person_telefon'], $text);

        if ($resultat) {
            $pdo->prepare("UPDATE stat_projekt SET sms_skickat = 1 WHERE id = ?")
                ->execute([$projekt_id]);
            smsLog("sms_skickat = 1 sparat i databasen");
        } else {
            smsLog("skickaSms() returnerade false — sms_skickat uppdateras ej");
        }

        return $resultat;

    } catch (PDOException $e) {
        smsLog("PDOException: " . $e->getMessage());
        return false;
    }
}

/**
 * Skicka bokningsbekräftelse via SMS när planDate sätts eller ändras.
 * Skickas bara om planDate är nytt (skiljer sig från sms_bokning_datum i DB).
 */
function skickaSmsBokning($pdo, int $projekt_id, string $nyPlanDate, ?string $starttid = null): bool {
    smsLog("skickaSmsBokning() anropad — projekt_id: $projekt_id, nyPlanDate: $nyPlanDate");

    if (!SMS_ENABLED) {
        smsLog("AVBROTT: SMS_ENABLED = false");
        return false;
    }

    if (empty($nyPlanDate)) {
        smsLog("AVBROTT: inget planDate angivet");
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, regnummer, rubrik, kontakt_person_namn,
                   kontakt_person_telefon, sms_bokning_datum
            FROM stat_projekt WHERE id = ?
        ");
        $stmt->execute([$projekt_id]);
        $p = $stmt->fetch();

        if (!$p) {
            smsLog("AVBROTT: projekt $projekt_id hittades inte");
            return false;
        }

        smsLog("Projekt hämtat — regnr: {$p['regnummer']}, telefon: '{$p['kontakt_person_telefon']}', sms_bokning_datum: '{$p['sms_bokning_datum']}'");

        // Skicka bara om datumet är nytt eller ändrat
        if ($p['sms_bokning_datum'] === $nyPlanDate) {
            smsLog("AVBROTT: boknings-SMS redan skickat för datum $nyPlanDate");
            return false;
        }

        // Formatera datum på svenska: "måndag 21 april"
        $ts       = strtotime($nyPlanDate);
        $dagar    = ['Sunday'=>'söndag','Monday'=>'måndag','Tuesday'=>'tisdag',
                     'Wednesday'=>'onsdag','Thursday'=>'torsdag','Friday'=>'fredag','Saturday'=>'lördag'];
        $manader  = ['January'=>'januari','February'=>'februari','March'=>'mars',
                     'April'=>'april','May'=>'maj','June'=>'juni','July'=>'juli',
                     'August'=>'augusti','September'=>'september','October'=>'oktober',
                     'November'=>'november','December'=>'december'];
        $dagNamn  = $dagar[date('l', $ts)]  ?? date('l', $ts);
        $manNamn  = $manader[date('F', $ts)] ?? date('F', $ts);
        $datumStr = $dagNamn . ' ' . date('j', $ts) . ' ' . $manNamn;

        $tidStr = '';
        if (!empty($starttid)) {
            $tidStr = ' kl. ' . substr($starttid, 0, 5);
        }

        $text = "Hej {$p['kontakt_person_namn']}! "
              . "Din bil {$p['regnummer']} är inbokad hos GS Motors "
              . $datumStr . $tidStr . ". "
              . "Möjlighet att lämna i förtid finns. "
              . "Ring oss: " . KONTAKT_TELEFON
              . " // GS Motors";

        smsLog("Boknings-SMS text: '$text'");
        smsLog("Meddelandelängd: " . mb_strlen($text) . " tecken");

        $resultat = skickaSms($p['kontakt_person_telefon'], $text);

        if ($resultat) {
            $pdo->prepare("UPDATE stat_projekt SET sms_bokning_datum = ? WHERE id = ?")
                ->execute([$nyPlanDate, $projekt_id]);
            smsLog("sms_bokning_datum = '$nyPlanDate' sparat i databasen");
        } else {
            smsLog("skickaSms() returnerade false — sms_bokning_datum uppdateras ej");
        }

        return $resultat;

    } catch (PDOException $e) {
        smsLog("PDOException i skickaSmsBokning: " . $e->getMessage());
        return false;
    }
}

/**
 * Normalisera telefonnummer till E.164 (+46...)
 * Hanterar: 07XXXXXXXX, 0046XXXXXXXX, +46XXXXXXXX
 */
function normaliseTelefon(string $nr) {
    // Förväntat format: 07XXXXXXXX (10 siffror)
    $rensat = preg_replace('/[^0-9]/', '', $nr);
    smsLog("normaliseTelefon: '$nr' → rensat: '$rensat'");

    if (strlen($rensat) === 10 && substr($rensat, 0, 2) === '07') {
        $e164 = '+46' . substr($rensat, 1);
        smsLog("normaliseTelefon: OK → $e164");
        return $e164;
    }

    smsLog("normaliseTelefon: OGILTIGT format — förväntar 07XXXXXXXX, fick '$rensat'");
    return false;
}
