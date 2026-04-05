<?php
/**
 * sms.php – Skicka SMS via 46elks REST API
 *
 * Kräver att SMS_ENABLED = true och giltiga API-nycklar i config.php.
 * Dokumentation: https://46elks.com/docs/send-sms
 */

/**
 * Skicka ett enskilt SMS
 *
 * @param string $till       Mottagarens telefonnummer i E.164-format, t.ex. +46730730009
 * @param string $meddelande SMS-text (max 160 tecken för ett SMS, mer ger MMS-prissättning)
 * @return bool
 */
function skickaSms(string $till, string $meddelande): bool {
    if (!SMS_ENABLED) return false;
    if (empty($till))  return false;

    // Normalisera svenska nummer till E.164
    $till = normaliseTelefon($till);
    if (!$till) return false;

    $ch = curl_init('https://api.46elks.com/a1/sms');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => SMS_API_USER . ':' . SMS_API_PASS,
        CURLOPT_POSTFIELDS     => http_build_query([
            'from'    => SMS_FROM,
            'to'      => $till,
            'message' => $meddelande,
        ]),
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpKod  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (curl_errno($ch)) {
    error_log('SMS cURL error: ' . curl_error($ch));
    return false;
    }

    if ($httpKod !== 200 && $httpKod !== 201) {
        error_log('SMS API error: HTTP ' . $httpKod . ' Response: ' . $response);
        return false;
    }

    return true;

}

/**
 * Skicka SMS med publik arbetsorder-länk när projekt är avslutad + betald
 *
 * @param PDO $pdo
 * @param int $projekt_id
 * @return bool   true = SMS skickades (eller redan skickat), false = misslyckades / SMS inaktiverat
 */
function skickaSmsKvittens($pdo, int $projekt_id): bool {
    try {
        // Hämta projektet
        $stmt = $pdo->prepare("
            SELECT id, regnummer, kontakt_person_telefon,
                   status, betald, sms_skickat
            FROM stat_projekt
            WHERE id = ?
        ");
        $stmt->execute([$projekt_id]);
        $p = $stmt->fetch();

        if (!$p) return false;

        // Villkor: avslutad + betald + ej redan skickat
        if ($p['status'] !== 'avslutad' || !$p['betald'] || $p['sms_skickat']) return false;

        // Generera token + PIN
        $token = genereraPublikToken($pdo, $projekt_id);
        $url   = SITE_URL . '/order.php?t=' . $token['token'];

        $text = "Hej! Din bil " . $p['regnummer']
            . " är klar och betald. "
            . "Se din arbetsorder: " . $url
            . " — Kod: " . $token['pin']
            . " // GS Motors";

        $resultat = skickaSms($p['kontakt_person_telefon'], $text);

        if ($resultat) {
            // Markera att SMS är skickat
            $pdo->prepare("UPDATE stat_projekt SET sms_skickat = 1 WHERE id = ?")
                ->execute([$projekt_id]);
        }

        return $resultat;
    } catch (PDOException $e) {
        // SMS-kolumner saknas (migration ej körd) – ignorera, fortsätt spara
        error_log('skickaSmsKvittens fel: ' . $e->getMessage());
        return false;
    }
}

/**
 * Normalisera telefonnummer till E.164 (+46...)
 * Hanterar: 07XXXXXXXX, 0046XXXXXXXX, +46XXXXXXXX
 */
function normaliseTelefon(string $nr): string|false {
    $nr = preg_replace('/[^0-9+]/', '', $nr);

    if (str_starts_with($nr, '+46'))   return $nr;
    if (str_starts_with($nr, '0046'))  return '+46' . substr($nr, 4);
    if (str_starts_with($nr, '0'))     return '+46' . substr($nr, 1);

    return false;
}
