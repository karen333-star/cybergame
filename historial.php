<?php
require 'config.php';

validar_sesion();

$idUsuario = (int)$_SESSION['usuario_id'];
$nombreUsuario = $_SESSION['nombre_usuario'] ?? 'Jugador';
$seccion = $_GET['seccion'] ?? 'menu';
$partidaSeleccionada = isset($_GET['partida']) ? (int)$_GET['partida'] : 0;

// Filtros para partidas finalizadas
$filtroEstado = $_GET['filtro_estado'] ?? 'todas'; // 'todas', 'ganada', 'perdida'
$filtroFechaDesde = $_GET['filtro_fecha_desde'] ?? '';
$filtroFechaHasta = $_GET['filtro_fecha_hasta'] ?? '';

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

// Construir query con filtros
$condicionesFiltro = array();
$parametrosFiltro = array('i', $idUsuario);
$tiposParametros = 'i';

$condicionesFiltro[] = "p.id_usuario = ?";
$condicionesFiltro[] = "p.estado_partida IN ('ganada', 'perdida')";

// Filtro de estado
if ($filtroEstado !== 'todas') {
    $condicionesFiltro[] = "p.estado_partida = ?";
    $parametrosFiltro[0] .= 's';
    $parametrosFiltro[] = $filtroEstado;
    $tiposParametros .= 's';
}

// Filtro de fecha desde
if (!empty($filtroFechaDesde)) {
    $condicionesFiltro[] = "DATE(p.tiempo_inicial) >= ?";
    $parametrosFiltro[0] .= 's';
    $parametrosFiltro[] = $filtroFechaDesde;
    $tiposParametros .= 's';
}

// Filtro de fecha hasta
if (!empty($filtroFechaHasta)) {
    $condicionesFiltro[] = "DATE(p.tiempo_inicial) <= ?";
    $parametrosFiltro[0] .= 's';
    $parametrosFiltro[] = $filtroFechaHasta;
    $tiposParametros .= 's';
}

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
    WHERE " . implode(' AND ', $condicionesFiltro) . "
    ORDER BY p.tiempo_inicial DESC, p.id_partida DESC
";

$stmtPartidas = $conn->prepare($sqlPartidas);
if (!$stmtPartidas) {
    die('No se pudo cargar el historial.');
}

// Preparar binding de parámetros
$tipos = array_shift($parametrosFiltro);
$stmtPartidas->bind_param($tipos, ...$parametrosFiltro);
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
                    <div class="partidas-table-container">
                        <table class="partidas-table">
                            <thead>
                                <tr>
                                    <th style="width: 50%;">Métrica</th>
                                    <th style="width: 50%;">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="partida-row">
                                    <td><strong>Partidas finalizadas</strong></td>
                                    <td><?php echo (int)($resumenUsuario['partidas_finalizadas'] ?? 0); ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Partidas no finalizadas</strong></td>
                                    <td><?php echo (int)($resumenUsuario['partidas_no_finalizadas'] ?? 0); ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Partidas Ganadas</strong></td>
                                    <td><?php echo (int)($resumenUsuario['partidas_ganadas'] ?? 0); ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Partidas Perdidas</strong></td>
                                    <td><?php echo (int)($resumenUsuario['partidas_perdidas'] ?? 0); ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Promedio CIA</strong></td>
                                    <td><?php echo $resumenUsuario['promedio_cia'] !== null ? h(number_format((float)$resumenUsuario['promedio_cia'], 2)) : '-'; ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Promedio Confidencialidad (C)</strong></td>
                                    <td><?php echo $resumenUsuario['promedio_c'] !== null ? h(number_format((float)$resumenUsuario['promedio_c'], 2)) : '-'; ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Promedio Integridad (I)</strong></td>
                                    <td><?php echo $resumenUsuario['promedio_i'] !== null ? h(number_format((float)$resumenUsuario['promedio_i'], 2)) : '-'; ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Promedio Accesibilidad (A)</strong></td>
                                    <td><?php echo $resumenUsuario['promedio_a'] !== null ? h(number_format((float)$resumenUsuario['promedio_a'], 2)) : '-'; ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Promedio Presupuesto</strong></td>
                                    <td><?php echo $resumenUsuario['promedio_presupuesto'] !== null ? h(number_format((float)$resumenUsuario['promedio_presupuesto'], 2)) : '-'; ?></td>
                                </tr>
                                <tr class="partida-row">
                                    <td><strong>Promedio Despido</strong></td>
                                    <td><?php echo $resumenUsuario['promedio_despido'] !== null ? h(number_format((float)$resumenUsuario['promedio_despido'], 2)) : '-'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($seccion === 'partidas'): ?>
                <div class="history-panel-block">
                    <div class="partidas-header">
                        <h3>Mis partidas finalizadas</h3>
                        <div class="partidas-stats">
                            <span id="contador-partidas">Total: <?php echo count($partidas); ?></span>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="partidas-filters-container">
                        <form method="get" id="form-filtros" class="partidas-filters">
                            <input type="hidden" name="seccion" value="partidas">
                            
                            <div class="filter-group">
                                <label for="filtro_estado">Estado:</label>
                                <select name="filtro_estado" id="filtro_estado" class="filter-select">
                                    <option value="todas" <?php echo $filtroEstado === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                    <option value="ganada" <?php echo $filtroEstado === 'ganada' ? 'selected' : ''; ?>>Ganadas</option>
                                    <option value="perdida" <?php echo $filtroEstado === 'perdida' ? 'selected' : ''; ?>>Perdidas</option>
                                </select>
                            </div>

                            <button type="button" class="filter-toggle-btn" id="toggle-fecha-btn" onclick="toggleFiltroFecha()">
                                <span class="toggle-icon">+</span> Filtrar por fecha
                            </button>

                            <div class="filtros-fecha" id="filtros-fecha">
                                <div class="filter-group">
                                    <label for="filtro_fecha_desde">Desde:</label>
                                    <input type="date" name="filtro_fecha_desde" id="filtro_fecha_desde" class="filter-input" value="<?php echo h($filtroFechaDesde); ?>">
                                </div>

                                <div class="filter-group">
                                    <label for="filtro_fecha_hasta">Hasta:</label>
                                    <input type="date" name="filtro_fecha_hasta" id="filtro_fecha_hasta" class="filter-input" value="<?php echo h($filtroFechaHasta); ?>">
                                </div>
                            </div>

                            <div class="filter-group">
                                <button type="submit" class="filter-button">Aplicar filtros</button>
                                <a href="historial.php?seccion=partidas" class="filter-button filter-button-reset">Limpiar</a>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($partidas)): ?>
                        <div class="history-empty">Aún no tienes partidas finalizadas con esos filtros.</div>
                    <?php else: ?>
                        <!-- Tabla de partidas -->
                        <div class="partidas-table-container">
                            <table class="partidas-table">
                                <thead>
                                    <tr>
                                        <th class="col-fecha">Fecha</th>
                                        <th class="col-partida">Partida</th>
                                        <th class="col-estado">Estado</th>
                                        <th class="col-cia">CIA</th>
                                        <th class="col-presupuesto">Presupuesto</th>
                                        <th class="col-despido">Despido</th>
                                        <th class="col-acciones">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($partidas as $partida): ?>
                                        <tr class="partida-row" data-partida-id="<?php echo (int)$partida['id_partida']; ?>">
                                            <td class="col-fecha">
                                                <span class="fecha-valor"><?php echo date('d/m/Y', strtotime($partida['tiempo_inicial'])); ?></span>
                                                <span class="hora-valor"><?php echo date('H:i', strtotime($partida['tiempo_inicial'])); ?></span>
                                            </td>
                                            <td class="col-partida">
                                                #<?php echo (int)$partida['id_partida']; ?>
                                            </td>
                                            <td class="col-estado">
                                                <span class="status-pill <?php echo clase_estado((string)$partida['estado_partida']); ?>">
                                                    <?php echo $partida['estado_partida'] === 'ganada' ? '✓ Ganada' : '✗ Perdida'; ?>
                                                </span>
                                            </td>
                                            <td class="col-cia">
                                                <?php echo $partida['cia_final'] !== null ? (int)$partida['cia_final'] . '%' : '-'; ?>
                                            </td>
                                            <td class="col-presupuesto">
                                                <?php echo $partida['presupuesto_final'] !== null ? (int)$partida['presupuesto_final'] : '-'; ?>
                                            </td>
                                            <td class="col-despido">
                                                <?php echo $partida['despido_final'] !== null ? number_format((float)$partida['despido_final'], 1) . '%' : '-'; ?>
                                            </td>
                                            <td class="col-acciones">
                                                <button type="button" class="btn-expandir" onclick="toggleDetallPartida(this, <?php echo (int)$partida['id_partida']; ?>)">
                                                    <span class="btn-icon">▶</span> Detalles
                                                </button>
                                            </td>
                                        </tr>
                                        <!-- Fila expandible de detalles -->
                                        <tr class="partida-detail-row" id="detail-<?php echo (int)$partida['id_partida']; ?>">
                                            <td colspan="7">
                                                <div class="partida-detail-content">
                                                    <div class="detail-grid">
                                                        <div class="detail-section">
                                                            <h4>Datos básicos</h4>
                                                            <div class="detail-row">
                                                                <span class="label">Duración:</span>
                                                                <span class="value"><?php echo $partida['duracion_segundos'] !== null ? number_format((float)$partida['duracion_segundos'] / 60, 1) . ' min' : '-'; ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Rondas:</span>
                                                                <span class="value"><?php echo (int)$partida['rondas_jugadas']; ?>/<?php echo (int)$partida['max_rondas']; ?></span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Eventos:</span>
                                                                <span class="value"><?php echo (int)$partida['eventos_registrados']; ?></span>
                                                            </div>
                                                        </div>

                                                        <div class="detail-section">
                                                            <h4>Puntajes (Inicial → Final)</h4>
                                                            <div class="detail-row">
                                                                <span class="label">CIA:</span>
                                                                <span class="value">
                                                                    <?php echo $partida['cia_inicial']; ?>% → <?php echo $partida['cia_final'] !== null ? (int)$partida['cia_final'] . '%' : '-'; ?>
                                                                </span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Confidencialidad:</span>
                                                                <span class="value">
                                                                    <?php echo $partida['c_inicial'] !== null ? (int)$partida['c_inicial'] . '%' : '-'; ?> → <?php echo $partida['c_final'] !== null ? (int)$partida['c_final'] . '%' : '-'; ?>
                                                                </span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Integridad:</span>
                                                                <span class="value">
                                                                    <?php echo $partida['i_inicial'] !== null ? (int)$partida['i_inicial'] . '%' : '-'; ?> → <?php echo $partida['i_final'] !== null ? (int)$partida['i_final'] . '%' : '-'; ?>
                                                                </span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Accesibilidad:</span>
                                                                <span class="value">
                                                                    <?php echo $partida['a_inicial'] !== null ? (int)$partida['a_inicial'] . '%' : '-'; ?> → <?php echo $partida['a_final'] !== null ? (int)$partida['a_final'] . '%' : '-'; ?>
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="detail-section">
                                                            <h4>Presupuesto y Despido</h4>
                                                            <div class="detail-row">
                                                                <span class="label">Presupuesto:</span>
                                                                <span class="value">
                                                                    <?php echo (int)$partida['presupuesto_inicial']; ?> → <?php echo $partida['presupuesto_final'] !== null ? (int)$partida['presupuesto_final'] : '-'; ?>
                                                                </span>
                                                            </div>
                                                            <div class="detail-row">
                                                                <span class="label">Despido:</span>
                                                                <span class="value">
                                                                    <?php echo number_format((float)$partida['despido_inicial'], 1); ?>% → <?php echo $partida['despido_final'] !== null ? number_format((float)$partida['despido_final'], 1) . '%' : '-'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="detail-actions">
                                                        <a href="historial.php?seccion=detalle&partida=<?php echo (int)$partida['id_partida']; ?>" class="btn-ver-completo">Ver análisis completo →</a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
                        
                        <div class="partidas-table-container">
                            <table class="partidas-table">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Parámetro</th>
                                        <th style="width: 25%;">Valor Inicial</th>
                                        <th style="width: 25%;">Valor Final</th>
                                        <th style="width: 25%;">Cambio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="partida-row">
                                        <td><strong>CIA</strong></td>
                                        <td><?php echo (int)$partidaDetalle['cia_inicial']; ?>%</td>
                                        <td><?php echo $partidaDetalle['cia_final'] !== null ? (int)$partidaDetalle['cia_final'] . '%' : '-'; ?></td>
                                        <td style="text-align: center;">
                                            <?php 
                                                $cambio_cia = ($partidaDetalle['cia_final'] !== null && $partidaDetalle['cia_inicial'] !== null) 
                                                    ? (int)$partidaDetalle['cia_final'] - (int)$partidaDetalle['cia_inicial']
                                                    : 0;
                                                $color = $cambio_cia > 0 ? 'color: #9ef5ca;' : ($cambio_cia < 0 ? 'color: #ffc1d3;' : '');
                                            ?>
                                            <span style="<?php echo $color; ?>"><?php echo ($cambio_cia > 0 ? '+' : '') . $cambio_cia; ?>%</span>
                                        </td>
                                    </tr>
                                    <tr class="partida-row">
                                        <td><strong>Confidencialidad</strong></td>
                                        <td><?php echo $partidaDetalle['c_inicial'] !== null ? (int)$partidaDetalle['c_inicial'] . '%' : '-'; ?></td>
                                        <td><?php echo $partidaDetalle['c_final'] !== null ? (int)$partidaDetalle['c_final'] . '%' : '-'; ?></td>
                                        <td style="text-align: center;">
                                            <?php 
                                                $cambio_c = ($partidaDetalle['c_final'] !== null && $partidaDetalle['c_inicial'] !== null) 
                                                    ? (int)$partidaDetalle['c_final'] - (int)$partidaDetalle['c_inicial']
                                                    : 0;
                                            ?>
                                            <span><?php echo ($cambio_c > 0 ? '+' : '') . $cambio_c; ?>%</span>
                                        </td>
                                    </tr>
                                    <tr class="partida-row">
                                        <td><strong>Integridad</strong></td>
                                        <td><?php echo $partidaDetalle['i_inicial'] !== null ? (int)$partidaDetalle['i_inicial'] . '%' : '-'; ?></td>
                                        <td><?php echo $partidaDetalle['i_final'] !== null ? (int)$partidaDetalle['i_final'] . '%' : '-'; ?></td>
                                        <td style="text-align: center;">
                                            <?php 
                                                $cambio_i = ($partidaDetalle['i_final'] !== null && $partidaDetalle['i_inicial'] !== null) 
                                                    ? (int)$partidaDetalle['i_final'] - (int)$partidaDetalle['i_inicial']
                                                    : 0;
                                            ?>
                                            <span><?php echo ($cambio_i > 0 ? '+' : '') . $cambio_i; ?>%</span>
                                        </td>
                                    </tr>
                                    <tr class="partida-row">
                                        <td><strong>Accesibilidad</strong></td>
                                        <td><?php echo $partidaDetalle['a_inicial'] !== null ? (int)$partidaDetalle['a_inicial'] . '%' : '-'; ?></td>
                                        <td><?php echo $partidaDetalle['a_final'] !== null ? (int)$partidaDetalle['a_final'] . '%' : '-'; ?></td>
                                        <td style="text-align: center;">
                                            <?php 
                                                $cambio_a = ($partidaDetalle['a_final'] !== null && $partidaDetalle['a_inicial'] !== null) 
                                                    ? (int)$partidaDetalle['a_final'] - (int)$partidaDetalle['a_inicial']
                                                    : 0;
                                            ?>
                                            <span><?php echo ($cambio_a > 0 ? '+' : '') . $cambio_a; ?>%</span>
                                        </td>
                                    </tr>
                                    <tr class="partida-row">
                                        <td><strong>Presupuesto</strong></td>
                                        <td><?php echo (int)$partidaDetalle['presupuesto_inicial']; ?></td>
                                        <td><?php echo $partidaDetalle['presupuesto_final'] !== null ? (int)$partidaDetalle['presupuesto_final'] : '-'; ?></td>
                                        <td style="text-align: center;">
                                            <?php 
                                                $cambio_presupuesto = ($partidaDetalle['presupuesto_final'] !== null && $partidaDetalle['presupuesto_inicial'] !== null) 
                                                    ? (int)$partidaDetalle['presupuesto_final'] - (int)$partidaDetalle['presupuesto_inicial']
                                                    : 0;
                                            ?>
                                            <span><?php echo ($cambio_presupuesto > 0 ? '+' : '') . $cambio_presupuesto; ?></span>
                                        </td>
                                    </tr>
                                    <tr class="partida-row">
                                        <td><strong>Despido</strong></td>
                                        <td><?php echo h(number_format((float)$partidaDetalle['despido_inicial'], 2)); ?>%</td>
                                        <td><?php echo $partidaDetalle['despido_final'] !== null ? h(number_format((float)$partidaDetalle['despido_final'], 2)) . '%' : '-'; ?></td>
                                        <td style="text-align: center;">
                                            <?php 
                                                $cambio_despido = ($partidaDetalle['despido_final'] !== null && $partidaDetalle['despido_inicial'] !== null) 
                                                    ? (float)$partidaDetalle['despido_final'] - (float)$partidaDetalle['despido_inicial']
                                                    : 0;
                                            ?>
                                            <span><?php echo ($cambio_despido > 0 ? '+' : '') . h(number_format($cambio_despido, 2)); ?>%</span>
                                        </td>
                                    </tr>
                                    <tr class="partida-row">
                                        <td><strong>Duración</strong></td>
                                        <td colspan="2" style="text-align: center;">
                                            <?php echo !empty($partidaDetalle['duracion_segundos']) ? h(number_format(((float)$partidaDetalle['duracion_segundos']) / 60, 1)) . ' minutos' : '-'; ?>
                                        </td>
                                        <td style="text-align: center;">Estado: <?php echo h($partidaDetalle['estado_partida']); ?></td>
                                    </tr>
                                </tbody>
                            </table>
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
                                                <?php 
                                                    $cia_antes = (float)($evento['cia_antes'] ?? 0);
                                                    $cia_despues = (float)($evento['cia_despues'] ?? 0);
                                                    $cambio_cia = $cia_despues - $cia_antes;
                                                    $es_buena = $cambio_cia > 0;
                                                ?>
                                                <span class="event-choice-badge <?php echo $es_buena ? 'good' : 'questionable'; ?>">
                                                    <?php echo $es_buena ? '✓ Buena elección' : '✕ Elección cuestionable'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="event-text"><?php echo nl2br(h($evento['texto_correo'])); ?></p>
                                        
                                        <div class="partidas-table-container" style="margin-top: 10px; margin-bottom: 10px;">
                                            <table class="partidas-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 25%;">Respuesta del usuario</th>
                                                        <th style="width: 25%;">Feedback de respuesta</th>
                                                        <th style="width: 25%;">Feedback del escenario</th>
                                                        <th style="width: 25%;">Tiempo de respuesta</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="partida-row">
                                                        <td><?php echo h($evento['texto_opcion'] ?: 'Timeout automático'); ?></td>
                                                        <td><?php echo h($evento['feedback_mostrado'] ?: $evento['feedback_opcion'] ?: '-'); ?></td>
                                                        <td><?php echo h($evento['feedback_general'] ?: '-'); ?></td>
                                                        <td style="text-align: center;"><?php echo h($evento['fue_timeout'] ? 'Timeout' : ((int)$evento['tiempo_respuesta_segundos'] . 's')); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="partidas-table-container">
                                            <table class="partidas-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 50%;">Estado antes del turno</th>
                                                        <th style="width: 50%;">Estado después del turno</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="partida-row">
                                                        <td>
                                                            <small style="color: #a9b5ff;">CIA: <?php echo h(formatear_desglose_cia([
                                                                'confidencialidad' => $evento['c_antes'],
                                                                'integridad' => $evento['i_antes'],
                                                                'accesibilidad' => $evento['a_antes'],
                                                                'cia' => $evento['cia_antes']
                                                            ])); ?></small><br>
                                                            Presupuesto: <?php echo (int)$evento['presupuesto_antes']; ?> | Despido: <?php echo h(number_format((float)$evento['despido_antes'], 2)); ?>%
                                                        </td>
                                                        <td>
                                                            <small style="color: #a9b5ff;">CIA: <?php echo h(formatear_desglose_cia([
                                                                'confidencialidad' => $evento['c_despues'],
                                                                'integridad' => $evento['i_despues'],
                                                                'accesibilidad' => $evento['a_despues'],
                                                                'cia' => $evento['cia_despues']
                                                            ])); ?></small><br>
                                                            Presupuesto: <?php echo (int)$evento['presupuesto_despues']; ?> | Despido: <?php echo h(number_format((float)$evento['despido_despues'], 2)); ?>%
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
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
                        <div class="partidas-table-container">
                            <table class="partidas-table">
                                <thead>
                                    <tr>
                                        <th style="width: 8%;">Posición</th>
                                        <th style="width: 25%;">Usuario</th>
                                        <th style="width: 12%;">Partidas</th>
                                        <th style="width: 12%;">Ganadas</th>
                                        <th style="width: 12%;">CIA</th>
                                        <th style="width: 15%;">Presupuesto</th>
                                        <th style="width: 16%;">Despido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rankingUsuarios as $index => $usuario): ?>
                                        <tr class="partida-row">
                                            <td style="text-align: center; font-weight: 700; color: #a9b5ff;"><?php echo $index + 1; ?></td>
                                            <td><?php echo h($usuario['nombre_usuario']); ?></td>
                                            <td style="text-align: center;"><?php echo (int)$usuario['partidas_finalizadas']; ?></td>
                                            <td style="text-align: center;"><?php echo (int)$usuario['partidas_ganadas']; ?></td>
                                            <td style="text-align: center;"><?php echo $usuario['promedio_cia'] !== null ? h(number_format((float)$usuario['promedio_cia'], 2)) : '-'; ?></td>
                                            <td style="text-align: center;"><?php echo $usuario['promedio_presupuesto'] !== null ? h(number_format((float)$usuario['promedio_presupuesto'], 2)) : '-'; ?></td>
                                            <td style="text-align: center;"><?php echo $usuario['promedio_despido'] !== null ? h(number_format((float)$usuario['promedio_despido'], 2)) . '%' : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toggle detalle de partida expandible
        function toggleDetallPartida(button, idPartida) {
            const detailRow = document.getElementById('detail-' + idPartida);
            const isExpanded = detailRow.classList.contains('expanded');
            
            // Cerrar todas las otras filas de detalles
            document.querySelectorAll('.partida-detail-row').forEach(row => {
                row.classList.remove('expanded');
            });
            
            // Resetear iconos de botones
            document.querySelectorAll('.btn-expandir .btn-icon').forEach(icon => {
                icon.textContent = '▶';
            });
            
            // Mostrar la fila de detalles seleccionada
            if (!isExpanded) {
                detailRow.classList.add('expanded');
                button.querySelector('.btn-icon').textContent = '▼';
            }
        }

        // Toggle filtro de fecha
        function toggleFiltroFecha() {
            const filtrosFecha = document.getElementById('filtros-fecha');
            const toggleBtn = document.getElementById('toggle-fecha-btn');
            const toggleIcon = toggleBtn.querySelector('.toggle-icon');
            
            if (filtrosFecha.classList.contains('visible')) {
                filtrosFecha.classList.remove('visible');
                toggleIcon.textContent = '▶';
            } else {
                filtrosFecha.classList.add('visible');
                toggleIcon.textContent = '▼';
            }
        }

        // Mostrar filtro de fecha si hay valores activos
        window.addEventListener('load', function() {
            const fechaDesde = document.getElementById('filtro_fecha_desde').value;
            const fechaHasta = document.getElementById('filtro_fecha_hasta').value;
            
            if (fechaDesde || fechaHasta) {
                document.getElementById('filtros-fecha').classList.add('visible');
                document.querySelector('#toggle-fecha-btn .toggle-icon').textContent = '▼';
            }
        });

        // Permitir hacer click en la fila para abrir detalles
        document.querySelectorAll('.partida-row').forEach(row => {
            row.addEventListener('click', function(e) {
                // No disparar si se hace click en el botón
                if (!e.target.closest('.btn-expandir')) {
                    const button = this.querySelector('.btn-expandir');
                    button.click();
                }
            });
        });
    </script>
</body>
</html>
