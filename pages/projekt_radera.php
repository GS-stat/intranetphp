<?php
require_once '../includes/config.php';
kravAdmin();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    raderaProjekt($pdo, $id);
}

header('Location: projekt_lista.php?meddelande=Projekt raderat');
exit;
?>