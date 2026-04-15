<?php
/**
 * Hämta status-badge med rätt färg
 */
function getStatusBadge($status) {
    switch($status) {
        case 'inkommen':
            return '<span class="badge bg-warning">Inkommen</span>';
        case 'pågående':
            return '<span class="badge bg-primary">Pågående</span>';
        case 'avslutad':
            return '<span class="badge bg-success">Avslutad</span>';
        default:
            return '<span class="badge bg-secondary">Okänd</span>';
    }
}

/**
 * Hämta kundTyp-badge med rätt ikon och färg
 */
function getKundTypBadge($kundTyp) {
    if ($kundTyp == 'Företag') {
        return '<span class="badge bg-info"><i class="fas fa-building"></i> Företag</span>';
    } elseif ($kundTyp == 'Privat') {
        return '<span class="badge bg-primary"><i class="fas fa-user"></i> Privat</span>';
    } elseif ($kundTyp == 'Försäkring') {
        return '<span class="badge bg-warning"><i class="fas fa-shield-alt"></i> Försäkring</span>';
    } else {
        return '<span class="badge bg-secondary"><i class="fas fa-question"></i> Okänd</span>';
    }
}

/**
 * Hämta alla projekt med statistik
 */
function getProjektStatistik($pdo) {
    $statistik = [];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM stat_projekt");
    $row = $stmt->fetch();
    $statistik['total'] = $row['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stat_projekt WHERE status = 'inkommen'");
    $row = $stmt->fetch();
    $statistik['inkommen'] = $row['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stat_projekt WHERE status = 'pågående'");
    $row = $stmt->fetch();
    $statistik['pågående'] = $row['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stat_projekt WHERE status = 'avslutad'");
    $row = $stmt->fetch();
    $statistik['avslutad'] = $row['count'];

    $stmt = $pdo->query("SELECT SUM(pris) as total FROM stat_projekt WHERE betald = 1");
    $row = $stmt->fetch();
    $statistik['intäkt'] = $row['total'] ? $row['total'] : 0;

    $stmt = $pdo->query("
        SELECT kundTyp, COUNT(*) as antal
        FROM stat_projekt
        WHERE kundTyp IS NOT NULL
        GROUP BY kundTyp
    ");
    $statistik['per_kundtyp'] = $stmt->fetchAll();

    return $statistik;
}

/**
 * Hämta statistik för prisintervall
 */
function getPrisIntervallStatistik($pdo) {
    $statistik = [
        'under_5000' => 0,
        'mellan_5000_25000' => 0,
        'over_25000' => 0,
        'summa_under_5000' => 0,
        'summa_mellan_5000_25000' => 0,
        'summa_over_25000' => 0
    ];

    $stmt = $pdo->query("
        SELECT pris, status
        FROM stat_projekt
        WHERE pris IS NOT NULL AND pris > 0
    ");

    $projekt = $stmt->fetchAll();

    foreach ($projekt as $p) {
        $pris = floatval($p['pris']);

        if ($pris < 5000) {
            $statistik['under_5000']++;
            $statistik['summa_under_5000'] += $pris;
        } elseif ($pris >= 5000 && $pris <= 25000) {
            $statistik['mellan_5000_25000']++;
            $statistik['summa_mellan_5000_25000'] += $pris;
        } else {
            $statistik['over_25000']++;
            $statistik['summa_over_25000'] += $pris;
        }
    }

    return $statistik;
}

/**
 * Hämta statistik per kundTyp
 */
function getKundTypStatistik($pdo) {
    $stmt = $pdo->query("
        SELECT
            kundTyp,
            COUNT(*) as antal_projekt,
            SUM(CASE WHEN status = 'avslutad' THEN 1 ELSE 0 END) as avslutade,
            SUM(pris) as total_intakt,
            AVG(pris) as snitt_pris
        FROM stat_projekt
        WHERE kundTyp IS NOT NULL
        GROUP BY kundTyp
        ORDER BY antal_projekt DESC
    ");

    return $stmt->fetchAll();
}

/**
 * Hämta månadsstatistik för innevarande år
 */
function getManadsStatistik($pdo) {
    $stmt = $pdo->query("
        SELECT
            MONTH(createdDate) as manad,
            COUNT(*) as antal_projekt,
            SUM(CASE WHEN status = 'avslutad' THEN 1 ELSE 0 END) as avslutade,
            SUM(pris) as intakter
        FROM stat_projekt
        WHERE YEAR(createdDate) = YEAR(CURDATE())
        GROUP BY MONTH(createdDate)
        ORDER BY manad
    ");

    return $stmt->fetchAll();
}

/**
 * Hämta alla projekt (för lista) – utan paginering
 */
function hamtaAllaProjekt($pdo) {
    $stmt = $pdo->query("
        SELECT p.*, a.anvandarnamn as skapad_av_namn
        FROM stat_projekt p
        LEFT JOIN stat_anvandare a ON p.skapad_av = a.id
        ORDER BY
            CASE p.status
                WHEN 'pågående' THEN 1
                WHEN 'inkommen' THEN 2
                WHEN 'avslutad' THEN 3
            END,
            p.planDate ASC,
            p.createdDate DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Hämta filtrerade och paginerade projekt
 *
 * @param PDO   $pdo
 * @param array $filter  ['sok' => '', 'status' => '', 'betald' => '']
 * @param int   $sida    Aktuell sida (1-baserad)
 * @param int   $perSida Rader per sida
 * @return array ['projekt', 'total', 'sida', 'perSida', 'sidor']
 */
function hamtaFiltereradeProjekt($pdo, array $filter = [], int $sida = 1, int $perSida = 25): array {
    $where  = [];
    $params = [];

    if (!empty($filter['sok'])) {
        $where[] = "(p.regnummer LIKE :sok1
                    OR p.rubrik LIKE :sok2
                    OR p.kontakt_person_namn LIKE :sok3
                    OR p.kontakt_person_telefon LIKE :sok4)";
        $sokVal = '%' . $filter['sok'] . '%';
        $params[':sok1'] = $sokVal;
        $params[':sok2'] = $sokVal;
        $params[':sok3'] = $sokVal;
        $params[':sok4'] = $sokVal;
    }

    if (!empty($filter['status'])) {
        $where[] = "p.status = :status";
        $params[':status'] = $filter['status'];
    }

    if ($filter['betald'] !== '') {
        $where[] = "p.betald = :betald";
        $params[':betald'] = (int)$filter['betald'];
    }

    $whereStr = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Totalt antal matchande rader
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM stat_projekt p $whereStr"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sidor   = max(1, (int)ceil($total / $perSida));
    $sida    = max(1, min($sida, $sidor));
    $offset  = ($sida - 1) * $perSida;

    $sql = "SELECT p.*, a.anvandarnamn as skapad_av_namn
            FROM stat_projekt p
            LEFT JOIN stat_anvandare a ON p.skapad_av = a.id
            $whereStr
            ORDER BY
                CASE p.status
                    WHEN 'inkommen' THEN 2
                    WHEN 'avslutad' THEN 3
                    ELSE 1
                END,
                p.planDate ASC,
                p.createdDate DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit',  $perSida, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmt->execute();

    return [
        'projekt' => $stmt->fetchAll(),
        'total'   => $total,
        'sida'    => $sida,
        'perSida' => $perSida,
        'sidor'   => $sidor,
    ];
}

/**
 * Hämta ett specifikt projekt
 */
function hamtaProjekt($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT p.*, a.anvandarnamn as skapad_av_namn
        FROM stat_projekt p
        LEFT JOIN stat_anvandare a ON p.skapad_av = a.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Skapa nytt projekt
 * Returnerar ID för det skapade projektet, eller false vid fel.
 */
function skapaProjekt($pdo, $data) {
    $sql = "INSERT INTO stat_projekt (
        regnummer,
        rubrik,
        beskrivning,
        felsokning,
        atgard,
        cmt,
        skapad_av,
        planDate,
        starttid,
        sluttid,
        pris,
        betald,
        dackforvaring,
        dackforvaring_id,
        kontakt_person_namn,
        kontakt_person_telefon,
        kontakt_person_email,
        kundTyp,
        status,
        ansvarig_tekniker,
        flagga
    ) VALUES (
        :regnummer,
        :rubrik,
        :beskrivning,
        :felsokning,
        :atgard,
        :cmt,
        :skapad_av,
        :planDate,
        :starttid,
        :sluttid,
        :pris,
        :betald,
        :dackforvaring,
        :dackforvaring_id,
        :kontakt_person_namn,
        :kontakt_person_telefon,
        :kontakt_person_email,
        :kundTyp,
        :status,
        :ansvarig_tekniker,
        :flagga
    )";

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($data as $key => $val) {
            if ($val === null) {
                $stmt->bindValue(':' . $key, null, PDO::PARAM_NULL);
            } elseif (is_int($val) || is_bool($val)) {
                $stmt->bindValue(':' . $key, (int)$val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $key, $val, PDO::PARAM_STR);
            }
        }
        if ($stmt->execute()) {
            return (int)$pdo->lastInsertId();
        }
        return false;
    } catch (PDOException $e) {
        error_log('skapaProjekt fel: ' . $e->getMessage());
        return false;
    }
}

/**
 * Uppdatera projekt
 */
function uppdateraProjekt($pdo, $id, $data) {
    $data['id'] = $id;

    if (isset($data['status']) && $data['status'] == 'avslutad') {
        $data['avslutad'] = 1;
        $data['avslutadDatum'] = date('Y-m-d H:i:s');
    } else {
        $data['avslutad'] = 0;
        $data['avslutadDatum'] = null;
    }

    $sql = "UPDATE stat_projekt SET
        regnummer = :regnummer,
        rubrik = :rubrik,
        beskrivning = :beskrivning,
        felsokning = :felsokning,
        atgard = :atgard,
        cmt = :cmt,
        planDate = :planDate,
        starttid = :starttid,
        sluttid = :sluttid,
        pris = :pris,
        betald = :betald,
        dackforvaring = :dackforvaring,
        dackforvaring_id = :dackforvaring_id,
        kontakt_person_namn = :kontakt_person_namn,
        kontakt_person_telefon = :kontakt_person_telefon,
        kontakt_person_email = :kontakt_person_email,
        kundTyp = :kundTyp,
        status = :status,
        ansvarig_tekniker = :ansvarig_tekniker,
        flagga = :flagga,
        avslutad = :avslutad,
        avslutadDatum = :avslutadDatum
        WHERE id = :id";

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($data as $key => $val) {
            if ($val === null) {
                $stmt->bindValue(':' . $key, null, PDO::PARAM_NULL);
            } elseif (is_int($val) || is_bool($val)) {
                $stmt->bindValue(':' . $key, (int)$val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $key, $val, PDO::PARAM_STR);
            }
        }
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log('uppdateraProjekt fel: ' . $e->getMessage());
        return false;
    }
}

/**
 * Radera projekt
 */
function raderaProjekt($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM stat_projekt WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Hämta antal planerade jobb för ett specifikt datum
 */
function hamtaPlaneradeJobbForDatum($pdo, $datum) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as antal
        FROM stat_projekt
        WHERE planDate = ?
        AND status IN ('inkommen', 'pågående')
    ");
    $stmt->execute([$datum]);
    $row = $stmt->fetch();
    return $row['antal'];
}

/**
 * Hämta alla bokningar för en specifik vecka
 */
function hamtaVeckansBokningar($pdo, $veckaStart) {
    $veckaSlut = date('Y-m-d', strtotime($veckaStart . ' +6 days'));

    $stmt = $pdo->prepare("
        SELECT
            id,
            regnummer,
            rubrik,
            planDate,
            starttid,
            sluttid,
            status,
            kontakt_person_namn
        FROM stat_projekt
        WHERE planDate BETWEEN :start AND :slut
        AND planDate IS NOT NULL
        AND status IN ('inkommen', 'pågående')
        ORDER BY planDate ASC, starttid ASC
    ");

    $stmt->execute([
        ':start' => $veckaStart,
        ':slut' => $veckaSlut
    ]);

    $bokningar = $stmt->fetchAll();

    $veckansDagar = [];
    for ($i = 0; $i < 7; $i++) {
        $datum = date('Y-m-d', strtotime($veckaStart . " +$i days"));
        $veckansDagar[$datum] = [];
    }

    foreach ($bokningar as $bokning) {
        $veckansDagar[$bokning['planDate']][] = $bokning;
    }

    return $veckansDagar;
}

/**
 * Formatera tid för visning
 */
function formateraTid($tid) {
    if (!$tid) return '--:--';
    return date('H:i', strtotime($tid));
}

/**
 * Beräkna blockets höjd baserat på tidsskillnad
 */
function beraknaBlockHojd($starttid, $sluttid) {
    if (!$starttid || !$sluttid) return 1;

    $start = strtotime($starttid);
    $slut = strtotime($sluttid);
    $timmar = ($slut - $start) / 3600;

    $timmar = max(0.5, min(8, $timmar));
    return $timmar;
}

/**
 * Hämta alla användare
 */
function hamtaAllaAnvandare($pdo) {
    $stmt = $pdo->query("
        SELECT id, anvandarnamn, email, roll, skapad, aktiv
        FROM stat_anvandare
        ORDER BY anvandarnamn ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Skapa användare
 */
function skapaAnvandare($pdo, $data) {
    try {
        $check = $pdo->prepare("SELECT id FROM stat_anvandare WHERE anvandarnamn = ?");
        $check->execute([$data['anvandarnamn']]);
        if ($check->fetch()) {
            return false;
        }

        $check = $pdo->prepare("SELECT id FROM stat_anvandare WHERE email = ?");
        $check->execute([$data['email']]);
        if ($check->fetch()) {
            return false;
        }

        $sql = "INSERT INTO stat_anvandare (anvandarnamn, email, losenord, roll)
                VALUES (:anvandarnamn, :email, :losenord, :roll)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($data);

    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Uppdatera användare
 */
function uppdateraAnvandare($pdo, $id, $data) {
    $sql = "UPDATE stat_anvandare
            SET anvandarnamn = :anvandarnamn,
                email = :email,
                roll = :roll,
                aktiv = :aktiv
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $data['id'] = $id;
    return $stmt->execute($data);
}

/**
 * Ändra lösenord
 */
function andraLosenord($pdo, $id, $nytt_losenord) {
    $hash = password_hash($nytt_losenord, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE stat_anvandare SET losenord = ? WHERE id = ?");
    return $stmt->execute([$hash, $id]);
}

/**
 * Kolla om användare är inloggad
 */
function arInloggad() {
    return isset($_SESSION['anvandare_id']);
}

/**
 * Kolla om användare är admin
 */
function arAdmin() {
    if (isset($_SESSION['anvandare_roll']) && $_SESSION['anvandare_roll'] == 'admin') {
        return true;
    }
    return false;
}

/**
 * Omdirigera om inte inloggad
 */
function kravInloggning() {
    if (!arInloggad()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Omdirigera om inte admin
 */
function kravAdmin() {
    kravInloggning();
    if (!arAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

/**
 * Formatera datum svenskt format
 */
function formatDateSvensk($datum) {
    if (!$datum) return '-';
    return date('Y-m-d', strtotime($datum));
}

/**
 * Formatera pris
 */
function formatPris($pris) {
    if (!$pris) return '-';
    return number_format($pris, 0, ',', ' ') . ' kr';
}

/**
 * Radera användare
 */
function raderaAnvandare($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as antal FROM stat_anvandare WHERE roll = 'admin' AND aktiv = 1");
    $stmt->execute();
    $result = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT roll FROM stat_anvandare WHERE id = ?");
    $stmt->execute([$id]);
    $anvandare = $stmt->fetch();

    if ($anvandare && $anvandare['roll'] == 'admin' && $result['antal'] <= 1) {
        return false;
    }

    $stmt = $pdo->prepare("DELETE FROM stat_anvandare WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Hämta projektets rader
 */
function hamtaProjektRader($pdo, $projekt_id) {
    $stmt = $pdo->prepare("SELECT * FROM stat_projekt_rader WHERE projekt_id = ? ORDER BY id ASC");
    $stmt->execute([$projekt_id]);
    return $stmt->fetchAll();
}

/**
 * Spara projektets rader (ersätter alla befintliga)
 */
function sparaProjektRader($pdo, $projekt_id, $rader) {
    $pdo->prepare("DELETE FROM stat_projekt_rader WHERE projekt_id = ?")->execute([$projekt_id]);

    $sql = "INSERT INTO stat_projekt_rader (projekt_id, artikel_id, typ, beskrivning, pris, antal, rabatt)
            VALUES (:projekt_id, :artikel_id, :typ, :beskrivning, :pris, :antal, :rabatt)";
    $stmt = $pdo->prepare($sql);

    foreach ($rader as $rad) {
        if (empty(trim($rad['beskrivning'] ?? ''))) continue;

        $stmt->execute([
            ':projekt_id'  => $projekt_id,
            ':artikel_id'  => !empty($rad['artikel_id']) ? (int)$rad['artikel_id'] : null,
            ':typ'         => 'material',
            ':beskrivning' => trim($rad['beskrivning']),
            ':pris'        => (float)($rad['pris'] ?? 0),
            ':antal'       => (float)($rad['antal'] ?? 1),
            ':rabatt'      => (float)($rad['rabatt'] ?? 0),
        ]);
    }
}

// ──────────────────────────────────────────────────────────
// ARTIKLAR (admin-hanterade produkter/tjänster)
// ──────────────────────────────────────────────────────────

/**
 * Hämta alla artiklar
 */
function hamtaAllaArtiklar($pdo, bool $aktivaOnly = false): array {
    if ($aktivaOnly) {
        $stmt = $pdo->query("SELECT * FROM stat_artiklar WHERE aktiv = 1 ORDER BY namn ASC");
    } else {
        $stmt = $pdo->query("SELECT * FROM stat_artiklar ORDER BY aktiv DESC, namn ASC");
    }
    return $stmt->fetchAll();
}

/**
 * Hämta en artikel
 */
function hamtaArtikel($pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM stat_artiklar WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Skapa artikel
 */
function skapaArtikel($pdo, array $data): bool {
    $stmt = $pdo->prepare("
        INSERT INTO stat_artiklar (namn, pris, tillat_rabatt, pris_disabled, aktiv)
        VALUES (:namn, :pris, :tillat_rabatt, :pris_disabled, :aktiv)
    ");
    return $stmt->execute($data);
}

/**
 * Uppdatera artikel
 */
function uppdateraArtikel($pdo, int $id, array $data): bool {
    $data['id'] = $id;
    $stmt = $pdo->prepare("
        UPDATE stat_artiklar
        SET namn           = :namn,
            pris           = :pris,
            tillat_rabatt  = :tillat_rabatt,
            pris_disabled  = :pris_disabled,
            aktiv          = :aktiv
        WHERE id = :id
    ");
    return $stmt->execute($data);
}

/**
 * Radera artikel
 */
function raderaArtikel($pdo, int $id): bool {
    $stmt = $pdo->prepare("DELETE FROM stat_artiklar WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Beräkna projektets totalsumma från rader
 */
function beraknaProjektSumma($rader) {
    $total = 0;
    foreach ($rader as $rad) {
        $total += ((float)($rad['pris'] ?? 0) * (float)($rad['antal'] ?? 1)) - (float)($rad['rabatt'] ?? 0);
    }
    return $total;
}

// ──────────────────────────────────────────────────────────
// DASHBOARD-NOTISER
// ──────────────────────────────────────────────────────────

/**
 * Hämta notiser för dashboard
 * Returnerar array med notiser av olika typer
 */
function getDashboardNotiser($pdo): array {
    $notiser = [];

    // Projekt som stått som inkommen i mer än 7 dagar
    $stmt = $pdo->query("
        SELECT COUNT(*) as antal
        FROM stat_projekt
        WHERE status = 'inkommen'
          AND createdDate < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $row = $stmt->fetch();
    if ($row['antal'] > 0) {
        $notiser[] = [
            'typ'  => 'warning',
            'ikon' => 'fa-clock',
            'text' => $row['antal'] . ' projekt ' . ($row['antal'] === 1 ? 'har' : 'har') . ' stått som <strong>Inkommen</strong> i mer än 7 dagar',
            'link' => 'projekt_lista.php?status=inkommen',
            'link_text' => 'Visa',
        ];
    }

    // Flaggade projekt
    $stmt = $pdo->query("
        SELECT COUNT(*) as antal
        FROM stat_projekt
        WHERE flagga = 1
          AND status != 'avslutad'
    ");
    $row = $stmt->fetch();
    if ($row['antal'] > 0) {
        $notiser[] = [
            'typ'  => 'danger',
            'ikon' => 'fa-exclamation-circle',
            'text' => $row['antal'] . ' projekt ' . ($row['antal'] === 1 ? 'är' : 'är') . ' <strong>flaggade för granskning</strong>',
            'link' => 'projekt_lista.php?flagga=1',
            'link_text' => 'Visa',
        ];
    }

    // Projekt med passerat plandat och status != avslutad
    $stmt = $pdo->query("
        SELECT COUNT(*) as antal
        FROM stat_projekt
        WHERE planDate < CURDATE()
          AND planDate IS NOT NULL
          AND status != 'avslutad'
    ");
    $row = $stmt->fetch();
    if ($row['antal'] > 0) {
        $notiser[] = [
            'typ'  => 'warning',
            'ikon' => 'fa-calendar-times',
            'text' => $row['antal'] . ' projekt har <strong>passerat planerat datum</strong> utan att avslutas',
            'link' => 'projekt_lista.php',
            'link_text' => 'Visa',
        ];
    }

    // Avslutade men obetalda projekt
    $stmt = $pdo->query("
        SELECT COUNT(*) as antal
        FROM stat_projekt
        WHERE status = 'avslutad'
          AND betald = 0
    ");
    $row = $stmt->fetch();
    if ($row['antal'] > 0) {
        $notiser[] = [
            'typ'  => 'info',
            'ikon' => 'fa-file-invoice-dollar',
            'text' => $row['antal'] . ' avslutade projekt är <strong>ännu inte betalda</strong>',
            'link' => 'projekt_lista.php?status=avslutad&betald=0',
            'link_text' => 'Visa',
        ];
    }

    return $notiser;
}

// ──────────────────────────────────────────────────────────
// SNABB-ÅTGÄRDER (quick actions)
// ──────────────────────────────────────────────────────────

/**
 * Sätt projekt-status till avslutad via snabb-åtgärd
 */
function snabbAvslutaProjekt($pdo, int $id): bool {
    $stmt = $pdo->prepare("
        UPDATE stat_projekt
        SET status = 'avslutad',
            avslutad = 1,
            avslutadDatum = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$id]);
}

/**
 * Sätt projekt som betalt via snabb-åtgärd
 */
function snabbMarkBetald($pdo, int $id): bool {
    $stmt = $pdo->prepare("UPDATE stat_projekt SET betald = 1 WHERE id = ?");
    return $stmt->execute([$id]);
}

// ──────────────────────────────────────────────────────────
// KALENDER PER TEKNIKER
// ──────────────────────────────────────────────────────────

/**
 * Hämta vecko-bokningar, valfritt filtrerat per tekniker
 */
function hamtaVeckansBokningarPerTekniker($pdo, string $veckaStart, ?int $tekniker_id = null): array {
    $veckaSlut = date('Y-m-d', strtotime($veckaStart . ' +6 days'));

    $sql = "
        SELECT
            p.id,
            p.regnummer,
            p.rubrik,
            p.planDate,
            p.starttid,
            p.sluttid,
            p.status,
            p.kontakt_person_namn,
            p.ansvarig_tekniker,
            a.anvandarnamn as tekniker_namn
        FROM stat_projekt p
        LEFT JOIN stat_anvandare a ON p.ansvarig_tekniker = a.id
        WHERE p.planDate BETWEEN :start AND :slut
          AND p.planDate IS NOT NULL
          AND p.status IN ('inkommen', 'pågående')
    ";

    $params = [':start' => $veckaStart, ':slut' => $veckaSlut];

    if ($tekniker_id !== null) {
        $sql .= " AND p.ansvarig_tekniker = :tekniker_id";
        $params[':tekniker_id'] = $tekniker_id;
    }

    $sql .= " ORDER BY p.planDate ASC, p.starttid ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bokningar = $stmt->fetchAll();

    $veckansDagar = [];
    for ($i = 0; $i < 7; $i++) {
        $datum = date('Y-m-d', strtotime($veckaStart . " +$i days"));
        $veckansDagar[$datum] = [];
    }
    foreach ($bokningar as $b) {
        $veckansDagar[$b['planDate']][] = $b;
    }

    return $veckansDagar;
}

// ──────────────────────────────────────────────────────────
// STATISTIK PER TEKNIKER
// ──────────────────────────────────────────────────────────

/**
 * Hämta detaljerad statistik per tekniker
 */
function getTeknikerStatistikDetaljerad($pdo): array {
    $stmt = $pdo->query("
        SELECT
            a.id,
            a.anvandarnamn,
            COUNT(p.id)                                                  AS totalt,
            SUM(CASE WHEN p.status = 'avslutad'  THEN 1 ELSE 0 END)     AS avslutade,
            SUM(CASE WHEN p.status = 'pågående'  THEN 1 ELSE 0 END)     AS pagaende,
            SUM(CASE WHEN p.status = 'inkommen'  THEN 1 ELSE 0 END)     AS inkomna,
            SUM(CASE WHEN p.betald = 1           THEN p.pris ELSE 0 END) AS intakt,
            AVG(CASE
                WHEN p.status = 'avslutad' AND p.avslutadDatum IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, p.createdDate, p.avslutadDatum)
                ELSE NULL
            END)                                                         AS snitt_timmar
        FROM stat_anvandare a
        LEFT JOIN stat_projekt p ON p.ansvarig_tekniker = a.id
        GROUP BY a.id, a.anvandarnamn
        ORDER BY avslutade DESC, a.anvandarnamn ASC
    ");
    return $stmt->fetchAll();
}

// ──────────────────────────────────────────────────────────
// PUBLIKA ARBETSORDER-TOKENS (SMS-länk)
// ──────────────────────────────────────────────────────────

/**
 * Generera (eller återanvänd) publik token + PIN för ett projekt.
 * Returnerar ['token' => '...', 'pin' => '1234'] – PIN returneras bara vid ny generering (visas ej igen).
 * Sparar hashat PIN i databasen.
 */
function genereraPublikToken($pdo, int $projekt_id): array {
    // Kolla om token redan finns och är giltig
    $stmt = $pdo->prepare("
        SELECT publik_token, publik_utgangsdatum
        FROM stat_projekt
        WHERE id = ?
    ");
    $stmt->execute([$projekt_id]);
    $row = $stmt->fetch();

    if (!empty($row['publik_token']) && !empty($row['publik_utgangsdatum'])
        && strtotime($row['publik_utgangsdatum']) > time()) {
        // Befintlig token är fortfarande giltig – generera ny PIN
        $pin  = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE stat_projekt SET publik_pin_hash = ? WHERE id = ?")
            ->execute([$hash, $projekt_id]);
        return ['token' => $row['publik_token'], 'pin' => $pin];
    }

    // Skapa ny token
    $token       = bin2hex(random_bytes(20));
    $pin         = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $hash        = password_hash($pin, PASSWORD_DEFAULT);
    $utgangsdatum = date('Y-m-d', strtotime('+' . ORDER_LINK_DAGAR . ' days'));

    $pdo->prepare("
        UPDATE stat_projekt
        SET publik_token = ?,
            publik_pin_hash = ?,
            publik_utgangsdatum = ?
        WHERE id = ?
    ")->execute([$token, $hash, $utgangsdatum, $projekt_id]);

    return ['token' => $token, 'pin' => $pin];
}

/**
 * Hämta projekt via publik token (kontrollerar giltighetstid)
 */
function hamtaProjektMedToken($pdo, string $token): ?array {
    $stmt = $pdo->prepare("
        SELECT p.*, a.anvandarnamn as skapad_av_namn
        FROM stat_projekt p
        LEFT JOIN stat_anvandare a ON p.skapad_av = a.id
        WHERE p.publik_token = ?
          AND p.publik_utgangsdatum >= CURDATE()
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Validera PIN mot projekt
 */
function valideraPublikPin($pdo, int $projekt_id, string $pin): bool {
    $stmt = $pdo->prepare("SELECT publik_pin_hash FROM stat_projekt WHERE id = ?");
    $stmt->execute([$projekt_id]);
    $row = $stmt->fetch();
    if (!$row || empty($row['publik_pin_hash'])) return false;
    return password_verify($pin, $row['publik_pin_hash']);
}

// ══════════════════════════════════════════════════════════
// EKONOMI – PROJEKTKOSTNADER
// ══════════════════════════════════════════════════════════

/**
 * Hämta kostnader för ett projekt
 */
function hamtaProjektKostnader($pdo, int $projekt_id): array {
    $stmt = $pdo->prepare("SELECT * FROM stat_projekt_kostnader WHERE projekt_id = ? ORDER BY datum DESC, id DESC");
    $stmt->execute([$projekt_id]);
    return $stmt->fetchAll();
}

/**
 * Lägg till projektkostnad
 */
function laggTillProjektKostnad($pdo, int $projekt_id, string $beskrivning, float $belopp, int $moms_procent, string $datum): int|false {
    $stmt = $pdo->prepare("
        INSERT INTO stat_projekt_kostnader (projekt_id, beskrivning, belopp, moms_procent, datum)
        VALUES (:projekt_id, :beskrivning, :belopp, :moms_procent, :datum)
    ");
    $ok = $stmt->execute([
        ':projekt_id'   => $projekt_id,
        ':beskrivning'  => $beskrivning,
        ':belopp'       => $belopp,
        ':moms_procent' => $moms_procent,
        ':datum'        => $datum,
    ]);
    return $ok ? (int)$pdo->lastInsertId() : false;
}

/**
 * Uppdatera projektkostnad
 */
function uppdateraProjektKostnad($pdo, int $id, string $beskrivning, float $belopp, int $moms_procent, string $datum): bool {
    $stmt = $pdo->prepare("
        UPDATE stat_projekt_kostnader
        SET beskrivning = :beskrivning, belopp = :belopp, moms_procent = :moms_procent, datum = :datum
        WHERE id = :id
    ");
    return $stmt->execute([
        ':beskrivning'  => $beskrivning,
        ':belopp'       => $belopp,
        ':moms_procent' => $moms_procent,
        ':datum'        => $datum,
        ':id'           => $id,
    ]);
}

/**
 * Radera projektkostnad
 */
function raderaProjektKostnad($pdo, int $id): bool {
    $stmt = $pdo->prepare("DELETE FROM stat_projekt_kostnader WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Beräkna total projektkostnad (exkl. moms)
 */
function beraknaTotalProjektKostnad($pdo, int $projekt_id): float {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(belopp), 0) FROM stat_projekt_kostnader WHERE projekt_id = ?");
    $stmt->execute([$projekt_id]);
    return (float)$stmt->fetchColumn();
}

// ══════════════════════════════════════════════════════════
// EKONOMI – ALLMÄNNA UTGIFTER
// ══════════════════════════════════════════════════════════

/**
 * Hämta alla allmänna utgifter (med valfritt filter på år/månad)
 */
function hamtaUtgifter($pdo, ?int $ar = null, ?int $manad = null): array {
    $where  = ['u.aktiv = 1'];
    $params = [];

    if ($ar !== null) {
        $where[]         = 'YEAR(u.datum) = :ar';
        $params[':ar']   = $ar;
    }
    if ($manad !== null) {
        $where[]           = 'MONTH(u.datum) = :manad';
        $params[':manad']  = $manad;
    }

    $whereStr = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("SELECT * FROM stat_utgifter u $whereStr ORDER BY u.datum DESC, u.id DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Hämta en enskild utgift
 */
function hamtaUtgift($pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM stat_utgifter WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Spara ny allmän utgift
 */
function skapaUtgift($pdo, array $data): int|false {
    $stmt = $pdo->prepare("
        INSERT INTO stat_utgifter (kategori, beskrivning, belopp, moms_procent, datum, aterkommande)
        VALUES (:kategori, :beskrivning, :belopp, :moms_procent, :datum, :aterkommande)
    ");
    $ok = $stmt->execute($data);
    return $ok ? (int)$pdo->lastInsertId() : false;
}

/**
 * Uppdatera allmän utgift
 */
function uppdateraUtgift($pdo, int $id, array $data): bool {
    $data[':id'] = $id;
    $stmt = $pdo->prepare("
        UPDATE stat_utgifter
        SET kategori      = :kategori,
            beskrivning   = :beskrivning,
            belopp        = :belopp,
            moms_procent  = :moms_procent,
            datum         = :datum,
            aterkommande  = :aterkommande
        WHERE id = :id
    ");
    return $stmt->execute($data);
}

/**
 * Mjukradera (inaktivera) allmän utgift
 */
function raderaUtgift($pdo, int $id): bool {
    $stmt = $pdo->prepare("UPDATE stat_utgifter SET aktiv = 0 WHERE id = ?");
    return $stmt->execute([$id]);
}

// ══════════════════════════════════════════════════════════
// EKONOMI – MÅNADSÖVERSIKT (intäkter + kostnader + vinst)
// ══════════════════════════════════════════════════════════

/**
 * Hämta ekonomiöversikt per månad för ett givet år.
 * Returnerar 12 rader (en per månad) med:
 *   intakter, proj_kostnader, allm_kostnader, aterkommande_per_manad, netto
 */
function getEkonomiManadsoversikt($pdo, int $ar): array {
    // Intäkter (betalda projekt) per månad
    $stmtI = $pdo->prepare("
        SELECT MONTH(createdDate) AS manad, COALESCE(SUM(pris), 0) AS intakter
        FROM stat_projekt
        WHERE betald = 1 AND YEAR(createdDate) = :ar
        GROUP BY MONTH(createdDate)
    ");
    $stmtI->execute([':ar' => $ar]);
    $intakter = [];
    foreach ($stmtI->fetchAll() as $r) $intakter[(int)$r['manad']] = (float)$r['intakter'];

    // Projektkostnader per månad
    $stmtPK = $pdo->prepare("
        SELECT MONTH(datum) AS manad, COALESCE(SUM(belopp), 0) AS kostnad
        FROM stat_projekt_kostnader
        WHERE YEAR(datum) = :ar
        GROUP BY MONTH(datum)
    ");
    $stmtPK->execute([':ar' => $ar]);
    $projKostnader = [];
    foreach ($stmtPK->fetchAll() as $r) $projKostnader[(int)$r['manad']] = (float)$r['kostnad'];

    // Allmänna utgifter (ej återkommande) per månad
    $stmtU = $pdo->prepare("
        SELECT MONTH(datum) AS manad, COALESCE(SUM(belopp), 0) AS kostnad
        FROM stat_utgifter
        WHERE aktiv = 1 AND aterkommande = 0 AND YEAR(datum) = :ar
        GROUP BY MONTH(datum)
    ");
    $stmtU->execute([':ar' => $ar]);
    $almKostnader = [];
    foreach ($stmtU->fetchAll() as $r) $almKostnader[(int)$r['manad']] = (float)$r['kostnad'];

    // Återkommande utgifter – summa per månad (gäller alla månader)
    $stmtR = $pdo->prepare("
        SELECT COALESCE(SUM(belopp), 0) AS summa
        FROM stat_utgifter
        WHERE aktiv = 1 AND aterkommande = 1
    ");
    $stmtR->execute();
    $aterkommandeSumma = (float)$stmtR->fetchColumn();

    $manader = [];
    for ($m = 1; $m <= 12; $m++) {
        $i  = $intakter[$m]      ?? 0;
        $pk = $projKostnader[$m] ?? 0;
        $ak = ($almKostnader[$m] ?? 0) + $aterkommandeSumma;
        $manader[$m] = [
            'manad'              => $m,
            'intakter'           => $i,
            'proj_kostnader'     => $pk,
            'allm_kostnader'     => $ak,
            'totala_kostnader'   => $pk + $ak,
            'netto'              => $i - $pk - $ak,
        ];
    }
    return $manader;
}

/**
 * Hämta ekonomisummering för hela året
 */
function getEkonomiArssummering($pdo, int $ar): array {
    $manader = getEkonomiManadsoversikt($pdo, $ar);
    $sum = ['intakter' => 0, 'proj_kostnader' => 0, 'allm_kostnader' => 0, 'totala_kostnader' => 0, 'netto' => 0];
    foreach ($manader as $m) {
        foreach ($sum as $k => $_) $sum[$k] += $m[$k];
    }
    return $sum;
}

/**
 * Hämta projekt med vinst (intäkt − projektkostnader)
 */
function getProjektMedVinst($pdo, int $ar): array {
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.regnummer,
            p.rubrik,
            p.kontakt_person_namn,
            p.status,
            p.betald,
            p.createdDate,
            COALESCE(p.pris, 0)                          AS intakt,
            COALESCE(SUM(pk.belopp), 0)                  AS kostnader,
            COALESCE(p.pris, 0) - COALESCE(SUM(pk.belopp), 0) AS vinst
        FROM stat_projekt p
        LEFT JOIN stat_projekt_kostnader pk ON pk.projekt_id = p.id
        WHERE YEAR(p.createdDate) = :ar
        GROUP BY p.id
        ORDER BY p.createdDate DESC
    ");
    $stmt->execute([':ar' => $ar]);
    return $stmt->fetchAll();
}

/**
 * Hämta ekonomiöversikt för dashboard (innevarande månad + år totalt)
 */
function getDashboardEkonomi($pdo): array {
    $ar    = (int)date('Y');
    $manad = (int)date('n');

    // Intäkter betalda denna månad
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(pris), 0) FROM stat_projekt
        WHERE betald = 1 AND YEAR(createdDate) = ? AND MONTH(createdDate) = ?
    ");
    $stmt->execute([$ar, $manad]);
    $intaktMaand = (float)$stmt->fetchColumn();

    // Projektkostnader denna månad
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(belopp), 0) FROM stat_projekt_kostnader
        WHERE YEAR(datum) = ? AND MONTH(datum) = ?
    ");
    $stmt->execute([$ar, $manad]);
    $projKostMaand = (float)$stmt->fetchColumn();

    // Allmänna utgifter (ej återkommande) denna månad
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(belopp), 0) FROM stat_utgifter
        WHERE aktiv = 1 AND aterkommande = 0 AND YEAR(datum) = ? AND MONTH(datum) = ?
    ");
    $stmt->execute([$ar, $manad]);
    $almKostMaand = (float)$stmt->fetchColumn();

    // Återkommande utgifter
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(belopp), 0) FROM stat_utgifter WHERE aktiv = 1 AND aterkommande = 1");
    $stmt->execute();
    $aterkommande = (float)$stmt->fetchColumn();

    $totalKostMaand = $projKostMaand + $almKostMaand + $aterkommande;

    return [
        'intakt_manad'    => $intaktMaand,
        'kostnad_manad'   => $totalKostMaand,
        'netto_manad'     => $intaktMaand - $totalKostMaand,
        'aterkommande'    => $aterkommande,
    ];
}
?>
