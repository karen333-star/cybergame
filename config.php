<?php
// Configurar timeout/cookie de sesión antes de iniciarla.
// Si la sesión ya está activa (p.ej. session.auto_start), no intentar reconfigurarla.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.gc_maxlifetime', 86400);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Conexión a BD CYBERGAME
$dbHost = getenv('CYBERGAME_DB_HOST') ?: 'localhost';
$dbUser = getenv('CYBERGAME_DB_USER') ?: 'u592438158_Toska';
$dbPass = getenv('CYBERGAME_DB_PASS') ?: 'LauraHermosa.117';
$dbName = getenv('CYBERGAME_DB_NAME') ?: 'u592438158_cybergame';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

// Verificar conexión
if ($conn->connect_error) {
    error_log('Error de conexión a BD: ' . $conn->connect_error);
    http_response_code(500);
    die('Error interno de conexión');
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

