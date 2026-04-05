<?php
require_once '../includes/config.php';
kravAdmin();

$anvandare = hamtaAllaAnvandare($pdo);
?>
<?php include '../includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1><i class="fas fa-users"></i> Hantera användare</h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="anvandare_skapa.php" class="btn btn-danger">
            <i class="fas fa-user-plus"></i> Ny användare
        </a>
    </div>
</div>

<?php if (isset($_GET['meddelande'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['meddelande']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Användarnamn</th>
                        <th>E-post</th>
                        <th>Roll</th>
                        <th>Status</th>
                        <th>Skapad</th>
                        <th>Åtgärder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anvandare as $a): ?>
                    <tr>
                        <td>#<?php echo $a['id']; ?></td>
                        <td><?php echo htmlspecialchars($a['anvandarnamn']); ?></td>
                        <td><?php echo htmlspecialchars($a['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $a['roll'] == 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                <?php echo $a['roll']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($a['aktiv']): ?>
                                <span class="badge bg-success">Aktiv</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inaktiv</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($a['skapad'])); ?></td>
                        <td>
                            <a href="anvandare_redigera.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-warning" title="Redigera">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($a['id'] != $_SESSION['anvandare_id']): ?>
                            <a href="anvandare_radera.php?id=<?php echo $a['id']; ?>" 
                               class="btn btn-sm btn-danger" 
                               title="Radera"
                               onclick="return confirm('Är du säker på att du vill radera användaren <?php echo htmlspecialchars($a['anvandarnamn']); ?>? Detta kan inte ångras.');">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>