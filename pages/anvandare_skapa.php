<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Logga till fil
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

require_once '../includes/config.php';
kravAdmin();

$debug = [];
$debug[] = "Startar anvandare_skapa.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debug[] = "POST mottagen: " . print_r($_POST, true);
    
    $data = [
        'anvandarnamn' => $_POST['anvandarnamn'],
        'email' => $_POST['email'],
        'losenord' => password_hash($_POST['losenord'], PASSWORD_DEFAULT),
        'roll' => $_POST['roll']
    ];
    
    $debug[] = "Data förberedd: " . print_r($data, true);
    
    // Testa databasanslutningen först
    try {
        $test = $pdo->query("SELECT 1");
        $debug[] = "Databasanslutning OK";
    } catch (Exception $e) {
        $debug[] = "Databasfel: " . $e->getMessage();
    }
    
    // Försök skapa användare
    try {
        $resultat = skapaAnvandare($pdo, $data);
        $debug[] = "skapaAnvandare returnerade: " . ($resultat ? 'true' : 'false');
        
        if ($resultat) {
            $debug[] = "Omdirigerar till anvandare_lista.php";
            header('Location: anvandare_lista.php?meddelande=Användare skapad');
            exit;
        } else {
            $error = "Kunde inte skapa användare";
            // Hämta eventuellt PDO-fel
            $errorInfo = $pdo->errorInfo();
            $debug[] = "PDO Error: " . print_r($errorInfo, true);
        }
    } catch (Exception $e) {
        $debug[] = "Exception: " . $e->getMessage();
        $error = "Fel: " . $e->getMessage();
    }
}

// Skriv debug till fil
file_put_contents(__DIR__ . '/../debug_log.txt', implode("\n", $debug) . "\n\n", FILE_APPEND);
?>
<?php include '../includes/header.php'; ?>

<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><i class="fas fa-user-plus"></i> Skapa ny användare</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Visa debug på sidan (ta bort efter felsökning) -->
                <?php if (!empty($debug)): ?>
                    <div class="alert alert-info">
                        <h6>Debug information:</h6>
                        <pre><?php echo implode("\n", $debug); ?></pre>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="anvandarnamn" class="form-label">Användarnamn *</label>
                        <input type="text" class="form-control" id="anvandarnamn" name="anvandarnamn" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-post *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="losenord" class="form-label">Lösenord *</label>
                        <input type="password" class="form-control" id="losenord" name="losenord" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="roll" class="form-label">Roll *</label>
                        <select class="form-control" id="roll" name="roll" required>
                            <option value="user">Användare</option>
                            <option value="admin">Administratör</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save"></i> Skapa användare
                    </button>
                    <a href="anvandare_lista.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Avbryt
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>