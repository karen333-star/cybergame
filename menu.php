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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberGame - Menú Principal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1 id="main-title">🛡️ CyberGame</h1>
        
        <div class="card">
            <h2>Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?>!</h2>
            
            <div class="menu-opciones">
                <a href="index.html?view=config" class="menu-btn">🎮 Iniciar Partida</a>
                <a href="perfil.php" class="menu-btn">👤 Ver Perfil</a>
                <a href="historial.php" class="menu-btn">📊 Ver Historial</a>
                <a href="menu.php?logout=1" class="menu-btn logout-btn">🚪 Cerrar Sesión</a>
            </div>
        </div>
    </div>
</body>
</html>
