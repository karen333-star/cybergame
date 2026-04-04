<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identidad = trim($_POST['identidad'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identidad) || empty($password)) {
        header("Location: login.html?error=Completa todos los campos");
        exit;
    }

    // Buscar usuario por email O nombre_usuario
    $sql = "SELECT id_usuario, nombre_usuario, email, password_hash FROM usuarios 
            WHERE email = ? OR nombre_usuario = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        header("Location: login.html?error=Error interno al iniciar sesión");
        exit;
    }
    $stmt->bind_param("ss", $identidad, $identidad);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: login.html?error=Usuario o contraseña incorrectos");
        exit;
    }

    $usuario = $result->fetch_assoc();

    // Verificar contraseña
    if (!password_verify($password, $usuario['password_hash'])) {
        header("Location: login.html?error=Usuario o contraseña incorrectos");
        exit;
    }

    // Login exitoso - crear sesión
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuario['id_usuario'];
    $_SESSION['nombre_usuario'] = $usuario['nombre_usuario'];
    $_SESSION['email'] = $usuario['email'];

    // Redirigir a menú
    header("Location: menu.php");
    exit;
}

// Si es GET, mostrar formulario
header("Location: login.html");
exit;
?>
