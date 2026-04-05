<?php
/**
 * snabb_action.php
 * AJAX-endpoint för snabbåtgärder från projekt-listan.
 *
 * POST-parametrar:
 *   action      : 'avsluta' | 'betald'
 *   projekt_id  : int
 *
 * Returnerar JSON: { success, message, ny_status?, ny_betald? }
 */
require_once '../includes/config.php';
require_once '../includes/sms.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metod ej tillåten']);
    exit;
}

kravInloggning();

$action     = trim($_POST['action']     ?? '');
$projekt_id = (int)($_POST['projekt_id'] ?? 0);

if (!$projekt_id || !in_array($action, ['avsluta', 'betald'], true)) {
    echo json_encode(['success' => false, 'message' => 'Ogiltiga parametrar']);
    exit;
}

// Verifiera att projektet finns
$stmt = $pdo->prepare("SELECT id, status, betald FROM stat_projekt WHERE id = ?");
$stmt->execute([$projekt_id]);
$projekt = $stmt->fetch();

if (!$projekt) {
    echo json_encode(['success' => false, 'message' => 'Projektet hittades inte']);
    exit;
}

switch ($action) {

    case 'avsluta':
        if ($projekt['status'] === 'avslutad') {
            echo json_encode(['success' => false, 'message' => 'Projektet är redan avslutat']);
            exit;
        }
        $ok = snabbAvslutaProjekt($pdo, $projekt_id);
        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera status']);
            exit;
        }
        // Försök skicka SMS om det nu är betalt också
        skickaSmsKvittens($pdo, $projekt_id);

        echo json_encode([
            'success'   => true,
            'message'   => 'Status satt till Avslutad',
            'ny_status' => 'avslutad',
        ]);
        break;

    case 'betald':
        if ($projekt['betald']) {
            echo json_encode(['success' => false, 'message' => 'Projektet är redan markerat som betalt']);
            exit;
        }
        $ok = snabbMarkBetald($pdo, $projekt_id);
        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Kunde inte uppdatera betalstatus']);
            exit;
        }
        // Försök skicka SMS om projektet nu är avslutad + betald
        skickaSmsKvittens($pdo, $projekt_id);

        echo json_encode([
            'success'    => true,
            'message'    => 'Projektet markerat som Betalt',
            'ny_betald'  => true,
        ]);
        break;
}
