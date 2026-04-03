<?php
require 'config.php';

// Validar sesión
validar_sesion();

$idUsuario = (int)$_SESSION['usuario_id'];
$nombreUsuario = $_SESSION['nombre_usuario'] ?? 'Jugador';
$email = $_SESSION['email'] ?? '';

$mensajeExito = '';
$mensajeError = '';

// ============================================
// PROCESAR CAMBIO DE CONTRASEÑA
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_password') {
    $passwordActual = $_POST['password_actual'] ?? '';
    $passwordNueva = $_POST['password_nueva'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validaciones
    if (empty($passwordActual) || empty($passwordNueva) || empty($passwordConfirm)) {
        $mensajeError = 'Completa todos los campos de contraseña.';
    } elseif ($passwordNueva !== $passwordConfirm) {
        $mensajeError = 'Las contraseñas nuevas no coinciden.';
    } elseif (!validar_contraseña($passwordNueva)) {
        $mensajeError = 'Contraseña débil: mínimo 7 caracteres, 1 mayúscula y 1 número.';
    } elseif ($passwordActual === $passwordNueva) {
        $mensajeError = 'La nueva contraseña no puede ser igual a la actual.';
    } else {
        // Validar que la contraseña actual sea correcta
        $sqlUser = "SELECT password_hash FROM usuarios WHERE id_usuario = ?";
        $stmtUser = $conn->prepare($sqlUser);
        if (!$stmtUser) {
            $mensajeError = 'Error al verificar contraseña actual.';
        } else {
            $stmtUser->bind_param('i', $idUsuario);
            $stmtUser->execute();
            $rowUser = $stmtUser->get_result()->fetch_assoc();

            if (!$rowUser || !password_verify($passwordActual, $rowUser['password_hash'])) {
                $mensajeError = 'Contraseña actual incorrecta.';
            } else {
                // Cambiar contraseña
                $newHash = password_hash($passwordNueva, PASSWORD_BCRYPT);
                $sqlUpdate = "UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                if (!$stmtUpdate) {
                    $mensajeError = 'Error al actualizar contraseña.';
                } else {
                    $stmtUpdate->bind_param('si', $newHash, $idUsuario);
                    if ($stmtUpdate->execute()) {
                        $mensajeExito = 'Contraseña cambiada exitosamente.';
                    } else {
                        $mensajeError = 'Error al guardar nueva contraseña.';
                    }
                }
            }
        }
    }
}

// ============================================
// OBTENER DATOS DE USUARIO
// ============================================
$sqlProfile = "SELECT id_usuario, nombre_usuario, email, creado_en FROM usuarios WHERE id_usuario = ?";
$stmtProfile = $conn->prepare($sqlProfile);
$stmtProfile->bind_param('i', $idUsuario);
$stmtProfile->execute();
$userProfile = $stmtProfile->get_result()->fetch_assoc();

// ============================================
// CALCULAR TOP 5 EN QUÉ DEBO MEJORAR
// ============================================
$top5 = [];

$sqlTop5 = "
    SELECT
        e.id_escenario,
        e.titulo_correo,
        e.tipo_escenario,
        COUNT(DISTINCT pe.id_partida_escenario) AS veces_jugado,
        SUM(CASE WHEN (ep.cia_antes > ep.cia_despues OR ep.despido_despues > ep.despido_antes) THEN 1 ELSE 0 END) AS errores,
        ROUND(
            SUM(
                GREATEST(0, CAST(ep.cia_antes AS SIGNED) - CAST(ep.cia_despues AS SIGNED)) +
                GREATEST(0, CAST(ep.despido_despues AS SIGNED) - CAST(ep.despido_antes AS SIGNED))
            ),
            2
        ) AS score_total,
        ROUND(
            SUM(
                GREATEST(0, CAST(ep.cia_antes AS SIGNED) - CAST(ep.cia_despues AS SIGNED)) +
                GREATEST(0, CAST(ep.despido_despues AS SIGNED) - CAST(ep.despido_antes AS SIGNED))
            ) / COUNT(DISTINCT pe.id_partida_escenario),
            2
        ) AS score_promedio,
        ROUND(
            SUM(CASE WHEN (ep.cia_antes > ep.cia_despues OR ep.despido_despues > ep.despido_antes) THEN 1 ELSE 0 END) /
            COUNT(DISTINCT pe.id_partida_escenario) * 100,
            1
        ) AS tasa_error_pct
    FROM escenarios e
    INNER JOIN partida_escenarios pe ON pe.id_escenario = e.id_escenario
    INNER JOIN partidas p ON p.id_partida = pe.id_partida
    INNER JOIN eventos_partida ep ON ep.id_partida_escenario = pe.id_partida_escenario
    WHERE p.id_usuario = ?
      AND p.estado_partida IN ('ganada', 'perdida')
      AND ep.fue_timeout = 0
    GROUP BY e.id_escenario, e.titulo_correo, e.tipo_escenario
    HAVING COUNT(DISTINCT pe.id_partida_escenario) >= 3
    ORDER BY score_total DESC, tasa_error_pct DESC, veces_jugado DESC
    LIMIT 5
";

$stmtTop5 = $conn->prepare($sqlTop5);
if ($stmtTop5) {
    $stmtTop5->bind_param('i', $idUsuario);
    $stmtTop5->execute();
    $top5 = $stmtTop5->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================
// OBTENER PATRÓN PRINCIPAL DE ERROR POR ESCENARIO
// ============================================
$patronesPorEscenario = [];

foreach ($top5 as $escenario) {
    $idEscenario = (int)$escenario['id_escenario'];
    
    $sqlPatron = "
        SELECT
            SUM(GREATEST(0, CAST(ep.cia_antes AS SIGNED) - CAST(ep.cia_despues AS SIGNED))) AS penalizacion_cia,
            SUM(GREATEST(0, CAST(ep.despido_despues AS SIGNED) - CAST(ep.despido_antes AS SIGNED))) AS penalizacion_despido
        FROM eventos_partida ep
        INNER JOIN partida_escenarios pe ON pe.id_partida_escenario = ep.id_partida_escenario
        INNER JOIN partidas p ON p.id_partida = pe.id_partida
        WHERE pe.id_escenario = ?
          AND p.id_usuario = ?
          AND p.estado_partida IN ('ganada', 'perdida')
          AND ep.fue_timeout = 0
          AND (ep.cia_antes > ep.cia_despues OR ep.despido_despues > ep.despido_antes)
    ";
    
    $stmtPatron = $conn->prepare($sqlPatron);
    if ($stmtPatron) {
        $stmtPatron->bind_param('ii', $idEscenario, $idUsuario);
        $stmtPatron->execute();
        $patron = $stmtPatron->get_result()->fetch_assoc();
        $patronesPorEscenario[$idEscenario] = $patron;
    }
}

// ============================================
// OBTENER FEEDBACK DE MEJORA (OPCIÓN MÁS ELEGIDA CON ERROR)
// ============================================
$feedbackPorEscenario = [];

foreach ($top5 as $escenario) {
    $idEscenario = (int)$escenario['id_escenario'];
    
    $sqlFeedback = "
        SELECT oe.feedback_opcion
        FROM eventos_partida ep
        INNER JOIN partida_escenarios pe ON pe.id_partida_escenario = ep.id_partida_escenario
        INNER JOIN partidas p ON p.id_partida = pe.id_partida
        INNER JOIN opciones_escenario oe ON oe.id_opcion = ep.id_opcion_elegida
        WHERE pe.id_escenario = ?
          AND p.id_usuario = ?
          AND p.estado_partida IN ('ganada', 'perdida')
          AND ep.fue_timeout = 0
          AND (ep.cia_antes > ep.cia_despues OR ep.despido_despues > ep.despido_antes)
        GROUP BY oe.id_opcion
        ORDER BY COUNT(*) DESC
        LIMIT 1
    ";
    
    $stmtFeedback = $conn->prepare($sqlFeedback);
    if ($stmtFeedback) {
        $stmtFeedback->bind_param('ii', $idEscenario, $idUsuario);
        $stmtFeedback->execute();
        $feedbackRow = $stmtFeedback->get_result()->fetch_assoc();
        $feedbackPorEscenario[$idEscenario] = $feedbackRow['feedback_opcion'] ?? 'Revisa tu estrategia en este escenario.';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberGame - Perfil</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1 id="main-title">🛡️ CyberGame</h1>

        <div class="card">
            <h2>Mi Perfil</h2>

            <!-- Mensajes de éxito/error -->
            <?php if ($mensajeExito): ?>
                <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    ✓ <?php echo htmlspecialchars($mensajeExito); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensajeError): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                    ✗ <?php echo htmlspecialchars($mensajeError); ?>
                </div>
            <?php endif; ?>

            <!-- ============================================
                 SECCIÓN 1: DATOS DE CUENTA
                 ============================================ -->
            <div style="margin-bottom: 30px; padding: 16px; background: #f5f5f5; border-radius: 8px;">
                <h3>Datos de Cuenta</h3>
                <div style="margin: 12px 0;">
                    <label style="font-weight: 600; color: #333;">Nombre de usuario:</label>
                    <p style="margin: 4px 0; color: #222; font-size: 15px;">
                        <?php echo htmlspecialchars($userProfile['nombre_usuario'] ?? '-'); ?>
                    </p>
                </div>
                <div style="margin: 12px 0;">
                    <label style="font-weight: 600; color: #333;">Correo:</label>
                    <p style="margin: 4px 0; color: #222; font-size: 15px;">
                        <?php echo htmlspecialchars($userProfile['email'] ?? '-'); ?>
                    </p>
                </div>
                <div style="margin: 12px 0;">
                    <label style="font-weight: 600; color: #333;">Registrado desde:</label>
                    <p style="margin: 4px 0; color: #222; font-size: 15px;">
                        <?php 
                            $fecha = $userProfile['creado_en'] ?? null;
                            if ($fecha) {
                                echo htmlspecialchars(date('d/m/Y H:i', strtotime($fecha)));
                            } else {
                                echo '-';
                            }
                        ?>
                    </p>
                </div>
            </div>

            <!-- ============================================
                 SECCIÓN 2: CAMBIAR CONTRASEÑA (COLLAPSIBLE)
                 ============================================ -->
            <div style="margin-bottom: 30px; padding: 16px; background: #f5f5f5; border-radius: 8px;">
                <button type="button" onclick="toggleSeccion('cambiar-password')" style="width: 100%; padding: 12px; background: #8b5cf6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 15px;">
                    🔐 Cambiar Contraseña
                </button>
                
                <div id="cambiar-password" style="display: none; margin-top: 16px;">
                    <form method="POST" style="display: flex; flex-direction: column; gap: 12px;">
                        <input type="hidden" name="accion" value="cambiar_password">
                        
                        <div class="form-group">
                            <label for="password_actual">Contraseña actual:</label>
                            <input type="password" id="password_actual" name="password_actual" required>
                        </div>

                        <div class="form-group">
                            <label for="password_nueva">Contraseña nueva:</label>
                            <input type="password" id="password_nueva" name="password_nueva" required placeholder="7+ caracteres, 1 mayúscula, 1 número">
                        </div>

                        <div class="form-group">
                            <label for="password_confirm">Confirmar contraseña:</label>
                            <input type="password" id="password_confirm" name="password_confirm" required>
                        </div>

                        <button type="submit" class="btn">Cambiar Contraseña</button>
                    </form>
                </div>
            </div>

            <!-- ============================================
                 SECCIÓN 3: EN QUÉ DEBO MEJORAR (COLLAPSIBLE)
                 ============================================ -->
            <div style="margin-bottom: 30px; padding: 16px; background: #f5f5f5; border-radius: 8px;">
                <button type="button" onclick="toggleSeccion('mejorar-section')" style="width: 100%; padding: 12px; background: #8b5cf6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 15px;">
                    📈 En qué debo mejorar?
                </button>
                
                <div id="mejorar-section" style="display: none; margin-top: 16px;">
                    <?php if (empty($top5)): ?>
                        <p style="color: #555; text-align: center; padding: 20px; font-size: 14px;">
                            Aún no hay suficientes datos. Completa al menos 3 partidas finalizadas en cada escenario.
                        </p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <?php foreach ($top5 as $idx => $item): ?>
                                <div style="padding: 12px; background: white; border-left: 4px solid #8b5cf6; border-radius: 4px;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                        <div>
                                            <strong style="color: #222;">#<?php echo $idx + 1; ?> - <?php echo htmlspecialchars($item['titulo_correo']); ?></strong>
                                            <br/>
                                            <small style="color: #555;">Tipo: <?php echo htmlspecialchars($item['tipo_escenario']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 8px 0; font-size: 12px;">
                                        <div style="color: #333;">
                                            <strong style="color: #222;">Veces jugado</strong>
                                            <br/><?php echo (int)$item['veces_jugado']; ?>
                                        </div>
                                        <div style="color: #333;">
                                            <strong style="color: #222;">Tasa de error</strong>
                                            <br/><?php echo htmlspecialchars($item['tasa_error_pct']); ?>%
                                        </div>
                                        <div style="color: #333;">
                                            <strong style="color: #222;">Puntos perdidos</strong>
                                            <br/><?php echo htmlspecialchars($item['score_total']); ?>
                                        </div>
                                        <div style="color: #333;">
                                            <strong style="color: #222;">Sistema más afectado</strong>
                                            <br/>
                                            <?php 
                                                $patron = $patronesPorEscenario[$item['id_escenario']] ?? null;
                                                $penCia = (float)($patron['penalizacion_cia'] ?? 0);
                                                $penDesp = (float)($patron['penalizacion_despido'] ?? 0);
                                                if ($penCia > $penDesp) {
                                                    echo 'CIA ↓';
                                                } elseif ($penDesp > $penCia) {
                                                    echo 'Despido ↑';
                                                } else {
                                                    echo 'Mixto';
                                                }
                                            ?>
                                        </div>
                                    </div>

                                    <div style="margin-top: 8px; padding: 8px; background: #e8f4ff; border-radius: 4px; font-size: 13px; color: #222;">
                                        <strong style="color: #1a5a96;">Recomendación:</strong>
                                        <br/>
                                        <?php echo htmlspecialchars($feedbackPorEscenario[$item['id_escenario']] ?? 'Revisa tu estrategia.'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================
                 SECCIÓN 4: ACCESO RÁPIDO
                 ============================================ -->
            <div style="margin-bottom: 20px; padding: 16px; background: #f5f5f5; border-radius: 8px;">
                <h3>Acceso Rápido</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="historial.php" class="menu-btn">📊 Ver Historial Completo</a>
                    <a href="menu.php" class="menu-btn">🏠 Volver al Menú</a>
                    <a href="menu.php?logout=1" class="menu-btn logout-btn">🚪 Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSeccion(idSeccion) {
            const seccion = document.getElementById(idSeccion);
            if (seccion) {
                if (seccion.style.display === 'none' || seccion.style.display === '') {
                    seccion.style.display = 'block';
                } else {
                    seccion.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>
