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
    
    <main class="container-fluid mt-4">