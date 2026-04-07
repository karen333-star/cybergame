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
    <link rel="stylesheet" href="profile.css">
</head>
<body class="profile-page">
    <div class="container profile-shell">
        <header class="profile-topbar">
            <div class="profile-topbar-left">↳_ CYBER_SIM // OS <span>/ PERFIL</span></div>
            <div class="profile-topbar-right">● ENLACE SEGURO</div>
        </header>

        <div class="profile-grid">
            <aside class="profile-sidebar">
                <div class="profile-panel profile-brand-panel">
                    <div class="profile-kicker">MI PERFIL</div>
                    <div class="profile-avatar" aria-hidden="true">◌</div>
                    <h2 class="profile-user-name"><?php echo htmlspecialchars($userProfile['nombre_usuario'] ?? '-'); ?></h2>
                    <div class="profile-user-role">Operadora de respuesta empresarial</div>
                </div>

                <div class="profile-panel profile-account-panel">
                    <h3 class="profile-panel-title">Datos de Cuenta</h3>
                    <div class="profile-data-list">
                        <div class="profile-data-row">
                            <span class="profile-data-icon" aria-hidden="true">◌</span>
                            <span class="profile-data-label">Nombre de usuario</span>
                            <strong class="profile-data-value"><?php echo htmlspecialchars($userProfile['nombre_usuario'] ?? '-'); ?></strong>
                        </div>
                        <div class="profile-data-row">
                            <span class="profile-data-icon" aria-hidden="true">✉</span>
                            <span class="profile-data-label">Correo</span>
                            <strong class="profile-data-value"><?php echo htmlspecialchars($userProfile['email'] ?? '-'); ?></strong>
                        </div>
                        <div class="profile-data-row">
                            <span class="profile-data-icon" aria-hidden="true">⌚</span>
                            <span class="profile-data-label">Registrado desde</span>
                            <strong class="profile-data-value">
                                <?php 
                                    $fecha = $userProfile['creado_en'] ?? null;
                                    if ($fecha) {
                                        echo htmlspecialchars(date('d/m/Y H:i', strtotime($fecha)));
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </strong>
                        </div>
                    </div>

                    <div class="profile-mini-grid">
                        <div class="profile-mini-card">
                            <span>Clearance</span>
                            <strong>Nivel A2</strong>
                        </div>
                        <div class="profile-mini-card">
                            <span>Trazabilidad</span>
                            <strong>Verificada</strong>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="profile-main">
                <?php if ($mensajeExito): ?>
                    <div class="profile-alert profile-alert-success">✓ <?php echo htmlspecialchars($mensajeExito); ?></div>
                <?php endif; ?>

                <?php if ($mensajeError): ?>
                    <div class="profile-alert profile-alert-error">✗ <?php echo htmlspecialchars($mensajeError); ?></div>
                <?php endif; ?>

                <section class="profile-hero">
                    <div class="profile-kicker profile-kicker-main">CENTRO DE IDENTIDAD</div>
                    <h1>Controla tu cuenta, revisa tu progreso y accede a tus módulos críticos.</h1>
                    <p>Todo tu estado operativo concentrado en un panel de acceso rápido con lectura táctica.</p>
                </section>

                <section class="profile-panel profile-actions-panel">
                    <h3 class="profile-panel-title">ACCIONES PRINCIPALES</h3>
                    <div class="profile-actions-grid">
                        <button type="button" class="profile-action-card profile-action-purple" onclick="toggleSeccion('cambiar-password')">
                            <span class="profile-action-icon">⌂</span>
                            <span class="profile-action-content">
                                <strong>Cambiar Contraseña</strong>
                                <small>Refuerza el acceso a tu cuenta</small>
                            </span>
                            <span class="profile-action-badge">SEGURIDAD</span>
                        </button>

                        <button type="button" class="profile-action-card profile-action-cyan" onclick="toggleSeccion('mejorar-section')">
                            <span class="profile-action-icon">▥</span>
                            <span class="profile-action-content">
                                <strong>¿En qué debo mejorar?</strong>
                                <small>Consulta recomendaciones estratégicas</small>
                            </span>
                            <span class="profile-action-badge">ANÁLISIS</span>
                        </button>
                    </div>
                </section>

                <section id="cambiar-password" class="profile-panel profile-expandable" style="display: none;">
                    <h3 class="profile-panel-title">Cambiar Contraseña</h3>
                    <form method="POST" class="profile-form">
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
                </section>

                <section id="mejorar-section" class="profile-panel profile-expandable" style="display: none;">
                    <h3 class="profile-panel-title">En qué debo mejorar?</h3>
                    <?php if (empty($top5)): ?>
                        <p class="profile-empty-state">Aún no hay suficientes datos. Completa al menos 3 partidas finalizadas en cada escenario.</p>
                    <?php else: ?>
                        <div class="profile-weakness-list">
                            <?php foreach ($top5 as $idx => $item): ?>
                                <article class="profile-weakness-card">
                                    <div class="profile-weakness-head">
                                        <div>
                                            <strong>#<?php echo $idx + 1; ?> - <?php echo htmlspecialchars($item['titulo_correo']); ?></strong>
                                            <small>Tipo: <?php echo htmlspecialchars($item['tipo_escenario']); ?></small>
                                        </div>
                                    </div>

                                    <div class="profile-weakness-grid">
                                        <div>
                                            <span>Veces jugado</span>
                                            <strong><?php echo (int)$item['veces_jugado']; ?></strong>
                                        </div>
                                        <div>
                                            <span>Tasa de error</span>
                                            <strong><?php echo htmlspecialchars($item['tasa_error_pct']); ?>%</strong>
                                        </div>
                                        <div>
                                            <span>Puntos perdidos</span>
                                            <strong><?php echo htmlspecialchars($item['score_total']); ?></strong>
                                        </div>
                                        <div>
                                            <span>Sistema más afectado</span>
                                            <strong>
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
                                            </strong>
                                        </div>
                                    </div>

                                    <div class="profile-recommendation">
                                        <strong>Recomendación:</strong>
                                        <p><?php echo htmlspecialchars($feedbackPorEscenario[$item['id_escenario']] ?? 'Revisa tu estrategia.'); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="profile-panel profile-quick-panel">
                    <h3 class="profile-panel-title">Acceso Rápido</h3>
                    <div class="profile-quick-grid">
                        <a href="historial.php" class="menu-btn profile-quick-btn">
                            <span class="profile-quick-icon" aria-hidden="true">⌁</span>
                            <span class="profile-quick-copy"><strong>Ver Historial</strong><small>Resultados, comparativas y desempeño</small></span>
                        </a>
                        <a href="menu.php" class="menu-btn profile-quick-btn">
                            <span class="profile-quick-icon" aria-hidden="true">⌂</span>
                            <span class="profile-quick-copy"><strong>Volver al Menú</strong><small>Regresa al centro de control principal</small></span>
                        </a>
                        <a href="menu.php?logout=1" class="menu-btn logout-btn profile-quick-btn">
                            <span class="profile-quick-icon" aria-hidden="true">⇥</span>
                            <span class="profile-quick-copy"><strong>Cerrar Sesión</strong><small>Salir del sistema de forma segura</small></span>
                        </a>
                    </div>
                </section>
            </main>
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
