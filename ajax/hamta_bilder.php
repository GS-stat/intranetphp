<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';

// Kontrollera session
if (!isset($_SESSION['anvandare_id'])) {
    echo json_encode(['success' => false, 'message' => 'Inte inloggad']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['projekt_id']) || empty($_GET['projekt_id'])) {
    echo json_encode(['success' => false, 'message' => 'Projekt-ID saknas', 'bilder' => []]);
    exit;
}

$projekt_id = (int)$_GET['projekt_id'];

// Testa databasanslutning
try {
    // Kontrollera att tabellen finns
    $stmt = $pdo->query("SHOW TABLES LIKE 'stat_projekt_bilder'");
    if ($stmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Tabellen stat_projekt_bilder finns inte', 'bilder' => []]);
        exit;
    }
    
    // Hämta bilder
    $stmt = $pdo->prepare("
        SELECT b.*, a.anvandarnamn as uppladdad_av_namn 
        FROM stat_projekt_bilder b
        LEFT JOIN stat_anvandare a ON b.uppladdad_av = a.id
        WHERE b.projekt_id = ?
        ORDER BY b.uppladdad_datum DESC
    ");
    $stmt->execute([$projekt_id]);
    $bilder = $stmt->fetchAll();
    
    foreach ($bilder as &$bild) {
        $bild['sokvag_full'] = '../' . $bild['sökväg'];
        $bild['filstorlek_formaterad'] = formatFileSize($bild['filstorlek']);
        $bild['uppladdad_datum_formaterad'] = date('Y-m-d H:i', strtotime($bild['uppladdad_datum']));
    }
    
    echo json_encode(['success' => true, 'bilder' => $bilder]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Databasfel: ' . $e->getMessage(), 'bilder' => []]);
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return round($bytes / 1024, 0) . ' KB';
}
?>