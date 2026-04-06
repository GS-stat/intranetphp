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
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['meddelande']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($anvandare)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-users fa-3x mb-3"></i>
            <h5>Inga användare hittades</h5>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="anvandare-tabell">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Användarnamn</th>
                        <th>E-post</th>
                        <th style="width:100px">Roll</th>
                        <th style="width:100px">Status</th>
                        <th style="width:110px">Skapad</th>
                        <th style="width:90px" class="text-center">Åtgärder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anvandare as $a): ?>
                    <tr class="anvandare-rad <?php echo $a['aktiv'] ? '' : 'rad-inaktiv'; ?>">
                        <td class="text-muted small">#<?php echo $a['id']; ?></td>

                        <td>
                            <div class="fw-semibold lh-sm"><?php echo htmlspecialchars($a['anvandarnamn']); ?></div>
                            <?php if ((int)$a['id'] === (int)$_SESSION['anvandare_id']): ?>
                                <small class="text-muted">(du)</small>
                            <?php endif; ?>
                        </td>

                        <td class="text-muted">
                            <?php echo htmlspecialchars($a['email']); ?>
                        </td>

                        <td>
                            <span class="badge <?php echo $a['roll'] === 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                <?php echo $a['roll'] === 'admin'
                                    ? '<i class="fas fa-shield-alt me-1"></i>Admin'
                                    : '<i class="fas fa-user me-1"></i>Användare'; ?>
                            </span>
                        </td>

                        <td>
                            <?php if ($a['aktiv']): ?>
                                <span class="badge bg-success"><i class="fas fa-circle me-1" style="font-size:.6rem"></i>Aktiv</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="fas fa-circle me-1" style="font-size:.6rem"></i>Inaktiv</span>
                            <?php endif; ?>
                        </td>

                        <td class="small text-muted">
                            <?php echo date('Y-m-d', strtotime($a['skapad'])); ?>
                        </td>

                        <td class="text-center">
                            <div class="atgard-knappar">
                                <a href="anvandare_redigera.php?id=<?php echo $a['id']; ?>"
                                   class="atg-btn" title="Redigera">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <?php if ((int)$a['id'] !== (int)$_SESSION['anvandare_id']): ?>
                                <a href="anvandare_radera.php?id=<?php echo $a['id']; ?>"
                                   class="atg-btn atg-radera"
                                   title="Radera"
                                   onclick="return confirm('Radera användaren \'<?php echo htmlspecialchars(addslashes($a['anvandarnamn'])); ?>\'? Detta kan inte ångras.');">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ── Användare-tabell – matchar projekt_lista-stil ── */
.anvandare-tabell {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.875rem;
}
.anvandare-tabell thead tr {
    background: #212529;
    color: #fff;
}
.anvandare-tabell thead th {
    padding: 10px 14px;
    font-weight: 600;
    font-size: 0.78rem;
    letter-spacing: .03em;
    text-transform: uppercase;
    border: none;
    white-space: nowrap;
}
.anvandare-tabell tbody tr {
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color .12s ease, box-shadow .12s ease;
}
.anvandare-tabell tbody tr:hover {
    background: #fdf3f4;
    box-shadow: inset 0 0 0 1px rgba(220,53,69,.12);
}
.anvandare-tabell tbody td {
    padding: 10px 14px;
    vertical-align: middle;
}
.rad-inaktiv { opacity: 0.5; }
.rad-inaktiv:hover { opacity: 0.75; }

/* Åtgärds-knappar */
.atgard-knappar { display: flex; gap: 4px; justify-content: center; }
.atg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px; height: 30px;
    border-radius: 6px;
    border: 1.5px solid #dee2e6;
    background: #fff;
    color: #495057;
    font-size: 0.72rem;
    cursor: pointer;
    transition: all .12s ease;
    text-decoration: none;
}
.atg-btn:hover      { border-color: #adb5bd; background: #f8f9fa; color: #212529; }
.atg-radera         { border-color: #f5c6cb; color: #dc3545; }
.atg-radera:hover   { background: #dc3545; color: #fff; border-color: #dc3545; }
</style>

<?php include '../includes/footer.php'; ?>
