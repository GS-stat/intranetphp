<?php
require_once '../includes/config.php';
kravInloggning();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Ogiltig metod';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['bild_id']) || empty($_POST['bild_id'])) {
    $response['message'] = 'Bild-ID saknas';
    echo json_encode($response);
    exit;
}

$bild_id = (int)$_POST['bild_id'];

// Hämta bildinfo
$stmt = $pdo->prepare("SELECT * FROM stat_projekt_bilder WHERE id = ?");
$stmt->execute([$bild_id]);
$bild = $stmt->fetch();

if (!$bild) {
    $response['message'] = 'Bilden finns inte';
    echo json_encode($response);
    exit;
}

// Ta bort filen från servern
$fil_sokvag = dirname(__DIR__) . '/' . $bild['sökväg'];
if (file_exists($fil_sokvag)) {
    unlink($fil_sokvag);
}

// Ta bort från databasen
$stmt = $pdo->prepare("DELETE FROM stat_projekt_bilder WHERE id = ?");
if ($stmt->execute([$bild_id])) {
    $response['success'] = true;
    $response['message'] = 'Bilden togs bort';
} else {
    $response['message'] = 'Kunde inte ta bort bilden från databasen';
}

echo json_encode($response);
?>