<?php
session_start();

// ──────────────────────────────────────────────
// DATABAS
// ──────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'svzgkxoy_wp422');
define('DB_USER', 'svzgkxoy_stat');
define('DB_PASS', 'fGdN^J}qP6$B');

// ──────────────────────────────────────────────
// SAJT-URL  (utan avslutande slash)
// Används i SMS-länk och e-post
// ──────────────────────────────────────────────
define('SITE_URL', 'https://gsmotors.se');

// ──────────────────────────────────────────────
// E-POST
// Avsändaradress för automatiska kvittenser
// ──────────────────────────────────────────────
define('MAIL_FROM',      'noreply@gsmotors.se');
define('MAIL_FROM_NAME', 'GS Motors');

// ──────────────────────────────────────────────
// SMS – 46elks
// Skapa konto på 46elks.com och fyll i nedan
// ──────────────────────────────────────────────
define('SMS_ENABLED',  TRUE);               // Sätt till true när du har API-nycklar
define('SMS_API_USER', 'u57521b0882ffce52bf6c42c09f986447');  // 46elks API-username
define('SMS_API_PASS', '20E6458C3812B031431FA0EB51747A78');  // 46elks API-password
define('SMS_FROM',     'GS Motors');          // Avsändarnamn (max 11 tecken)
define('KONTAKT_TELEFON', '073-073 00 09');   // Visas i boknings-SMS till kund

// ──────────────────────────────────────────────
// PUBLIKA ARBETSORDER-TOKENS
// Hur länge en publik länk är giltig (dagar)
// ──────────────────────────────────────────────
define('ORDER_LINK_DAGAR', 7);

// ──────────────────────────────────────────────
// DATABASANSLUTNING
// ──────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ]
    );
} catch (PDOException $e) {
    die("Anslutning misslyckades: " . $e->getMessage());
}

require_once __DIR__ . '/functions.php';
?>
