<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

$accion = $_POST['accion'] ?? null;

// =====================================================
// 1. SOLICITAR CÓDIGO DE RECUPERACIÓN
// =====================================================
if ($accion === 'solicitar_codigo') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['ok' => false, 'error' => 'EMAIL_VACIO'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar que existe en BD
    $sql = "SELECT id_usuario, nombre_usuario FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_DB'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // POR SEGURIDAD: No revelar si el email existe (anti-enumeración)
        echo json_encode(['ok' => true, 'mensaje' => 'Si el email existe, recibirás instrucciones'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $usuario = $result->fetch_assoc();
    $id_usuario = $usuario['id_usuario'];
    $nombre_usuario = $usuario['nombre_usuario'];

    // ========== GENERAR CÓDIGO DE 6 DÍGITOS ==========
    $codigo = generar_codigo_6_digitos();
    
    // ========== HASHEAR CÓDIGO PARA BD ==========
    // Nunca guardamos el código en texto plano
    $codigo_hash = hash('sha256', $codigo);
    
    // Expiración: 30 minutos desde ahora
    $expira_en = date('Y-m-d H:i:s', time() + 30 * 60);
    
    // ========== GUARDAR HASH EN BD ==========
    $sqlToken = "INSERT INTO tokens_recuperacion (id_usuario, token_hash, expira_en, usado, creado_en)
                 VALUES (?, ?, ?, 0, NOW())";
    $stmtToken = $conn->prepare($sqlToken);
    if (!$stmtToken) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_GUARDAR_TOKEN'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmtToken->bind_param("iss", $id_usuario, $codigo_hash, $expira_en);
    
    if (!$stmtToken->execute()) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_GUARDAR_TOKEN'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ========== ENVIAR EMAIL ==========
    $resultado_email = enviar_email_recuperacion($email, $codigo, $nombre_usuario);
    
    if ($resultado_email['success']) {
        $mensaje = 'Se ha enviado un código de verificación a tu correo';
        if ($resultado_email['method'] === 'sandbox') {
            $mensaje = 'Se ha generado un código de verificación: ' . $codigo;
        } elseif ($resultado_email['method'] === 'file') {
            $mensaje .= ' (fallback)';
        } elseif ($resultado_email['method'] === 'smtp') {
            $mensaje .= ' (via ' . $resultado_email['provider'] . ')';
        }
        
        echo json_encode([
            'ok' => true,
            'mensaje' => $mensaje,
            'codigo_debug' => $codigo  // Siempre mostrar el código para testing
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Este caso no debería ocurrir con el fallback
        echo json_encode([
            'ok' => false,
            'error' => 'ERROR_ENVIO_EMAIL'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// =====================================================
// 2. VERIFICAR CÓDIGO Y OBTENER TOKEN DE SESIÓN
// =====================================================
if ($accion === 'verificar_codigo') {
    $email = trim($_POST['email'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');

    if (empty($email) || empty($codigo)) {
        echo json_encode(['ok' => false, 'error' => 'CAMPOS_VACIOS'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar formato: debe ser 6 dígitos
    if (!preg_match('/^\d{6}$/', $codigo)) {
        echo json_encode(['ok' => false, 'error' => 'CODIGO_INVALIDO'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Buscar usuario
    $sqlUser = "SELECT id_usuario FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1";
    $stmtUser = $conn->prepare($sqlUser);
    if (!$stmtUser) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_DB'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmtUser->bind_param("s", $email);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();

    if ($resUser->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => 'USUARIO_NO_EXISTE'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $usuario = $resUser->fetch_assoc();
    $id_usuario = $usuario['id_usuario'];

    // ========== BUSCAR CÓDIGO ACTIVO ==========
    $ahora = date('Y-m-d H:i:s');
    $sqlBuscaToken = "
        SELECT id_token, token_hash, creado_en
        FROM tokens_recuperacion
        WHERE id_usuario = ?
          AND usado = 0
          AND expira_en > ?
        ORDER BY creado_en DESC
        LIMIT 1
    ";
    $stmtBuscaToken = $conn->prepare($sqlBuscaToken);
    if (!$stmtBuscaToken) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_DB'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmtBuscaToken->bind_param("is", $id_usuario, $ahora);
    $stmtBuscaToken->execute();
    $resBuscaToken = $stmtBuscaToken->get_result();

    if ($resBuscaToken->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => 'CODIGO_EXPIRADO'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tokenRecord = $resBuscaToken->fetch_assoc();
    $id_token = $tokenRecord['id_token'];
    $codigo_hash_bd = $tokenRecord['token_hash'];

    // ========== VALIDAR CÓDIGO ==========
    // Comparar con hash usando hash_equals para evitar timing attacks
    if (!validar_codigo_recuperacion($codigo, $codigo_hash_bd)) {
        echo json_encode(['ok' => false, 'error' => 'CODIGO_INVALIDO'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ========== GENERAR TOKEN TEMPORAL DE SESIÓN ==========
    $temp_reset_token = bin2hex(random_bytes(16));

    // ========== GUARDAR EN SESIÓN ==========
    $_SESSION['temp_reset_email'] = $email;
    $_SESSION['temp_reset_token'] = $temp_reset_token;
    $_SESSION['temp_reset_id_token'] = $id_token;

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Código verificado correctamente',
        'temp_token' => $temp_reset_token
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =====================================================
// 3. RESETEAR CONTRASEÑA
// =====================================================
if ($accion === 'resetear_contraseña') {
    $temp_token = trim($_POST['temp_token'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($temp_token) || empty($password) || empty($password_confirm)) {
        echo json_encode(['ok' => false, 'error' => 'CAMPOS_VACIOS'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar token de sesión
    if (!isset($_SESSION['temp_reset_token']) || $_SESSION['temp_reset_token'] !== $temp_token) {
        echo json_encode(['ok' => false, 'error' => 'SESION_INVALIDA'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar contraseñas
    if ($password !== $password_confirm) {
        echo json_encode(['ok' => false, 'error' => 'PASS_NO_COINCIDEN'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar requisitos
    if (!validar_contraseña($password)) {
        echo json_encode(['ok' => false, 'error' => 'PASS_DEBIL'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Obtener datos de sesión
    $email = $_SESSION['temp_reset_email'];
    $id_token = $_SESSION['temp_reset_id_token'];

    // Confirmar usuario
    $sqlUser = "SELECT id_usuario FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1";
    $stmtUser = $conn->prepare($sqlUser);
    if (!$stmtUser) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_DB'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmtUser->bind_param("s", $email);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();

    if ($resUser->num_rows === 0) {
        echo json_encode(['ok' => false, 'error' => 'USUARIO_NO_EXISTE'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $usuario = $resUser->fetch_assoc();
    $id_usuario = $usuario['id_usuario'];

    // Actualizar contraseña
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $sqlUpdate = "UPDATE usuarios SET password_hash = ? WHERE id_usuario = ? LIMIT 1";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    if (!$stmtUpdate) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_DB'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmtUpdate->bind_param("si", $password_hash, $id_usuario);
    $stmtUpdate->execute();

    // Marcar token como usado
    $sqlMarkUsed = "UPDATE tokens_recuperacion SET usado = 1, usado_en = NOW() WHERE id_token = ? LIMIT 1";
    $stmtMarkUsed = $conn->prepare($sqlMarkUsed);
    if (!$stmtMarkUsed) {
        echo json_encode(['ok' => false, 'error' => 'ERROR_MARCAR_TOKEN'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmtMarkUsed->bind_param("i", $id_token);
    $stmtMarkUsed->execute();

    // Limpiar sesión
    unset($_SESSION['temp_reset_email']);
    unset($_SESSION['temp_reset_token']);
    unset($_SESSION['temp_reset_id_token']);

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Contraseña actualizada exitosamente'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'ACCION_INVALIDA'], JSON_UNESCAPED_UNICODE);
exit;
?>
