<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GS Motors - Stat</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome för ikoner -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Vår egen CSS - relativ sökväg -->
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php
    // Funktion för att kontrollera om användare är inloggad (om den inte redan är definierad)
    if (!function_exists('arInloggad')) {
        function arInloggad() {
            return isset($_SESSION['anvandare_id']);
        }
    }

    // Hämta osedda uppdateringar för inloggad användare
    $oseddaUppdateringar = [];
    if (arInloggad() && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.titel, u.innehall
                FROM stat_uppdateringar u
                WHERE u.id NOT IN (
                    SELECT uppdatering_id FROM stat_uppdatering_sedd
                    WHERE anvandare_id = ?
                )
                ORDER BY u.skapad ASC
            ");
            $stmt->execute([$_SESSION['anvandare_id']]);
            $oseddaUppdateringar = $stmt->fetchAll();
        } catch (Exception $e) {
            // Tabellerna kanske inte finns ännu — visa ingen popup
        }
    }

    if (arInloggad()): 
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-car"></i> GS Motors Stat
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Hem
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="projekt_lista.php">
                            <i class="fas fa-list"></i> Alla projekt
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="projekt_skapa.php">
                            <i class="fas fa-plus-circle"></i> Nytt projekt
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-bar"></i> Statistik
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="statistik_tekniker.php">
                                <i class="fas fa-user-tie me-1"></i> Statistik per tekniker
                            </a></li>
                            <li><a class="dropdown-item" href="kalender_tekniker.php">
                                <i class="fas fa-calendar-alt me-1"></i> Kalender per tekniker
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-coins"></i> Ekonomi
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="ekonomi.php">
                                <i class="fas fa-chart-line me-1"></i> Ekonomiöversikt
                            </a></li>
                            <li><a class="dropdown-item" href="utgifter_lista.php">
                                <i class="fas fa-receipt me-1"></i> Allmänna utgifter
                            </a></li>
                        </ul>
                    </li>
                    <?php if (arAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users"></i> Användare
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="anvandare_lista.php">Lista användare</a></li>
                            <li><a class="dropdown-item" href="anvandare_skapa.php">Skapa användare</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="artiklar_lista.php">
                            <i class="fas fa-tags"></i> Artiklar
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="navbar-text text-white me-3">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['anvandare_namn'] ?? ''); ?>
                            (<?php echo $_SESSION['anvandare_roll'] ?? ''; ?>)
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logga ut
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <?php if (!empty($oseddaUppdateringar)): ?>
    <!-- ══════════════════════════════════════════════════════ -->
    <!--  UPPDATERING-POPUP                                    -->
    <!-- ══════════════════════════════════════════════════════ -->
    <style>
        #gs-uppdatering-overlay {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0,0,0,.55);
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            animation: gsOverlayIn .2s ease;
        }
        @keyframes gsOverlayIn { from { opacity:0; } to { opacity:1; } }

        #gs-uppdatering-modal {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            width: 100%; max-width: 560px;
            max-height: 88vh;
            display: flex; flex-direction: column;
            animation: gsModalIn .25s cubic-bezier(.34,1.56,.64,1);
            overflow: hidden;
        }
        @keyframes gsModalIn { from { transform: scale(.9) translateY(20px); opacity:0; } to { transform: none; opacity:1; } }

        .gs-modal-header {
            background: linear-gradient(135deg, #dc3545 0%, #c0212f 100%);
            color: white;
            padding: 28px 28px 20px;
            flex-shrink: 0;
        }
        .gs-modal-header .gs-badge {
            display: inline-block;
            background: rgba(255,255,255,.2);
            border-radius: 20px;
            padding: 3px 12px;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .gs-modal-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.3;
        }
        .gs-modal-header p {
            margin: 6px 0 0;
            opacity: .85;
            font-size: .9rem;
        }

        .gs-modal-body {
            padding: 20px 24px;
            overflow-y: auto;
            flex: 1;
        }

        .gs-punkt {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .gs-punkt:last-child { border-bottom: none; }
        .gs-punkt-ikon {
            font-size: 1.6rem;
            flex-shrink: 0;
            width: 38px;
            text-align: center;
            line-height: 1;
            padding-top: 2px;
        }
        .gs-punkt-rubrik {
            font-weight: 700;
            font-size: .95rem;
            color: #1c1c1e;
            margin-bottom: 3px;
        }
        .gs-punkt-text {
            font-size: .875rem;
            color: #3c3c43;
            line-height: 1.5;
        }

        .gs-modal-footer {
            padding: 16px 24px 20px;
            border-top: 1px solid #f0f0f0;
            flex-shrink: 0;
        }
        #gs-forstar-btn {
            width: 100%;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
        }
        #gs-forstar-btn:hover { background: #b02a37; }
        #gs-forstar-btn:active { background: #9a2430; }
        #gs-forstar-btn:disabled { opacity: .6; cursor: default; }
        .gs-modal-footer small {
            display: block;
            text-align: center;
            color: #aeaeb2;
            font-size: .78rem;
            margin-top: 8px;
        }
    </style>

    <div id="gs-uppdatering-overlay">
        <div id="gs-uppdatering-modal" role="dialog" aria-modal="true" aria-labelledby="gs-modal-titel">
            <?php $upd = $oseddaUppdateringar[0]; ?>
            <div class="gs-modal-header">
                <div class="gs-badge">Systemuppdatering</div>
                <h2 id="gs-modal-titel"><?php echo htmlspecialchars($upd['titel']); ?></h2>
                <p>Läs igenom vad som är nytt och tryck sedan på knappen nedan.</p>
            </div>
            <div class="gs-modal-body">
                <?php
                $punkter = json_decode($upd['innehall'], true) ?: [];
                foreach ($punkter as $p):
                ?>
                <div class="gs-punkt">
                    <div class="gs-punkt-ikon"><?php echo $p['ikon']; ?></div>
                    <div>
                        <div class="gs-punkt-rubrik"><?php echo htmlspecialchars($p['rubrik']); ?></div>
                        <div class="gs-punkt-text"><?php echo htmlspecialchars($p['text']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="gs-modal-footer">
                <button id="gs-forstar-btn" data-id="<?php echo (int)$upd['id']; ?>">
                    Jag förstår – stäng
                </button>
                <small>Visas inte igen efter att du tryckt på knappen.</small>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('gs-forstar-btn').addEventListener('click', function() {
        var btn = this;
        var id  = btn.getAttribute('data-id');
        btn.disabled = true;
        btn.textContent = 'Sparar...';

        var fd = new FormData();
        fd.append('uppdatering_id', id);

        fetch('../ajax/markera_uppdatering_sedd.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function() {
                var overlay = document.getElementById('gs-uppdatering-overlay');
                overlay.style.transition = 'opacity .2s';
                overlay.style.opacity = '0';
                setTimeout(function() { overlay.remove(); }, 200);
            })
            .catch(function() {
                // Ta bort popup ändå — visa inte igen
                document.getElementById('gs-uppdatering-overlay').remove();
            });
    });
    </script>
    <?php endif; ?>

    <main class="container-fluid mt-4">