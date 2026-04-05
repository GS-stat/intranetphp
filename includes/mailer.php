<?php
/**
 * mailer.php – Skicka e-post via PHP mail()
 *
 * Kräver att webbservern har en fungerande mail-agent (sendmail/postfix).
 * På MissHosting fungerar detta direkt.
 */

/**
 * Skicka e-postbekräftelse vid skapad arbetsorder
 *
 * @param string $till        Mottagarens e-postadress
 * @param array  $projekt     Projektdata (rubrik, regnummer, planDate, starttid, sluttid, kontakt_person_namn, id)
 * @return bool
 */
function skickaOrderBekraftelse(string $till, array $projekt): bool {
    if (empty($till)) return false;

    $frånNamn    = MAIL_FROM_NAME;
    $frånAdress  = MAIL_FROM;
    $planDatum   = !empty($projekt['planDate'])
        ? date('Y-m-d', strtotime($projekt['planDate']))
        : 'Ej bokat ännu';
    $planTid     = (!empty($projekt['starttid']) && !empty($projekt['sluttid']))
        ? date('H:i', strtotime($projekt['starttid'])) . '–' . date('H:i', strtotime($projekt['sluttid']))
        : 'Ej angiven';

    $amne = '=?UTF-8?B?' . base64_encode('Arbetsorder bekräftad – ' . $projekt['regnummer'] . ' | GS Motors') . '?=';

    $meddelande = "Hej " . $projekt['kontakt_person_namn'] . ",\r\n\r\n"
        . "Vi har tagit emot din arbetsorder. Här är en sammanfattning:\r\n\r\n"
        . "────────────────────────────────\r\n"
        . "Regnummer:     " . $projekt['regnummer'] . "\r\n"
        . "Rubrik:        " . $projekt['rubrik'] . "\r\n"
        . "Planerat datum: " . $planDatum . "\r\n"
        . "Tid:           " . $planTid . "\r\n"
        . "Ordernr:       #" . $projekt['id'] . "\r\n"
        . "────────────────────────────────\r\n\r\n"
        . "Vi hör av oss om det uppstår frågor.\r\n\r\n"
        . "Med vänliga hälsningar,\r\n"
        . $frånNamn . "\r\n"
        . "Tel: 0730730009\r\n"
        . "E-post: " . $frånAdress . "\r\n";

    $headers  = "From: =?UTF-8?B?" . base64_encode($frånNamn) . "?= <" . $frånAdress . ">\r\n";
    $headers .= "Reply-To: " . $frånAdress . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";

    return mail(
        $till,
        $amne,
        base64_encode($meddelande),
        $headers
    );
}
