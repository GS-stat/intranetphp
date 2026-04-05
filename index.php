<?php
// Starta session
session_start();

// Försök omdirigera till rätt sida
try {
    // Kontrollera om användaren är inloggad
    if (isset($_SESSION['anvandare_id']) && !empty($_SESSION['anvandare_id'])) {
        // Användaren är inloggad - skicka till dashboard
        header('Location: pages/dashboard.php');
    } else {
        // Användaren är inte inloggad - skicka till login
        header('Location: pages/login.php');
    }
    exit;
} catch (Exception $e) {
    // Om något går fel, visa ett enkelt meddelande
    echo "<h1>GS Motors Stat</h1>";
    echo "<p>Ett fel uppstod. Försök igen senare.</p>";
    echo "<p><a href='pages/login.php'>Gå till inloggning</a></p>";
}
?>