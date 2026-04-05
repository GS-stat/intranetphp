<?php
require_once '../includes/config.php';
kravAdmin();

if (!isset($_GET['id'])) {
    header('Location: anvandare_lista.php');
    exit;
}

$id = (int)$_GET['id'];

// Kontrollera att användaren inte försöker radera sig själv
if ($id == $_SESSION['anvandare_id']) {
    header('Location: anvandare_lista.php?error=Du kan inte radera ditt eget konto');
    exit;
}

// Försök radera användaren
if (raderaAnvandare($pdo, $id)) {
    header('Location: anvandare_lista.php?meddelande=Användare raderad');
} else {
    header('Location: anvandare_lista.php?error=Kunde inte radera användaren. Det måste finnas minst en aktiv admin.');
}
exit;
?>