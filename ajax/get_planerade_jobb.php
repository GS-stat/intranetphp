<?php
require_once '../includes/config.php';
kravInloggning();

if (isset($_GET['datum'])) {
    $datum = $_GET['datum'];
    $antal = hamtaPlaneradeJobbForDatum($pdo, $datum);
    
    if ($antal > 0) {
        echo "<span class='badge bg-warning text-dark'>$antal planerade jobb denna dag</span>";
    } else {
        echo "<span class='badge bg-success'>Inga planerade jobb än</span>";
    }
}
?>