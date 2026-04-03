<?php
// =======================================
// RECUPERACIÓN DE CONTRASEÑA SIMPLIFICADA
// Con PHPMailer para envío real de emails
// =======================================

require 'config.php';
header('Content-Type: application/json; charset=utf-8');

// ========== USAR PHPMailer ==========
// Requiere: composer require phpmailer/phpmailer
// O descargar desde: https://github.com/PHPMailer/PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Función para enviar email con PHPMailer
function enviar_email_phpmailer($email_destino, $codigo, $nombre_usuario = 'Usuario') {
    try {
        $mail = new PHPMailer(true);
        
        // ========== CONFIGURACIÓN SMTP ==========
        // DESARROLLO: Usar Gmail o Mailtrap
        // PRODUCCIÓN: Usar SendGrid, AWS SES, etc.
        
        // --- Opción 1: Gmail (DEVELOPMENT) ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tu-email@gmail.com';          // ← CAMBIAR
        $mail->Password   = 'tu-app-password';              // ← CAMBIAR (16 caracteres)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // --- Opción 2: Mailtrap (TESTING) ---
        // $mail->isSMTP();
        // $mail->Host       = 'live.smtp.mailtrap.io';
        // $mail->SMTPAuth   = true;
        // $mail->Username   = 'api';
        // $mail->Password   = 'tu-token-mailtrap';
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port       = 587;
        
        // --- Opción 3: SendGrid (PRODUCCIÓN) ---
        // $mail->isSMTP();
        // $mail->Host       = 'smtp.sendgrid.net';
        // $mail->SMTPAuth   = true;
        // $mail->Username   = 'apikey';
        // $mail->Password   = 'SG.xxxxx...';
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port       = 587;
        
        // ========== CONTENIDO DEL EMAIL ==========
        $mail->setFrom('noreply@cybergame.local', 'CyberGame');
        $mail->addAddress($email_destino);
        
        $mail->isHTML(true);
        $mail->Subject = 'CyberGame - Tu código de recuperación';
        $mail->Body = "
            <h2>¡Hola $nombre_usuario!</h2>
            <p>Recibimos una solicitud para recuperar tu contraseña.</p>
            
            <p><strong>Tu código es:</strong></p>
            <h1 style='color:#cf2e11; font-size:32px; text-align:center;'>$codigo</h1>
            
            <p>Este código es válido por <strong>10 minutos</strong>.</p>
            <p>Si no solicitaste esto, ignora este email.</p>
            
            <hr>
            <p style='color:#999; font-size:12px;'>© 2026 CyberGame - Email automático</p>
        ";
        
        // ========== ENVIAR ==========
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Error PHPMailer: " . $mail->ErrorInfo);
        return false;
    }
}

$accion = $_POST['accion'] ?? '';

// ===============================================
// PASO 1: ENVIAR CÓDIGO
// ===============================================
if ($accion === 'enviar_codigo') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['ok' => false, 'error' => 'EMAIL_VACIO']);
        exit;
    }
    
    // ========== BUSCAR USUARIO ==========
    $sql = "SELECT id_usuario, nombre_usuario FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Por seguridad: no revelar si existe o no
        echo json_encode(['ok' => true, 'mensaje' => 'Si existe, recibirás código']);
        exit;
    }
    
    $usuario = $result->fetch_assoc();
    $id_usuario = $usuario['id_usuario'];
    $nombre_usuario = $usuario['nombre_usuario'];
    
    // ========== GENERAR CÓDIGO NUMÉRICO ==========
    // 6 dígitos: 100000 - 999999
    $codigo = random_int(100000, 999999);
    $codigo_hash = hash('sha256', $codigo);
    
    // ========== EXPIRACIÓN: 10 MINUTOS ==========
    $expira_en = date('Y-m-d H:i:s', time() + 10 * 60);
    
    // ========== GUARDAR EN BD ==========
    $sql = "INSERT INTO tokens_recuperacion (id_usuario, token_hash, expira_en, usado, creado_en)
            VALUES (?, ?, ?, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $id_usuario, $codigo_hash, $expira_en);
    
    if (!$stmt->execute()) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_BD']);
        exit;
    }
    
    // ========== ENVIAR EMAIL ==========
    // IMPORTANTE: descomentar cuando tengas PHPMailer instalado
    $email_enviado = enviar_email_phpmailer($email, $codigo, $nombre_usuario);
    
    if ($email_enviado) {
        echo json_encode([
            'ok' => true,
            'mensaje' => '✅ Código enviado a tu correo (válido 10 minutos)',
            'codigo_debug' => $codigo  // Solo DEV: quitar en producción
        ]);
    } else {
        echo json_encode([
            'ok' => true,
            'mensaje' => '⚠️ Código generado pero error al enviar email',
            'codigo_debug' => $codigo  // DEV: mostrar código localmente
        ]);
    }
    exit;
}

// ===============================================
// PASO 2: VERIFICAR CÓDIGO
// ===============================================
if ($accion === 'verificar_codigo') {
    $email = trim($_POST['email'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    
    if (empty($email) || empty($codigo)) {
        echo json_encode(['ok' => false, 'error' => 'CAMPOS_VACIOS']);
        exit;
    }
    
    // ========== HASHEAR CÓDIGO INGRESADO ==========
    // Comparar hashes, nunca textos planos
    $codigo_hash = hash('sha256', $codigo);
    
    // ========== BUSCAR TOKEN VÁLIDO ==========
    // Condiciones:
    // 1. Hash coincida
    // 2. No expirado
    // 3. No usado
    $sql = "SELECT id_token FROM tokens_recuperacion
            WHERE token_hash = ?
            AND usado = 0
            AND expira_en > NOW()
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $codigo_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => 'CODIGO_INVALIDO']);
        exit;
    }
    
    $token = $result->fetch_assoc();
    $id_token = $token['id_token'];
    
    // ========== GENERAR TOKEN DE SESIÓN ==========
    // Para el paso 3 (reset de contraseña)
    session_start();
    $_SESSION['temp_reset_email'] = $email;
    $_SESSION['temp_reset_token'] = bin2hex(random_bytes(16));
    $_SESSION['temp_reset_id_token'] = $id_token;
    
    echo json_encode([
        'ok' => true,
        'mensaje' => '✅ Código verificado',
        'temp_token' => $_SESSION['temp_reset_token']
    ]);
    exit;
}

// ===============================================
// PASO 3: RESETEAR CONTRASEÑA
// ===============================================
if ($accion === 'resetear_contraseña') {
    session_start();
    
    $temp_token = trim($_POST['temp_token'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($temp_token) || empty($password) || empty($password_confirm)) {
        echo json_encode(['ok' => false, 'error' => 'CAMPOS_VACIOS']);
        exit;
    }
    
    // ========== VALIDAR TOKEN DE SESIÓN ==========
    if (!isset($_SESSION['temp_reset_token']) || $_SESSION['temp_reset_token'] !== $temp_token) {
        echo json_encode(['ok' => false, 'error' => 'SESION_INVALIDA']);
        exit;
    }
    
    // ========== VALIDAR CONTRASEÑAS ==========
    if ($password !== $password_confirm) {
        echo json_encode(['ok' => false, 'error' => 'PASS_NO_COINCIDEN']);
        exit;
    }
    
    // Validar requisitos: 7+ chars, 1 mayúscula, 1 número
    if (!validar_contraseña($password)) {
        echo json_encode(['ok' => false, 'error' => 'PASS_DEBIL']);
        exit;
    }
    
    $email = $_SESSION['temp_reset_email'];
    $id_token = $_SESSION['temp_reset_id_token'];
    
    // ========== ACTUALIZAR CONTRASEÑA ==========
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    $sql = "UPDATE usuarios SET password_hash = ? WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $password_hash, $email);
    $stmt->execute();
    
    // ========== MARCAR TOKEN COMO USADO ==========
    $sql = "UPDATE tokens_recuperacion SET usado = 1, usado_en = NOW() WHERE id_token = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_token);
    $stmt->execute();
    
    // ========== LIMPIAR SESIÓN ==========
    unset($_SESSION['temp_reset_email']);
    unset($_SESSION['temp_reset_token']);
    unset($_SESSION['temp_reset_id_token']);
    
    echo json_encode(['ok' => true, 'mensaje' => '✅ Contraseña actualizada']);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'ACCION_INVALIDA']);
?>
