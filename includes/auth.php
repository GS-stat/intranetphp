<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $anvandarnamn = $_POST['anvandarnamn'] ?? '';
    $lösenord = $_POST['lösenord'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM stat_anvandare WHERE anvandarnamn = ? AND aktiv = 1");
    $stmt->execute([$anvandarnamn]);
    $anvandare = $stmt->fetch();
    
    if ($anvandare && password_verify($lösenord, $anvandare['losenord'])) {
        $_SESSION['anvandare_id'] = $anvandare['id'];
        $_SESSION['anvandare_namn'] = $anvandare['anvandarnamn'];
        $_SESSION['anvandare_roll'] = $anvandare['roll'];
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Fel användarnamn eller lösenord";
    }
}
?>