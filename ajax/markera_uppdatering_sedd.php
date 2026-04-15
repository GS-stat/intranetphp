<?php
require_once '../includes/config.php';
kravInloggning();

header('Content-Type: application/json');

$uppdatering_id = (int)($_POST['uppdatering_id'] ?? 0);
$anvandare_id   = (int)($_SESSION['anvandare_id'] ?? 0);

if (!$uppdatering_id || !$anvandare_id) {
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $pdo->prepare("
        INSERT IGNORE INTO stat_uppdatering_sedd (anvandare_id, uppdatering_id)
        VALUES (?, ?)
    ")->execute([$anvandare_id, $uppdatering_id]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'fel' => $e->getMessage()]);
}
