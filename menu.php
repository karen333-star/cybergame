<?php
require 'config.php';

// Validar sesión
validar_sesion();

// Procesar logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.html");
    exit;
}

$nombre_usuario = $_SESSION['nombre_usuario'];
$usuario_param = urlencode($nombre_usuario);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberGame - Menú Principal</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="menu.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
</head>
<body class="menu-page">
    <div class="menu-grid-overlay" aria-hidden="true"></div>
    <div class="container menu-container">
        <div class="card menu-card">
            <div class="menu-corner menu-corner-tl" aria-hidden="true"></div>
            <div class="menu-corner menu-corner-tr" aria-hidden="true"></div>
            <div class="menu-corner menu-corner-bl" aria-hidden="true"></div>
            <div class="menu-corner menu-corner-br" aria-hidden="true"></div>

            <h1 id="main-title" class="menu-title"><span class="menu-title-prefix">&gt;_</span> CYBER_GAME</h1>
            <h2 class="menu-welcome">Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?>!</h2>

            <div class="menu-opciones">
                <a href="index.html?view=config&usuario=<?php echo $usuario_param; ?>" class="menu-btn menu-btn-primary">
                    <span>INICIAR PARTIDA</span>
                </a>
                <a href="perfil.php" class="menu-btn menu-btn-secondary">
                    <span>VER PERFIL</span>
                </a>
                <a href="historial.php" class="menu-btn menu-btn-secondary">
                    <span>VER HISTORIAL</span>
                </a>
                <a href="menu.php?logout=1" class="menu-btn menu-btn-danger logout-btn">
                    <span>CERRAR SESION</span>
                </a>
            </div>

            <p class="menu-status"><span class="menu-status-dot"></span>SISTEMA OPERATIVO // RED SEGURA</p>
        </div>
    </div>
</body>
</html>
