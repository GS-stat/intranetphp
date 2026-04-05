<?php
require_once '../includes/auth.php';
?>
<?php include '../includes/header.php'; ?>

<div class="login-box">
    <div class="text-center mb-4">
        <i class="fas fa-car fa-3x text-danger"></i>
        <h2 class="mt-3">GS Motors Stat</h2>
        <p class="text-muted">Logga in för att fortsätta</p>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="mb-3">
            <label for="anvandarnamn" class="form-label">Användarnamn</label>
            <input type="text" class="form-control" id="anvandarnamn" name="anvandarnamn" required>
        </div>
        <div class="mb-3">
            <label for="lösenord" class="form-label">Lösenord</label>
            <input type="password" class="form-control" id="lösenord" name="lösenord" required>
        </div>
        <button type="submit" name="login" class="btn btn-danger w-100">Logga in</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>