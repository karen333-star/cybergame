<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validaciones
    if (empty($email) || empty($nombre_usuario) || empty($password)) {
        header("Location: register.html?error=Completa todos los campos");
        exit;
    }

    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.html?error=Email inválido");
        exit;
    }

    // Validar contraseñas coinciden
    if ($password !== $confirm_password) {
        header("Location: register.html?error=Las contraseñas no coinciden");
        exit;
    }

    // Validar contraseña: 7+ caracteres, 1 mayúscula, 1 número
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{7,}$/', $password)) {
        header("Location: register.html?error=Contraseña débil: mínimo 7 caracteres, 1 mayúscula y 1 número");
        exit;
    }

    // Validar que email sea único
    $sql = "SELECT id_usuario FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        header("Location: register.html?error=Este email ya está registrado");
        exit;
    }

    // Validar que nombre_usuario sea único
    $sql = "SELECT id_usuario FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nombre_usuario);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        header("Location: register.html?error=Este nombre de usuario ya está registrado");
        exit;
    }

    // Hash de contraseña
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Insertar usuario
    $sql = "INSERT INTO usuarios (email, nombre_usuario, password_hash) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $email, $nombre_usuario, $password_hash);

    if (!$stmt->execute()) {
        header("Location: register.html?error=Error al registrar usuario");
        exit;
    }

    $usuario_id = $conn->insert_id;

    // Crear sesión automáticamente después del registro
    $_SESSION['usuario_id'] = $usuario_id;
    $_SESSION['nombre_usuario'] = $nombre_usuario;
    $_SESSION['email'] = $email;

    // Redirigir a menú
    header("Location: menu.php");
    exit;
}

// Si es GET, mostrar formulario
header("Location: register.html");
exit;
?>
