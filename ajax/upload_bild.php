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

if (!isset($_POST['projekt_id']) || empty($_POST['projekt_id'])) {
    $response['message'] = 'Projekt-ID saknas';
    echo json_encode($response);
    exit;
}

$projekt_id = (int)$_POST['projekt_id'];
$anvandare_id = $_SESSION['anvandare_id'];

// Kontrollera att projektet finns
$stmt = $pdo->prepare("SELECT id FROM stat_projekt WHERE id = ?");
$stmt->execute([$projekt_id]);
if (!$stmt->fetch()) {
    $response['message'] = 'Projektet finns inte';
    echo json_encode($response);
    exit;
}

// Kontrollera om fil är uppladdad
if (!isset($_FILES['bild']) || $_FILES['bild']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Ingen fil uppladdad eller uppladdningen misslyckades';
    echo json_encode($response);
    exit;
}

$file = $_FILES['bild'];

// Validera filtyp
$tillatna_typer = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $tillatna_typer)) {
    $response['message'] = 'Endast JPG, PNG, GIF och WEBP är tillåtna';
    echo json_encode($response);
    exit;
}

// Validera filstorlek (max 10 MB)
if ($file['size'] > 10 * 1024 * 1024) {
    $response['message'] = 'Filen är för stor. Max 10 MB.';
    echo json_encode($response);
    exit;
}

// Skapa mapp om den inte finns
$upload_dir = dirname(__DIR__) . '/uploads/bilder/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Skapa unikt filnamn
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$unikt_namn = uniqid() . '_' . time() . '.' . $extension;
$uppladdnings_sokvag = $upload_dir . $unikt_namn;
$db_sokvag = 'uploads/bilder/' . $unikt_namn;

// Flytta filen
if (move_uploaded_file($file['tmp_name'], $uppladdnings_sokvag)) {
    // Spara i databasen
    $sql = "INSERT INTO stat_projekt_bilder (projekt_id, filnamn, original_namn, sökväg, filstorlek, filtyp, uppladdad_av) 
            VALUES (:projekt_id, :filnamn, :original_namn, :sokvag, :filstorlek, :filtyp, :uppladdad_av)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':projekt_id' => $projekt_id,
        ':filnamn' => $unikt_namn,
        ':original_namn' => $file['name'],
        ':sokvag' => $db_sokvag,
        ':filstorlek' => $file['size'],
        ':filtyp' => $mime_type,
        ':uppladdad_av' => $anvandare_id
    ]);
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = 'Bilden laddades upp';
    } else {
        $response['message'] = 'Kunde inte spara i databasen';
        unlink($uppladdnings_sokvag);
    }
} else {
    $response['message'] = 'Kunde inte flytta filen';
}

echo json_encode($response);
?>