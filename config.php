<?php
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configurar timeout/cookie de sesión antes de iniciarla.
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

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// ============================================
// FUNCIÓN: Validar contraseña
// ============================================
function validar_contraseña($password) {
    if (strlen($password) < 7) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    return true;
}

// ============================================
// FUNCIÓN: Validar sesión
// ============================================
function validar_sesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: login.html");
        exit;
    }
}

// ============================================
// CONFIGURACIÓN DE EMAIL - AUTODETECT SMTP
// ============================================
// El sistema detecta automáticamente la configuración SMTP
// basada en el dominio del email remitente
//
// PROVEEDORES SOPORTADOS:
// - Gmail: smtp.gmail.com (requiere App Password)
// - Outlook/Hotmail/Live: smtp-mail.outlook.com
// - Yahoo: smtp.mail.yahoo.com
// - iCloud: smtp.mail.me.com
// - Otros: fallback a Gmail
//
// INSTRUCCIONES:
// 1. Cambia 'username' por tu email real
// 2. Cambia 'password' por tu contraseña de aplicación
// 3. Para Gmail: genera App Password en myaccount.google.com/apppasswords
// 4. Para Outlook: usa tu contraseña normal
// 5. Para Yahoo: usa tu contraseña normal
//
// El sistema funcionará con cualquier email que tenga SMTP

function detectar_config_smtp($email_remitente) {
    $dominio = strtolower(substr(strrchr($email_remitente, "@"), 1));
    
    $configs = [
        // Gmail / Google Workspace
        'gmail.com' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        'googlemail.com' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        
        // Outlook / Hotmail / Live
        'outlook.com' => [
            'host' => 'smtp-mail.outlook.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        'hotmail.com' => [
            'host' => 'smtp-mail.outlook.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        'live.com' => [
            'host' => 'smtp-mail.outlook.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        
        // Yahoo
        'yahoo.com' => [
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        'yahoo.es' => [
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        'yahoo.co.uk' => [
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        
        // iCloud
        'icloud.com' => [
            'host' => 'smtp.mail.me.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        'me.com' => [
            'host' => 'smtp.mail.me.com',
            'port' => 587,
            'encryption' => 'tls'
        ],
        
        // Configuración genérica por defecto
        'default' => [
            'host' => 'smtp.gmail.com',  // Fallback a Gmail
            'port' => 587,
            'encryption' => 'tls'
        ]
    ];
    
    return $configs[$dominio] ?? $configs['default'];
}

const EMAIL_CONFIG = [
    'driver' => 'phpmailer',
    
    // ========== MODO PRODUCCIÓN (GMAIL SMTP) ==========
    'use_sandbox' => false,
    'username' => 'tucorreo@gmail.com',         // Reemplaza con tu Gmail.
    'password' => 'xxxxxxxxxxxxxxxx',           // Reemplaza con tu App Password de Gmail.
    
    'from_email' => 'tucorreo@gmail.com',
    'from_name' => 'CyberGame - Sistema de Recuperación',
];

// ============================================
// FUNCIÓN: Generar código de 6 dígitos
// ============================================
function generar_codigo_6_digitos() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// ============================================
// FUNCIÓN: Enviar email de recuperación con PHPMailer
// ============================================
function enviar_email_recuperacion($email_destino, $codigo, $nombre_usuario = 'Usuario') {
    global $conn;
    
    // ========== MODO SANDBOX: Guardar en archivo local ==========
    // Perfecto para desarrollo - no requiere configuración de SMTP
    if (EMAIL_CONFIG['use_sandbox'] === true) {
        $emails_dir = __DIR__ . '/emails_debug';
        if (!is_dir($emails_dir)) {
            mkdir($emails_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $email_safe = str_replace(['@', '.'], ['_at_', '_'], $email_destino);
        $file_path = $emails_dir . '/' . $timestamp . '_' . $email_safe . '.html';
        
        // Generar HTML del email
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $app_url = 'http://' . $host . '/cibergame/forgot.html';
        
        $email_html = "
        <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { text-align: center; color: #cf2e11; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
                    .content { color: #333; line-height: 1.6; margin: 20px 0; }
                    .codigo { 
                        background: #f0f0f0; 
                        padding: 15px; 
                        border-radius: 5px; 
                        font-family: 'Courier New', monospace; 
                        font-size: 28px; 
                        font-weight: bold;
                        text-align: center;
                        color: #cf2e11;
                        margin: 20px 0;
                        letter-spacing: 4px;
                    }
                    .link { color: #8b5cf6; text-decoration: none; }
                    .footer { text-align: center; color: #999; font-size: 12px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; }
                    .warning { background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>🛡️ CyberGame</div>
                    
                    <div class='content'>
                        <p>¡Hola <strong>" . htmlspecialchars($nombre_usuario) . "</strong>!</p>
                        
                        <p>Recibimos una solicitud para recuperar tu contraseña. Si no fuiste tú, ignora este email.</p>
                        
                        <p><strong>Tu código de verificación es:</strong></p>
                        <div class='codigo'>" . htmlspecialchars($codigo) . "</div>
                        
                        <p>Este código es válido por <strong>30 minutos</strong>. Cópialo y úsalo en la página de recuperación.</p>
                        
                        <p>O haz clic aquí: <a href='" . htmlspecialchars($app_url) . "' class='link'>Recuperar tu contraseña</a></p>
                        
                        <div class='warning'>
                            <strong>⚠️ Importante:</strong> Nunca compartas este código con nadie. 
                            CyberGame nunca te pedirá tu contraseña por email.
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p>© 2026 CyberGame - Sistema de Ciberseguridad</p>
                        <p>Este es un email automático, por favor no respondas.</p>
                    </div>
                </div>
            </body>
        </html>";
        
        file_put_contents($file_path, $email_html);
        error_log("Email guardado en: $file_path");
        return ['success' => true, 'method' => 'sandbox', 'file_path' => $file_path];
    }
    
    // ========== MODO SMTP REAL: Envío por SMTP ==========
    // Para producción, configura credenciales en EMAIL_CONFIG y cambia use_sandbox a false
    $mail = new PHPMailer(true);
    
    try {
        // Detectar configuración SMTP automáticamente
        $smtp_config = detectar_config_smtp(EMAIL_CONFIG['username']);
        
        // Configurar servidor SMTP
        $mail->isSMTP();
        $mail->Host = $smtp_config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_CONFIG['username'];
        $mail->Password = EMAIL_CONFIG['password'];
        $mail->SMTPSecure = $smtp_config['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $smtp_config['port'];
        
        // Direcciones
        $mail->setFrom(EMAIL_CONFIG['from_email'], EMAIL_CONFIG['from_name']);
        $mail->addAddress($email_destino, $nombre_usuario);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = 'CyberGame - Recupera tu contraseña';
        
        // Generar HTML del email
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $app_url = 'http://' . $host . '/cibergame/forgot.html';
        
        $mail->Body = "
        <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { text-align: center; color: #cf2e11; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
                    .content { color: #333; line-height: 1.6; margin: 20px 0; }
                    .codigo { 
                        background: #f0f0f0; 
                        padding: 15px; 
                        border-radius: 5px; 
                        font-family: 'Courier New', monospace; 
                        font-size: 28px; 
                        font-weight: bold;
                        text-align: center;
                        color: #cf2e11;
                        margin: 20px 0;
                        letter-spacing: 4px;
                    }
                    .link { color: #8b5cf6; text-decoration: none; }
                    .footer { text-align: center; color: #999; font-size: 12px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; }
                    .warning { background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>🛡️ CyberGame</div>
                    
                    <div class='content'>
                        <p>¡Hola <strong>" . htmlspecialchars($nombre_usuario) . "</strong>!</p>
                        
                        <p>Recibimos una solicitud para recuperar tu contraseña. Si no fuiste tú, ignora este email.</p>
                        
                        <p><strong>Tu código de verificación es:</strong></p>
                        <div class='codigo'>" . htmlspecialchars($codigo) . "</div>
                        
                        <p>Este código es válido por <strong>30 minutos</strong>. Cópialo y úsalo en la página de recuperación.</p>
                        
                        <p>O haz clic aquí: <a href='" . htmlspecialchars($app_url) . "' class='link'>Recuperar tu contraseña</a></p>
                        
                        <div class='warning'>
                            <strong>⚠️ Importante:</strong> Nunca compartas este código con nadie. 
                            CyberGame nunca te pedirá tu contraseña por email.
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p>© 2026 CyberGame - Sistema de Ciberseguridad</p>
                        <p>Este es un email automático, por favor no respondas.</p>
                    </div>
                </div>
            </body>
        </html>
        ";
        
        $mail->AltBody = "Tu código de verificación es: $codigo\n\nEste código es válido por 30 minutos.";
        
        // Enviar email
        $mail->send();
        error_log("Email enviado a $email_destino via " . $smtp_config['host']);
        return ['success' => true, 'method' => 'smtp', 'provider' => $smtp_config['host']];
        
    } catch (Exception $e) {
        error_log("Error al enviar email SMTP (" . $smtp_config['host'] . "): " . $mail->ErrorInfo);
        
        // ========== FALLBACK: Guardar en archivo para desarrollo ==========
        $emails_dir = __DIR__ . '/emails_debug';
        if (!is_dir($emails_dir)) {
            mkdir($emails_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $email_safe = str_replace(['@', '.'], ['_at_', '_'], $email_destino);
        $file_path = $emails_dir . '/' . $timestamp . '_' . $email_safe . '.html';
        
        // Crear HTML del email para guardar
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $app_url = 'http://' . $host . '/cibergame/forgot.html';
        $email_html = "
        <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .header { text-align: center; color: #cf2e11; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
                    .content { color: #333; line-height: 1.6; margin: 20px 0; }
                    .codigo { 
                        background: #f0f0f0; 
                        padding: 15px; 
                        border-radius: 5px; 
                        font-family: 'Courier New', monospace; 
                        font-size: 28px; 
                        font-weight: bold;
                        text-align: center;
                        color: #cf2e11;
                        margin: 20px 0;
                        letter-spacing: 4px;
                    }
                    .link { color: #8b5cf6; text-decoration: none; }
                    .footer { text-align: center; color: #999; font-size: 12px; margin-top: 30px; border-top: 1px solid #eee; padding-top: 15px; }
                    .warning { background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 15px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>🛡️ CyberGame</div>
                    
                    <div class='content'>
                        <p>¡Hola <strong>" . htmlspecialchars($nombre_usuario) . "</strong>!</p>
                        
                        <p>Recibimos una solicitud para recuperar tu contraseña. Si no fuiste tú, ignora este email.</p>
                        
                        <p><strong>Tu código de verificación es:</strong></p>
                        <div class='codigo'>" . htmlspecialchars($codigo) . "</div>
                        
                        <p>Este código es válido por <strong>30 minutos</strong>. Cópialo y úsalo en la página de recuperación.</p>
                        
                        <p>O haz clic aquí: <a href='" . htmlspecialchars($app_url) . "' class='link'>Recuperar tu contraseña</a></p>
                        
                        <div class='warning'>
                            <strong>⚠️ Importante:</strong> Nunca compartas este código con nadie. 
                            CyberGame nunca te pedirá tu contraseña por email.
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p>© 2026 CyberGame - Sistema de Ciberseguridad</p>
                        <p>Este es un email automático, por favor no respondas.</p>
                    </div>
                </div>
            </body>
        </html>";
        
        file_put_contents($file_path, $email_html);
        
        error_log("Email guardado en archivo: $file_path (SMTP falló para " . $smtp_config['host'] . ")");
        return ['success' => true, 'method' => 'file', 'file_path' => $file_path];
    }
}

// ============================================
// FUNCIÓN: Validar código (comparar con sha256 hash)
// ============================================
function validar_codigo_recuperacion($codigo_input, $codigo_hash_bd) {
    // El usuario ingresa el código en texto plano
    // Comparamos el sha256 del código ingresado con el hash guardado
    $hash_input = hash('sha256', $codigo_input);
    return hash_equals($hash_input, $codigo_hash_bd);
}
?>
