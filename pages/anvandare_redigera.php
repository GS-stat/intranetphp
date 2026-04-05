<?php
require_once '../includes/config.php';
kravAdmin();

if (!isset($_GET['id'])) {
    header('Location: anvandare_lista.php');
    exit;
}

$id = $_GET['id'];

// Hämta användarens data
$stmt = $pdo->prepare("SELECT * FROM stat_anvandare WHERE id = ?");
$stmt->execute([$id]);
$anvandare = $stmt->fetch();

if (!$anvandare) {
    header('Location: anvandare_lista.php?meddelande=Användare finns inte');
    exit;
}

// Uppdatera användare
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['andra_losenord']) && !empty($_POST['nytt_losenord'])) {
        // Ändra lösenord
        andraLosenord($pdo, $id, $_POST['nytt_losenord']);
        $meddelande = "Lösenord uppdaterat";
    }
    
    $data = [
        'anvandarnamn' => $_POST['anvandarnamn'],
        'email' => $_POST['email'],
        'roll' => $_POST['roll'],
        'aktiv' => isset($_POST['aktiv']) ? 1 : 0
    ];
    
    if (uppdateraAnvandare($pdo, $id, $data)) {
        header('Location: anvandare_lista.php?meddelande=Användare uppdaterad');
        exit;
    } else {
        $error = "Kunde inte uppdatera användare";
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-edit"></i> Redigera användare</h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="anvandare_lista.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Tillbaka
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($anvandare['anvandarnamn']); ?></h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($meddelande)): ?>
                    <div class="alert alert-success"><?php echo $meddelande; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="anvandarnamn" class="form-label">Användarnamn *</label>
                        <input type="text" class="form-control" id="anvandarnamn" name="anvandarnamn" 
                               value="<?php echo htmlspecialchars($anvandare['anvandarnamn']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-post *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($anvandare['email']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="roll" class="form-label">Roll *</label>
                        <select class="form-control" id="roll" name="roll" required>
                            <option value="user" <?php echo $anvandare['roll'] == 'user' ? 'selected' : ''; ?>>Användare</option>
                            <option value="admin" <?php echo $anvandare['roll'] == 'admin' ? 'selected' : ''; ?>>Administratör</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="aktiv" name="aktiv" 
                                   <?php echo $anvandare['aktiv'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="aktiv">
                                Aktiv (kan logga in)
                            </label>
                        </div>
                    </div>
                    
                    <hr>
                    <h5>Ändra lösenord</h5>
                    <p class="text-muted small">Lämna tomt om du inte vill ändra lösenordet</p>
                    
                    <div class="mb-3">
                        <label for="nytt_losenord" class="form-label">Nytt lösenord</label>
                        <input type="password" class="form-control" id="nytt_losenord" name="nytt_losenord">
                    </div>
                    
                    <div class="mb-3">
                        <button type="submit" name="andra_losenord" class="btn btn-info">
                            <i class="fas fa-key"></i> Uppdatera lösenord
                        </button>
                    </div>
                    
                    <hr>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save"></i> Spara ändringar
                    </button>

                    <a href="anvandare_radera.php?id=<?php echo $anvandare['id']; ?>" 
                    class="btn btn-danger" 
                    onclick="return confirm('Är du säker på att du vill radera denna användare?');">
                        <i class="fas fa-trash"></i> Radera användare
                    </a>
                    
                    <a href="anvandare_lista.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Avbryt
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>