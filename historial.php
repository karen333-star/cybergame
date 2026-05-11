<?php
require 'config.php';

validar_sesion();

$idUsuario = (int)$_SESSION['usuario_id'];
$nombreUsuario = $_SESSION['nombre_usuario'] ?? 'Jugador';
$seccion = $_GET['seccion'] ?? 'menu';
$partidaSeleccionada = isset($_GET['partida']) ? (int)$_GET['partida'] : 0;

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function formatear_fecha(?string $fecha): string {
    if (empty($fecha)) {
        return '-';
    }

    $timestamp = strtotime($fecha);
    if ($timestamp === false) {
        return h($fecha);
    }

    return date('d/m/Y H:i', $timestamp);
}

function formatear_desglose_cia(?array $datos): string {
    $confidencialidad = $datos['confidencialidad'] ?? null;
    $integridad = $datos['integridad'] ?? null;
    $accesibilidad = $datos['accesibilidad'] ?? null;
    $cia = $datos['cia'] ?? null;

    $textoConfidencialidad = $confidencialidad !== null ? ((int)$confidencialidad . '%') : 'No disponible';
    $textoIntegridad = $integridad !== null ? ((int)$integridad . '%') : 'No disponible';
    $textoAccesibilidad = $accesibilidad !== null ? ((int)$accesibilidad . '%') : 'No disponible';
    $textoCia = $cia !== null ? ((int)$cia . '%') : 'No disponible';

    return 'Confidencialidad: ' . $textoConfidencialidad
        . ' | Integridad: ' . $textoIntegridad
        . ' | Accesibilidad: ' . $textoAccesibilidad
        . ' | CIA promedio: ' . $textoCia;
}

$sqlResumenUsuario = "
    SELECT
        SUM(CASE WHEN estado_partida IN ('ganada', 'perdida') THEN 1 ELSE 0 END) AS partidas_finalizadas,
        SUM(CASE WHEN estado_partida NOT IN ('ganada', 'perdida') THEN 1 ELSE 0 END) AS partidas_no_finalizadas,
        ROUND(AVG(CASE WHEN estado_partida IN ('ganada', 'perdida') THEN cia_final END), 2) AS promedio_cia,
        ROUND(AVG(CASE WHEN estado_partida IN ('ganada', 'perdida') THEN c_final END), 2) AS promedio_c,
        ROUND(AVG(CASE WHEN estado_partida IN ('ganada', 'perdida') THEN i_final END), 2) AS promedio_i,
        ROUND(AVG(CASE WHEN estado_partida IN ('ganada', 'perdida') THEN a_final END), 2) AS promedio_a,
        ROUND(AVG(CASE WHEN estado_partida IN ('ganada', 'perdida') THEN presupuesto_final END), 2) AS promedio_presupuesto,
        ROUND(AVG(CASE WHEN estado_partida IN ('ganada', 'perdida') THEN despido_final END), 2) AS promedio_despido,
        SUM(CASE WHEN estado_partida = 'ganada' THEN 1 ELSE 0 END) AS partidas_ganadas,
        SUM(CASE WHEN estado_partida = 'perdida' THEN 1 ELSE 0 END) AS partidas_perdidas
    FROM partidas
    WHERE id_usuario = ?
";
$stmtResumenUsuario = $conn->prepare($sqlResumenUsuario);
if (!$stmtResumenUsuario) {
    die('No se pudo cargar el historial.');
}
$stmtResumenUsuario->bind_param('i', $idUsuario);
$stmtResumenUsuario->execute();
$resumenUsuario = $stmtResumenUsuario->get_result()->fetch_assoc() ?: [];

$sqlPartidas = "
    SELECT
        p.id_partida,
        p.estado_partida,
        CAST(GREATEST(1, LEAST(100, ROUND((COALESCE(p.c_inicial, 0) + COALESCE(p.i_inicial, 0) + COALESCE(p.a_inicial, 0)) / 3, 0))) AS UNSIGNED) AS cia_inicial,
        p.c_inicial,
        p.i_inicial,
        p.a_inicial,
        p.presupuesto_inicial,
        p.despido_inicial,
        p.cia_final,
        p.c_final,
        p.i_final,
        p.a_final,
        p.presupuesto_final,
        p.despido_final,
        p.tiempo_inicial,
        p.tiempo_final,
        p.duracion_segundos,
        p.max_rondas,
        (
            SELECT COUNT(*)
            FROM partida_escenarios pe
            WHERE pe.id_partida = p.id_partida
        ) AS rondas_jugadas,
        (
            SELECT COUNT(ep.id_evento)
            FROM partida_escenarios pe
            INNER JOIN eventos_partida ep ON ep.id_partida_escenario = pe.id_partida_escenario
            WHERE pe.id_partida = p.id_partida
        ) AS eventos_registrados
    FROM partidas p
    WHERE p.id_usuario = ?
      AND p.estado_partida IN ('ganada', 'perdida')
    ORDER BY p.tiempo_inicial DESC, p.id_partida DESC
";
$stmtPartidas = $conn->prepare($sqlPartidas);
if (!$stmtPartidas) {
    die('No se pudo cargar el historial.');
}
$stmtPartidas->bind_param('i', $idUsuario);
$stmtPartidas->execute();
$partidas = $stmtPartidas->get_result()->fetch_all(MYSQLI_ASSOC);

$partidaDetalle = null;
$eventos = [];

if ($partidaSeleccionada > 0) {
    $sqlDetalle = "
        SELECT
            p.id_partida,
            p.estado_partida,
            CAST(GREATEST(1, LEAST(100, ROUND((COALESCE(p.c_inicial, 0) + COALESCE(p.i_inicial, 0) + COALESCE(p.a_inicial, 0)) / 3, 0))) AS UNSIGNED) AS cia_inicial,
            p.c_inicial,
            p.i_inicial,
            p.a_inicial,
            p.presupuesto_inicial,
            p.despido_inicial,
            p.cia_final,
            p.c_final,
            p.i_final,
            p.a_final,
            p.presupuesto_final,
            p.despido_final,
            p.tiempo_inicial,
            p.tiempo_final,
            p.duracion_segundos,
            p.max_rondas
        FROM partidas p
        WHERE p.id_partida = ?
          AND p.id_usuario = ?
          AND p.estado_partida IN ('ganada', 'perdida')
        LIMIT 1
    ";
    $stmtDetalle = $conn->prepare($sqlDetalle);
    if ($stmtDetalle) {
        $stmtDetalle->bind_param('ii', $partidaSeleccionada, $idUsuario);
        $stmtDetalle->execute();
        $partidaDetalle = $stmtDetalle->get_result()->fetch_assoc() ?: null;
    }

    if ($partidaDetalle) {
        $sqlEventos = "
            SELECT
                pe.orden_en_partida,
                pe.notificado_en,
                e.tipo_escenario,
                e.titulo_correo,
                e.texto_correo,
                e.feedback_general,
                r.correo AS remitente_correo,
                r.nombre_mostrado AS remitente_nombre,
                o.texto_opcion,
                o.feedback_opcion,
                ep.tiempo_respuesta_segundos,
                ep.fue_timeout,
                ep.cia_antes,
                ep.c_antes,
                ep.i_antes,
                ep.a_antes,
                ep.presupuesto_antes,
                ep.despido_antes,
                ep.cia_despues,
                ep.c_despues,
                ep.i_despues,
                ep.a_despues,
                ep.presupuesto_despues,
                ep.despido_despues,
                ep.feedback_mostrado,
                ep.fecha_evento
            FROM eventos_partida ep
            INNER JOIN partida_escenarios pe ON pe.id_partida_escenario = ep.id_partida_escenario
            INNER JOIN escenarios e ON e.id_escenario = pe.id_escenario
            LEFT JOIN remitentes_email r ON r.id_remitente = e.id_remitente
            LEFT JOIN opciones_escenario o ON o.id_opcion = ep.id_opcion_elegida
            WHERE pe.id_partida = ?
            ORDER BY pe.orden_en_partida ASC, ep.id_evento ASC
        ";
        $stmtEventos = $conn->prepare($sqlEventos);
        if ($stmtEventos) {
            $stmtEventos->bind_param('i', $partidaSeleccionada);
            $stmtEventos->execute();
            $eventos = $stmtEventos->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}

$sqlRanking = "
        SELECT
                u.id_usuario,
                u.nombre_usuario,
                (
                        SELECT COUNT(*)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida IN ('ganada', 'perdida')
                ) AS partidas_finalizadas,
                (
                        SELECT COUNT(*)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida = 'ganada'
                ) AS partidas_ganadas,
                (
                        SELECT COUNT(*)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida = 'perdida'
                ) AS partidas_perdidas,
                (
                        SELECT ROUND(AVG(p.cia_final), 2)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida IN ('ganada', 'perdida')
                ) AS promedio_cia,
                (
                        SELECT ROUND(AVG(p.c_final), 2)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida IN ('ganada', 'perdida')
                ) AS promedio_c,
                (
                        SELECT ROUND(AVG(p.i_final), 2)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida IN ('ganada', 'perdida')
                ) AS promedio_i,
                (
                        SELECT ROUND(AVG(p.a_final), 2)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida IN ('ganada', 'perdida')
                ) AS promedio_a,
                (
                        SELECT ROUND(AVG(p.presupuesto_final), 2)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida IN ('ganada', 'perdida')
                ) AS promedio_presupuesto,
                (
                        SELECT ROUND(AVG(p.despido_final), 2)
                        FROM partidas p
                        WHERE p.id_usuario = u.id_usuario
                            AND p.estado_partida IN ('ganada', 'perdida')
                ) AS promedio_despido,
                (
                    SELECT ROUND(
                        COALESCE(AVG(p.cia_final), 0)
                        + COALESCE(AVG(p.presupuesto_final), 0)
                        + (100 - COALESCE(AVG(p.despido_final), 0)),
                        2
                    )
                    FROM partidas p
                    WHERE p.id_usuario = u.id_usuario
                        AND p.estado_partida IN ('ganada', 'perdida')
                ) AS puntaje_ranking
        FROM usuarios u
            WHERE EXISTS (
                SELECT 1
                FROM partidas p
                WHERE p.id_usuario = u.id_usuario
                    AND p.estado_partida IN ('ganada', 'perdida')
            )
            ORDER BY puntaje_ranking DESC, partidas_finalizadas DESC, promedio_cia DESC, promedio_presupuesto DESC, promedio_despido ASC, u.nombre_usuario ASC
";
$resultadoRanking = $conn->query($sqlRanking);
$rankingUsuarios = $resultadoRanking ? $resultadoRanking->fetch_all(MYSQLI_ASSOC) : [];

function clase_estado(string $estado): string {
    if ($estado === 'ganada') {
        return 'estado-ganada';
    }
    if ($estado === 'perdida') {
        return 'estado-perdida';
    }
    return 'estado-en-curso';
}

function es_activa(string $seccionActual, string $esperada): string {
    return $seccionActual === $esperada ? 'is-active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberGame - Historial</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="hotorial.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700;800&family=Share+Tech+Mono&display=swap" rel="stylesheet">
</head>
<body class="history-body history-cyber">
    <div class="history-grid-overlay" aria-hidden="true"></div>
    <div class="container history-page">
        <h1 id="main-title">CYBER_GAME</h1>

        <header class="history-nav-strip">
            <div class="history-nav-brand">CYBER_GAME</div>
            <div class="history-nav-actions">
                <a href="menu.php" class="menu-btn">Volver al menú</a>
                <a href="menu.php?logout=1" class="menu-btn logout-btn">Cerrar sesión</a>
            </div>
        </header>

        <div class="card history-shell">
            <div class="history-topbar">
                <div>
                    <h2>Historial</h2>
                    <p>Usuario: <?php echo h($nombreUsuario); ?></p>
                </div>
            </div>

            <div class="history-menu">
                <button type="button" class="history-menu-item <?php echo es_activa($seccion, 'menu'); ?>" onclick="window.location.href='historial.php?seccion=menu'">Inicio del historial</button>
                <button type="button" class="history-menu-item <?php echo es_activa($seccion, 'resumen'); ?>" onclick="window.location.href='historial.php?seccion=resumen'">Resumen general</button>
                <button type="button" class="history-menu-item <?php echo ($seccion === 'partidas' || $seccion === 'detalle') ? 'is-active' : ''; ?>" onclick="window.location.href='historial.php?seccion=partidas'">Mis partidas finalizadas</button>
                <button type="button" class="history-menu-item <?php echo es_activa($seccion, 'ranking'); ?>" onclick="window.location.href='historial.php?seccion=ranking'">Ranking de usuarios</button>
            </div>

            <?php if ($seccion === 'menu'): ?>
                <div class="history-landing">
                    <div class="history-landing-card">
                        <div class="history-landing-icon">i</div>
                        <h3>¿Qué puedes ver aquí?</h3>
                        <p>Usa los botones de arriba para navegar por cada sección del historial.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($seccion === 'resumen'): ?>
                <div class="history-panel-block">
                    <h3>Resumen general</h3>
                    <div class="history-summary-grid">
                        <article class="history-summary-card">
                            <span>Partidas finalizadas</span>
                            <strong><?php echo (int)($resumenUsuario['partidas_finalizadas'] ?? 0); ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Partidas no finalizadas</span>
                            <strong><?php echo (int)($resumenUsuario['partidas_no_finalizadas'] ?? 0); ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Promedio CIA</span>
                            <strong><?php echo $resumenUsuario['promedio_cia'] !== null ? h(number_format((float)$resumenUsuario['promedio_cia'], 2)) : '-'; ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Promedio Confidencialidad (C)</span>
                            <strong><?php echo $resumenUsuario['promedio_c'] !== null ? h(number_format((float)$resumenUsuario['promedio_c'], 2)) : '-'; ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Promedio Integridad (I)</span>
                            <strong><?php echo $resumenUsuario['promedio_i'] !== null ? h(number_format((float)$resumenUsuario['promedio_i'], 2)) : '-'; ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Promedio Accesibilidad (A)</span>
                            <strong><?php echo $resumenUsuario['promedio_a'] !== null ? h(number_format((float)$resumenUsuario['promedio_a'], 2)) : '-'; ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Promedio Presupuesto</span>
                            <strong><?php echo $resumenUsuario['promedio_presupuesto'] !== null ? h(number_format((float)$resumenUsuario['promedio_presupuesto'], 2)) : '-'; ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Promedio Despido</span>
                            <strong><?php echo $resumenUsuario['promedio_despido'] !== null ? h(number_format((float)$resumenUsuario['promedio_despido'], 2)) : '-'; ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Partidas Ganadas</span>
                            <strong><?php echo (int)($resumenUsuario['partidas_ganadas'] ?? 0); ?></strong>
                        </article>
                        <article class="history-summary-card">
                            <span>Partidas Perdidas</span>
                            <strong><?php echo (int)($resumenUsuario['partidas_perdidas'] ?? 0); ?></strong>
                        </article>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($seccion === 'partidas'): ?>
                <div class="history-panel-block">
                    <h3>Mis partidas finalizadas</h3>

                    <?php if (empty($partidas)): ?>
                        <div class="history-empty">Aún no tienes partidas finalizadas registradas.</div>
                    <?php else: ?>
                        <div class="history-list">
                            <?php foreach ($partidas as $partida): ?>
                                <div class="history-item <?php echo $partidaSeleccionada === (int)$partida['id_partida'] ? 'is-active' : ''; ?>">
                                    <div class="history-item-head">
                                        <div>
                                            <strong>Partida #<?php echo (int)$partida['id_partida']; ?></strong>
                                            <div class="history-item-subtitle">
                                                <span>Finalizada</span>
                                                <span><?php echo formatear_fecha($partida['tiempo_inicial'] ?? null); ?> - <?php echo formatear_fecha($partida['tiempo_final'] ?? null); ?></span>
                                            </div>
                                        </div>
                                        <span class="status-pill <?php echo clase_estado((string)$partida['estado_partida']); ?>"><?php echo h($partida['estado_partida']); ?></span>
                                    </div>
                                    <div class="history-item-grid">
                                        <div>
                                            <span>Estado</span>
                                            <strong><?php echo h($partida['estado_partida']); ?></strong>
                                        </div>
                                        <div>
                                            <span>Rondas</span>
                                            <strong><?php echo (int)$partida['rondas_jugadas']; ?>/<?php echo (int)$partida['max_rondas']; ?></strong>
                                        </div>
                                        <div>
                                            <span>Eventos</span>
                                            <strong><?php echo (int)$partida['eventos_registrados']; ?></strong>
                                        </div>
                                        <div>
                                            <span>CIA final</span>
                                            <strong><?php echo $partida['cia_final'] !== null ? (int)$partida['cia_final'] . '%' : '-'; ?></strong>
                                        </div>
                                        <div>
                                            <span>Presupuesto</span>
                                            <strong><?php echo $partida['presupuesto_final'] !== null ? (int)$partida['presupuesto_final'] : '-'; ?></strong>
                                        </div>
                                        <div>
                                            <span>Despido</span>
                                            <strong><?php echo $partida['despido_final'] !== null ? h(number_format((float)$partida['despido_final'], 2)) . '%' : '-'; ?></strong>
                                        </div>
                                    </div>
                                    <div class="history-item-actions">
                                        <a class="history-item-button" href="historial.php?seccion=detalle&partida=<?php echo (int)$partida['id_partida']; ?>">Ver detalle</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($seccion === 'detalle'): ?>
                <div class="history-panel-block">
                    <div class="history-item-actions" style="margin-top: 0; margin-bottom: 14px; justify-content: flex-start;">
                        <a class="history-item-button" href="historial.php?seccion=partidas">Volver a partidas finalizadas</a>
                    </div>

                    <?php if ($partidaSeleccionada <= 0): ?>
                        <div class="history-empty">Selecciona una partida desde "Mis partidas finalizadas" para ver su detalle.</div>
                    <?php elseif (!$partidaDetalle): ?>
                        <div class="history-empty">No se pudo cargar la partida seleccionada. Verifica que pertenezca a tu usuario y que esté finalizada.</div>
                    <?php else: ?>
                        <h3>Detalle de la partida #<?php echo (int)$partidaDetalle['id_partida']; ?></h3>
                        <div class="history-detail-summary">
                            <div><span>Duración</span><strong><?php echo !empty($partidaDetalle['duracion_segundos']) ? h(number_format(((float)$partidaDetalle['duracion_segundos']) / 60, 1)) . ' min' : '-'; ?></strong></div>
                            <div><span>Estado</span><strong><?php echo h($partidaDetalle['estado_partida']); ?></strong></div>
                        </div>
                        <div class="history-detail-summary history-detail-summary-small">
                            <div><span>CIA inicial</span><strong><?php echo (int)$partidaDetalle['cia_inicial']; ?>%</strong></div>
                            <div><span>C inicial</span><strong><?php echo $partidaDetalle['c_inicial'] !== null ? (int)$partidaDetalle['c_inicial'] . '%' : 'Puntaje individual no disponible'; ?></strong></div>
                            <div><span>I inicial</span><strong><?php echo $partidaDetalle['i_inicial'] !== null ? (int)$partidaDetalle['i_inicial'] . '%' : 'Puntaje individual no disponible'; ?></strong></div>
                            <div><span>A inicial</span><strong><?php echo $partidaDetalle['a_inicial'] !== null ? (int)$partidaDetalle['a_inicial'] . '%' : 'Puntaje individual no disponible'; ?></strong></div>
                            <div><span>Presupuesto inicial</span><strong><?php echo (int)$partidaDetalle['presupuesto_inicial']; ?></strong></div>
                            <div><span>Despido inicial</span><strong><?php echo h(number_format((float)$partidaDetalle['despido_inicial'], 2)); ?>%</strong></div>
                            <div><span>CIA final</span><strong><?php echo $partidaDetalle['cia_final'] !== null ? (int)$partidaDetalle['cia_final'] . '%' : '-'; ?></strong></div>
                            <div><span>C final</span><strong><?php echo $partidaDetalle['c_final'] !== null ? (int)$partidaDetalle['c_final'] . '%' : 'Puntaje individual no disponible'; ?></strong></div>
                            <div><span>I final</span><strong><?php echo $partidaDetalle['i_final'] !== null ? (int)$partidaDetalle['i_final'] . '%' : 'Puntaje individual no disponible'; ?></strong></div>
                            <div><span>A final</span><strong><?php echo $partidaDetalle['a_final'] !== null ? (int)$partidaDetalle['a_final'] . '%' : 'Puntaje individual no disponible'; ?></strong></div>
                            <div><span>Presupuesto final</span><strong><?php echo $partidaDetalle['presupuesto_final'] !== null ? (int)$partidaDetalle['presupuesto_final'] : '-'; ?></strong></div>
                            <div><span>Despido final</span><strong><?php echo $partidaDetalle['despido_final'] !== null ? h(number_format((float)$partidaDetalle['despido_final'], 2)) . '%' : '-'; ?></strong></div>
                        </div>

                        <?php if (empty($eventos)): ?>
                            <div class="history-empty">Esta partida no tiene eventos registrados.</div>
                        <?php else: ?>
                            <div class="history-events">
                                <?php foreach ($eventos as $evento): ?>
                                    <article class="history-event">
                                        <div class="history-event-head">
                                            <div>
                                                <span class="event-kicker">Turno <?php echo (int)$evento['orden_en_partida']; ?> · <?php echo h($evento['tipo_escenario']); ?></span>
                                                <h4><?php echo h($evento['titulo_correo']); ?></h4>
                                            </div>
                                            <div class="event-meta">
                                                <span><?php echo h($evento['remitente_nombre'] ?: $evento['remitente_correo']); ?></span>
                                                <span><?php echo h($evento['fue_timeout'] ? 'Timeout' : ((int)$evento['tiempo_respuesta_segundos'] . 's')); ?></span>
                                            </div>
                                        </div>
                                        <p class="event-text"><?php echo nl2br(h($evento['texto_correo'])); ?></p>
                                        <div class="event-grid">
                                            <div>
                                                <span>Respuesta</span>
                                                <strong><?php echo h($evento['texto_opcion'] ?: 'Timeout automático'); ?></strong>
                                            </div>
                                            <div>
                                                <span>Feedback de respuesta</span>
                                                <strong><?php echo h($evento['feedback_mostrado'] ?: $evento['feedback_opcion'] ?: '-'); ?></strong>
                                            </div>
                                            <div>
                                                <span>Feedback del escenario</span>
                                                <strong><?php echo h($evento['feedback_general'] ?: '-'); ?></strong>
                                            </div>
                                        </div>
                                        <div class="event-grid event-grid-small">
                                            <div>
                                                <span>Antes</span>
                                                <strong><?php echo h(formatear_desglose_cia([
                                                    'confidencialidad' => $evento['c_antes'],
                                                    'integridad' => $evento['i_antes'],
                                                    'accesibilidad' => $evento['a_antes'],
                                                    'cia' => $evento['cia_antes']
                                                ])); ?> | Presupuesto <?php echo (int)$evento['presupuesto_antes']; ?> | Despido <?php echo h(number_format((float)$evento['despido_antes'], 2)); ?>%</strong>
                                            </div>
                                            <div>
                                                <span>Después</span>
                                                <strong><?php echo h(formatear_desglose_cia([
                                                    'confidencialidad' => $evento['c_despues'],
                                                    'integridad' => $evento['i_despues'],
                                                    'accesibilidad' => $evento['a_despues'],
                                                    'cia' => $evento['cia_despues']
                                                ])); ?> | Presupuesto <?php echo (int)$evento['presupuesto_despues']; ?> | Despido <?php echo h(number_format((float)$evento['despido_despues'], 2)); ?>%</strong>
                                            </div>
                                        </div>
                                        <div class="event-date">Registrado: <?php echo formatear_fecha($evento['fecha_evento'] ?? null); ?></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($seccion === 'ranking'): ?>
                <div class="history-panel-block">
                    <h3>Ranking de usuarios</h3>

                    <?php if (empty($rankingUsuarios)): ?>
                        <div class="history-empty">No hay usuarios para mostrar.</div>
                    <?php else: ?>
                        <div class="ranking-list">
                            <?php foreach ($rankingUsuarios as $index => $usuario): ?>
                                <article class="ranking-item">
                                    <div class="ranking-position">#<?php echo $index + 1; ?></div>
                                    <div class="ranking-main">
                                        <strong><?php echo h($usuario['nombre_usuario']); ?></strong>
                                        <span><?php echo (int)$usuario['partidas_finalizadas']; ?> partidas finalizadas</span>
                                    </div>
                                    <div class="ranking-stats">
                                        <div><span>Ganadas</span><strong><?php echo (int)$usuario['partidas_ganadas']; ?></strong></div>
                                        <div><span>Perdidas</span><strong><?php echo (int)$usuario['partidas_perdidas']; ?></strong></div>
                                        <div><span>CIA</span><strong><?php echo $usuario['promedio_cia'] !== null ? h(number_format((float)$usuario['promedio_cia'], 2)) : '-'; ?></strong></div>
                                        <div><span>Confidencialidad</span><strong><?php echo $usuario['promedio_c'] !== null ? h(number_format((float)$usuario['promedio_c'], 2)) : '-'; ?></strong></div>
                                        <div><span>Integridad</span><strong><?php echo $usuario['promedio_i'] !== null ? h(number_format((float)$usuario['promedio_i'], 2)) : '-'; ?></strong></div>
                                        <div><span>Accesibilidad</span><strong><?php echo $usuario['promedio_a'] !== null ? h(number_format((float)$usuario['promedio_a'], 2)) : '-'; ?></strong></div>
                                        <div><span>Presupuesto</span><strong><?php echo $usuario['promedio_presupuesto'] !== null ? h(number_format((float)$usuario['promedio_presupuesto'], 2)) : '-'; ?></strong></div>
                                        <div><span>Despido</span><strong><?php echo $usuario['promedio_despido'] !== null ? h(number_format((float)$usuario['promedio_despido'], 2)) : '-'; ?></strong></div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
