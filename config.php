<?php
// Configurar timeout/cookie de sesión antes de iniciarla.
// Si la sesión ya está activa (p.ej. session.auto_start), no intentar reconfigurarla.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', 86400);
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Conexión a BD CYBERGAME
$conn = new mysqli("localhost", "root", "", "CYBERGAME");

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset UTF-8
$conn->set_charset("utf8mb4");

// ============================================
// FUNCIÓN: Validar contraseña
// Requisitos: 7+ caracteres, 1 mayúscula, 1 número
// ============================================
function validar_contraseña($password) {
    if (strlen($password) < 7) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    return true;
}

// ============================================
// FUNCIÓN: Validar sesión
// Redirige a login si no hay sesión activa
// ============================================
function validar_sesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: login.html");
        exit;
    }
}
?>
