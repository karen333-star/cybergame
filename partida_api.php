<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'NO_SESSION']);
    exit;
}

function responder($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function repartir_cia_inicial(int $cia): array {
    $cia = max(0, min(100, $cia));
    $base = intdiv($cia, 3);
    $resto = $cia % 3;

    return [
        'confidencialidad' => $base + ($resto > 0 ? 1 : 0),
        'integridad' => $base + ($resto > 1 ? 1 : 0),
        'accesibilidad' => $base,
    ];
}

function calcular_cia_promedio(float $confidencialidad, float $integridad, float $accesibilidad): int {
    return (int)round(($confidencialidad + $integridad + $accesibilidad) / 3);
}

function aplicar_modificador_signado(float $deltaBase, float $factorPositivo, float $factorNegativo): float {
    return ($deltaBase > 0) ? ($deltaBase * $factorPositivo) : ($deltaBase * $factorNegativo);
}

function aplicar_modificadores(float $ciaActual, float $presupuestoActual, float $deltaConfBase, float $deltaInteBase, float $deltaAccBase, float $deltaPresupuestoBase, float $deltaDespidoBase): array {
    $deltaConfAplicado = $deltaConfBase;
    $deltaInteAplicado = $deltaInteBase;
    $deltaAccAplicado = $deltaAccBase;
    $deltaPresupuestoAplicado = $deltaPresupuestoBase;
    $deltaDespidoAplicado = $deltaDespidoBase;

    if ($presupuestoActual > 95) {
        $deltaDespidoAplicado = aplicar_modificador_signado($deltaDespidoBase, 1.8, 0.2);
    } elseif ($presupuestoActual > 80) {
        $deltaDespidoAplicado = aplicar_modificador_signado($deltaDespidoBase, 1.6, 0.4);
    } elseif ($presupuestoActual > 70) {
        $deltaDespidoAplicado = aplicar_modificador_signado($deltaDespidoBase, 1.4, 0.7);
    }

    if ($ciaActual < 10) {
        $deltaDespidoAplicado = aplicar_modificador_signado($deltaDespidoAplicado, 2.0, 0.3);
        $deltaPresupuestoAplicado = aplicar_modificador_signado($deltaPresupuestoBase, 0.3, 2.0);
    } elseif ($ciaActual < 20) {
        $deltaDespidoAplicado = aplicar_modificador_signado($deltaDespidoAplicado, 1.5, 0.6);
        $deltaPresupuestoAplicado = aplicar_modificador_signado($deltaPresupuestoBase, 0.6, 1.5);
    } elseif ($ciaActual < 30) {
        $deltaDespidoAplicado = aplicar_modificador_signado($deltaDespidoAplicado, 1.3, 0.8);
        $deltaPresupuestoAplicado = aplicar_modificador_signado($deltaPresupuestoBase, 0.8, 1.3);
    }

    return [
        'delta_confidencialidad' => round($deltaConfAplicado),
        'delta_integridad' => round($deltaInteAplicado),
        'delta_accesibilidad' => round($deltaAccAplicado),
        'delta_presupuesto' => round($deltaPresupuestoAplicado),
        'delta_despido' => round($deltaDespidoAplicado),
    ];
}

function calcular_ajuste_trimestral_por_despido(float $despido): int {
    if ($despido <= 10) return 20;
    if ($despido <= 20) return 15;
    if ($despido <= 30) return 10;
    if ($despido <= 50) return 7;
    if ($despido <= 60) return 4;
    if ($despido <= 80) return 0;
    return -5;
}

function evaluar_estado_final(float $cia, float $presupuesto, float $despido): array {
    if ($cia <= 0) {
        return ['resultado' => 'perdida', 'motivo' => 'cia_cero'];
    }

    if ($presupuesto <= 0) {
        return ['resultado' => 'perdida', 'motivo' => 'presupuesto_cero'];
    }

    if ($despido >= 100) {
        return ['resultado' => 'perdida', 'motivo' => 'despido_cien'];
    }

    if ($despido > 95 && $cia < 75) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_95_cia_lt_75'];
    if ($despido > 90 && $cia < 70) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_90_cia_lt_70'];
    if ($despido > 80 && $cia < 60) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_80_cia_lt_60'];
    if ($despido > 70 && $cia < 50) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_70_cia_lt_50'];
    if ($despido > 60 && $cia < 40) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_60_cia_lt_40'];
    if ($despido > 50 && $cia < 30) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_50_cia_lt_30'];
    if ($despido > 40 && $cia < 20) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_40_cia_lt_20'];
    if ($despido > 30 && $cia < 10) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_30_cia_lt_10'];
    if ($despido > 20 && $cia < 5) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_20_cia_lt_5'];
    if ($despido > 10 && $cia < 2) return ['resultado' => 'perdida', 'motivo' => 'despido_gt_10_cia_lt_2'];

    if ($cia == 100 && $despido < 80) return ['resultado' => 'ganada', 'motivo' => 'cia_100_despido_lt_80'];
    if ($cia > 95 && $despido < 70) return ['resultado' => 'ganada', 'motivo' => 'cia_gt_95_despido_lt_70'];
    if ($cia > 95 && $despido < 60) return ['resultado' => 'ganada', 'motivo' => 'cia_gt_95_despido_lt_60'];
    if ($cia > 90 && $despido < 50) return ['resultado' => 'ganada', 'motivo' => 'cia_gt_90_despido_lt_50'];

    return ['resultado' => 'en_curso', 'motivo' => 'sin_condicion'];
}

function cerrar_partida(mysqli $conn, int $idPartida, string $resultado, int $ciaFinal, int $presupuestoFinal, int $despidoFinal, int $confidencialidadFinal, int $integridadFinal, int $accesibilidadFinal): void {
    $sqlActualizar = "
        UPDATE partidas
        SET estado_partida = ?,
            cia_final = ?,
            c_final = ?,
            i_final = ?,
            a_final = ?,
            presupuesto_final = ?,
            despido_final = ?,
            tiempo_final = NOW(),
            duracion_segundos = TIMESTAMPDIFF(SECOND, tiempo_inicial, NOW())
        WHERE id_partida = ?
        LIMIT 1
    ";
    $stmtActualizar = $conn->prepare($sqlActualizar);
    if (!$stmtActualizar) {
        throw new RuntimeException('Error prepare cerrar partida: ' . $conn->error);
    }

    $stmtActualizar->bind_param('siiiiiii', $resultado, $ciaFinal, $confidencialidadFinal, $integridadFinal, $accesibilidadFinal, $presupuestoFinal, $despidoFinal, $idPartida);
    $stmtActualizar->execute();
}

function obtener_estado_actual_partida(mysqli $conn, int $idPartida): array {
    $sqlPartidaGetter = "
        SELECT
            p.cia_inicial,
            p.c_inicial,
            p.i_inicial,
            p.a_inicial,
            p.presupuesto_inicial,
            p.despido_inicial,
            ep.cia_despues,
            ep.c_despues,
            ep.i_despues,
            ep.a_despues,
            ep.presupuesto_despues,
            ep.despido_despues
        FROM partidas p
        LEFT JOIN partida_escenarios pe ON pe.id_partida = p.id_partida
        LEFT JOIN eventos_partida ep ON ep.id_partida_escenario = pe.id_partida_escenario
        WHERE p.id_partida = ?
        ORDER BY pe.orden_en_partida DESC, ep.id_evento DESC
        LIMIT 1
    ";

    $stmtPartidaGet = $conn->prepare($sqlPartidaGetter);
    if (!$stmtPartidaGet) {
        throw new RuntimeException('Error prepare estado partida: ' . $conn->error);
    }

    $stmtPartidaGet->bind_param('i', $idPartida);
    $stmtPartidaGet->execute();
    $estadoRes = $stmtPartidaGet->get_result()->fetch_assoc();

    if (!$estadoRes) {
        throw new RuntimeException('Estado de partida no encontrado');
    }

    $desgloseInicial = repartir_cia_inicial((int)$estadoRes['cia_inicial']);

    $confActual = $estadoRes['c_despues'] !== null ? (int)$estadoRes['c_despues'] : ((int)($estadoRes['c_inicial'] ?? $desgloseInicial['confidencialidad']));
    $inteActual = $estadoRes['i_despues'] !== null ? (int)$estadoRes['i_despues'] : ((int)($estadoRes['i_inicial'] ?? $desgloseInicial['integridad']));
    $accActual = $estadoRes['a_despues'] !== null ? (int)$estadoRes['a_despues'] : ((int)($estadoRes['a_inicial'] ?? $desgloseInicial['accesibilidad']));

    return [
        'cia' => (int)calcular_cia_promedio($confActual, $inteActual, $accActual),
        'confidencialidad' => $confActual,
        'integridad' => $inteActual,
        'accesibilidad' => $accActual,
        'presupuesto' => (int)($estadoRes['presupuesto_despues'] !== null ? $estadoRes['presupuesto_despues'] : $estadoRes['presupuesto_inicial']),
        'despido' => (int)round((float)($estadoRes['despido_despues'] !== null ? $estadoRes['despido_despues'] : $estadoRes['despido_inicial']))
    ];
}

function obtener_posicion_rank_global(mysqli $conn, int $idUsuario): ?int {
    $sqlRankingIds = "
        SELECT u.id_usuario
        FROM usuarios u
        WHERE EXISTS (
            SELECT 1
            FROM partidas p
            WHERE p.id_usuario = u.id_usuario
              AND p.estado_partida IN ('ganada', 'perdida')
        )
        ORDER BY
            (
                SELECT ROUND(
                    COALESCE(AVG(p2.cia_final), 0)
                    + COALESCE(AVG(p2.presupuesto_final), 0)
                    + (100 - COALESCE(AVG(p2.despido_final), 0)),
                    2
                )
                FROM partidas p2
                WHERE p2.id_usuario = u.id_usuario
                  AND p2.estado_partida IN ('ganada', 'perdida')
            ) DESC,
            (
                SELECT COUNT(*)
                FROM partidas p3
                WHERE p3.id_usuario = u.id_usuario
                  AND p3.estado_partida IN ('ganada', 'perdida')
            ) DESC,
            u.nombre_usuario ASC
    ";

    $res = $conn->query($sqlRankingIds);
    if (!$res) {
        return null;
    }

    $pos = 0;
    while ($row = $res->fetch_assoc()) {
        $pos++;
        if ((int)$row['id_usuario'] === $idUsuario) {
            return $pos;
        }
    }

    return null;
}

function obtener_escenario_random_no_repetido(mysqli $conn, int $idPartida): ?array {
    $sqlEscenario = "
        SELECT
            e.id_escenario,
            e.tipo_escenario,
            e.titulo_correo,
            e.texto_correo,
            e.feedback_general,
            r.correo AS remitente_correo,
            r.nombre_mostrado AS remitente_nombre
        FROM escenarios e
        LEFT JOIN remitentes_email r ON r.id_remitente = e.id_remitente
        WHERE e.activo = 1
          AND e.id_escenario NOT IN (
              SELECT pe.id_escenario
              FROM partida_escenarios pe
              WHERE pe.id_partida = ?
          )
        ORDER BY RAND()
        LIMIT 1
    ";

    $stmtEscenario = $conn->prepare($sqlEscenario);
    if (!$stmtEscenario) {
        throw new RuntimeException('Error prepare escenario: ' . $conn->error);
    }

    $stmtEscenario->bind_param('i', $idPartida);
    $stmtEscenario->execute();
    $resultadoEscenario = $stmtEscenario->get_result();

    if ($resultadoEscenario->num_rows === 0) {
        return null;
    }

    $escenario = $resultadoEscenario->fetch_assoc();
    $idEscenario = (int)$escenario['id_escenario'];

    $sqlOrden = "SELECT COALESCE(MAX(orden_en_partida), 0) + 1 AS siguiente_orden FROM partida_escenarios WHERE id_partida = ?";
    $stmtOrden = $conn->prepare($sqlOrden);
    if (!$stmtOrden) {
        throw new RuntimeException('Error prepare orden: ' . $conn->error);
    }

    $stmtOrden->bind_param('i', $idPartida);
    $stmtOrden->execute();
    $siguienteOrden = (int)$stmtOrden->get_result()->fetch_assoc()['siguiente_orden'];

    $sqlInsert = "
        INSERT INTO partida_escenarios (id_partida, id_escenario, orden_en_partida, notificado_en)
        VALUES (?, ?, ?, NOW())
    ";
    $stmtInsert = $conn->prepare($sqlInsert);
    if (!$stmtInsert) {
        throw new RuntimeException('Error prepare insert partida_escenarios: ' . $conn->error);
    }

    $stmtInsert->bind_param('iii', $idPartida, $idEscenario, $siguienteOrden);
    $stmtInsert->execute();

    $sqlOpciones = "
        SELECT
            o.id_opcion,
            o.codigo_opcion,
            o.texto_opcion,
            o.feedback_opcion,
            COALESCE(io.delta_c_base, io.delta_cia_base, 0) AS delta_confidencialidad_base,
            COALESCE(io.delta_i_base, io.delta_cia_base, 0) AS delta_integridad_base,
            COALESCE(io.delta_a_base, io.delta_cia_base, 0) AS delta_accesibilidad_base,
            COALESCE(io.delta_presupuesto_base, 0) AS delta_presupuesto_base
        FROM opciones_escenario o
        LEFT JOIN impactos_opcion io ON io.id_opcion = o.id_opcion AND io.activo = 1
        WHERE o.id_escenario = ? AND o.activa = 1
        ORDER BY o.id_opcion ASC
    ";
    $stmtOpciones = $conn->prepare($sqlOpciones);
    if (!$stmtOpciones) {
        throw new RuntimeException('Error prepare opciones: ' . $conn->error);
    }

    $stmtOpciones->bind_param('i', $idEscenario);
    $stmtOpciones->execute();
    $resultadoOpciones = $stmtOpciones->get_result();

    $opciones = [];
    while ($fila = $resultadoOpciones->fetch_assoc()) {
        $opciones[] = $fila;
    }

    return [
        'id_partida_escenario' => (int)$stmtInsert->insert_id,
        'orden_en_partida' => $siguienteOrden,
        'escenario' => [
            'id_escenario' => $idEscenario,
            'tipo_escenario' => $escenario['tipo_escenario'],
            'titulo_correo' => $escenario['titulo_correo'],
            'texto_correo' => $escenario['texto_correo'],
            'feedback_general' => $escenario['feedback_general'],
            'remitente_correo' => $escenario['remitente_correo'],
            'remitente_nombre' => $escenario['remitente_nombre'],
            'opciones' => $opciones
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'error' => 'METODO_NO_PERMITIDO']);
}

$accion = $_POST['accion'] ?? '';
$idUsuario = (int)$_SESSION['usuario_id'];

try {
    if ($accion === 'iniciar_partida') {
        $cia = isset($_POST['cia']) ? (int)$_POST['cia'] : -1;
        $presupuesto = isset($_POST['presupuesto']) ? (int)$_POST['presupuesto'] : -1;
        $despido = isset($_POST['despido']) ? (float)$_POST['despido'] : -1;
        $maxRondas = isset($_POST['maxRondas']) ? (int)$_POST['maxRondas'] : 25;
        $desgloseInicial = repartir_cia_inicial($cia);
        $ciaInicialPromedio = calcular_cia_promedio(
            (float)$desgloseInicial['confidencialidad'],
            (float)$desgloseInicial['integridad'],
            (float)$desgloseInicial['accesibilidad']
        );

        if ($cia < 0 || $cia > 100 || $presupuesto < 5 || $presupuesto > 100 || $despido < 0 || $despido > 100 || $maxRondas < 15 || $maxRondas > 40) {
            responder(['ok' => false, 'error' => 'PARAMETROS_INVALIDOS']);
        }

        $sqlPartida = "
            INSERT INTO partidas (id_usuario, estado_partida, cia_inicial, c_inicial, i_inicial, a_inicial, presupuesto_inicial, despido_inicial, max_rondas)
            VALUES (?, 'en_curso', ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmtPartida = $conn->prepare($sqlPartida);
        if (!$stmtPartida) {
            throw new RuntimeException('Error prepare partida: ' . $conn->error);
        }

        $partidaBindTypes = str_repeat('i', 6) . 'd' . 'i';
        $stmtPartida->bind_param(
            $partidaBindTypes,
            $idUsuario,
            $cia,
            $desgloseInicial['confidencialidad'],
            $desgloseInicial['integridad'],
            $desgloseInicial['accesibilidad'],
            $presupuesto,
            $despido,
            $maxRondas
        );
        $stmtPartida->execute();

        $idPartida = (int)$stmtPartida->insert_id;
        $_SESSION['partida_id_actual'] = $idPartida;

        $turno = obtener_escenario_random_no_repetido($conn, $idPartida);

        responder([
            'ok' => true,
            'accion' => 'iniciar_partida',
            'id_partida' => $idPartida,
            'estado' => [
                'cia' => $ciaInicialPromedio,
                'confidencialidad' => $desgloseInicial['confidencialidad'],
                'integridad' => $desgloseInicial['integridad'],
                'accesibilidad' => $desgloseInicial['accesibilidad'],
                'presupuesto' => $presupuesto,
                'despido' => $despido,
                'maxRondas' => $maxRondas,
                'cia_inicial' => $cia
            ],
            'turno' => $turno
        ]);
    }

    if ($accion === 'siguiente_escenario') {
        $idPartida = isset($_SESSION['partida_id_actual']) ? (int)$_SESSION['partida_id_actual'] : 0;

        if ($idPartida <= 0) {
            responder(['ok' => false, 'error' => 'SIN_PARTIDA_ACTIVA']);
        }

        $sqlValidaPartida = "SELECT id_partida, max_rondas, estado_partida FROM partidas WHERE id_partida = ? AND id_usuario = ? LIMIT 1";
        $stmtValida = $conn->prepare($sqlValidaPartida);
        if (!$stmtValida) {
            throw new RuntimeException('Error prepare validar partida: ' . $conn->error);
        }

        $stmtValida->bind_param('ii', $idPartida, $idUsuario);
        $stmtValida->execute();
        $resValida = $stmtValida->get_result();

        if ($resValida->num_rows === 0) {
            responder(['ok' => false, 'error' => 'PARTIDA_INVALIDA']);
        }

        $partidaData = $resValida->fetch_assoc();
        $maxRondas = (int)$partidaData['max_rondas'];
        $estadoPartidaActual = (string)($partidaData['estado_partida'] ?? 'en_curso');

        if ($estadoPartidaActual !== 'en_curso') {
            responder([
                'ok' => true,
                'accion' => 'siguiente_escenario',
                'id_partida' => $idPartida,
                'partida_finalizada' => true,
                'resultado' => $estadoPartidaActual,
                'mensaje' => $estadoPartidaActual === 'ganada'
                    ? 'Partida finalizada por victoria.'
                    : 'Partida finalizada por derrota.'
            ]);
        }

        // Cerrar partida al completar el máximo de escenarios configurado.
        $sqlConteo = "SELECT COUNT(*) AS total FROM partida_escenarios WHERE id_partida = ?";
        $stmtConteo = $conn->prepare($sqlConteo);
        if (!$stmtConteo) {
            throw new RuntimeException('Error prepare conteo escenarios: ' . $conn->error);
        }
        $stmtConteo->bind_param('i', $idPartida);
        $stmtConteo->execute();
        $totalEscenarios = (int)$stmtConteo->get_result()->fetch_assoc()['total'];

        if ($totalEscenarios >= $maxRondas) {
            $estadoActual = obtener_estado_actual_partida($conn, $idPartida);
            $estadoFinal = evaluar_estado_final(
                $estadoActual['cia'],
                $estadoActual['presupuesto'],
                $estadoActual['despido']
            );
            $resultadoFinal = $estadoFinal['resultado'] === 'en_curso' ? 'ganada' : $estadoFinal['resultado'];
            $mensajeFinal = $resultadoFinal === 'perdida'
                ? 'Partida finalizada por derrota.'
                : 'Partida finalizada: completaste las rondas seleccionadas.';

            cerrar_partida(
                $conn,
                $idPartida,
                $resultadoFinal,
                $estadoActual['cia'],
                $estadoActual['presupuesto'],
                $estadoActual['despido'],
                $estadoActual['confidencialidad'],
                $estadoActual['integridad'],
                $estadoActual['accesibilidad']
            );
            responder([
                'ok' => true,
                'accion' => 'siguiente_escenario',
                'id_partida' => $idPartida,
                'partida_finalizada' => true,
                'resultado' => $resultadoFinal,
                'mensaje' => $mensajeFinal
            ]);
        }

        $turno = obtener_escenario_random_no_repetido($conn, $idPartida);

        if ($turno === null) {
            $estadoActual = obtener_estado_actual_partida($conn, $idPartida);
            $estadoFinal = evaluar_estado_final(
                $estadoActual['cia'],
                $estadoActual['presupuesto'],
                $estadoActual['despido']
            );
            $resultadoFinal = $estadoFinal['resultado'] === 'en_curso' ? 'ganada' : $estadoFinal['resultado'];
            $mensajeFinal = $resultadoFinal === 'perdida'
                ? 'Partida finalizada por derrota.'
                : 'Partida finalizada: no hay mas escenarios disponibles en la base de datos.';

            cerrar_partida(
                $conn,
                $idPartida,
                $resultadoFinal,
                $estadoActual['cia'],
                $estadoActual['presupuesto'],
                $estadoActual['despido'],
                $estadoActual['confidencialidad'],
                $estadoActual['integridad'],
                $estadoActual['accesibilidad']
            );
            responder([
                'ok' => true,
                'accion' => 'siguiente_escenario',
                'id_partida' => $idPartida,
                'partida_finalizada' => true,
                'resultado' => $resultadoFinal,
                'mensaje' => $mensajeFinal
            ]);
        }

        responder([
            'ok' => true,
            'accion' => 'siguiente_escenario',
            'id_partida' => $idPartida,
            'turno' => $turno
        ]);
    }

    if ($accion === 'guardar_sesion') {
        $idPartidaEscenario = (int)($_POST['id_partida_escenario'] ?? 0);
        if ($idPartidaEscenario > 0) {
            $_SESSION['partida_escenario_id_actual'] = $idPartidaEscenario;
        }
        responder(['ok' => true, 'accion' => 'guardar_sesion']);
    }

    if ($accion === 'obtener_rank_global') {
        $rankGlobal = obtener_posicion_rank_global($conn, $idUsuario);
        responder([
            'ok' => true,
            'accion' => 'obtener_rank_global',
            'rank_global' => $rankGlobal
        ]);
    }

    if ($accion === 'procesar_opcion') {
        $idOpcion = (int)($_POST['id_opcion'] ?? 0);
        $codigoOpcion = $_POST['codigo_opcion'] ?? '';
        $fueTimeout = (int)($_POST['fue_timeout'] ?? 0);
        $tiempoRespuesta = (int)($_POST['tiempo_respuesta'] ?? 0);
        $idPartida = isset($_SESSION['partida_id_actual']) ? (int)$_SESSION['partida_id_actual'] : 0;
        $idPartidaEscenario = isset($_SESSION['partida_escenario_id_actual']) ? (int)$_SESSION['partida_escenario_id_actual'] : 0;

        if ($idPartida <= 0) {
            responder(['ok' => false, 'error' => 'PARAMETROS_INVALIDOS']);
        }

        if ($fueTimeout === 0 && $idOpcion <= 0 && $codigoOpcion === '') {
            responder(['ok' => false, 'error' => 'PARAMETROS_INVALIDOS']);
        }

        // Validar partida pertenece al usuario
        $sqlValidaPartida = "SELECT id_partida, estado_partida FROM partidas WHERE id_partida = ? AND id_usuario = ? LIMIT 1";
        $stmtValida = $conn->prepare($sqlValidaPartida);
        if (!$stmtValida) {
            throw new RuntimeException('Error prepare validar partida: ' . $conn->error);
        }
        $stmtValida->bind_param('ii', $idPartida, $idUsuario);
        $stmtValida->execute();
        $partidaValidada = $stmtValida->get_result()->fetch_assoc();
        if (!$partidaValidada) {
            responder(['ok' => false, 'error' => 'PARTIDA_INVALIDA']);
        }

        $estadoPartidaActual = (string)($partidaValidada['estado_partida'] ?? 'en_curso');
        if ($estadoPartidaActual !== 'en_curso') {
            responder([
                'ok' => true,
                'accion' => 'procesar_opcion',
                'id_partida' => $idPartida,
                'partida_finalizada' => true,
                'resultado' => $estadoPartidaActual,
                'mensaje' => $estadoPartidaActual === 'ganada'
                    ? 'Partida finalizada por victoria.'
                    : 'Partida finalizada por derrota.'
            ]);
        }

        // Si es timeout, buscar la opción 'timeout' del escenario actual
        if ($fueTimeout === 1) {
            $idPartidaEscenario = isset($_SESSION['partida_escenario_id_actual']) ? (int)$_SESSION['partida_escenario_id_actual'] : 0;
            if ($idPartidaEscenario <= 0) {
                responder(['ok' => false, 'error' => 'NO_ESCENARIO_ACTIVO']);
            }

            $sqlBuscaTimeout = "
                SELECT oe.id_opcion
                FROM partida_escenarios pe
                JOIN escenarios e ON e.id_escenario = pe.id_escenario
                JOIN opciones_escenario oe ON oe.id_escenario = e.id_escenario
                WHERE pe.id_partida_escenario = ? AND oe.codigo_opcion = 'timeout'
                LIMIT 1
            ";
            $stmtBuscaTimeout = $conn->prepare($sqlBuscaTimeout);
            if (!$stmtBuscaTimeout) {
                throw new RuntimeException('Error buscar timeout: ' . $conn->error);
            }
            $stmtBuscaTimeout->bind_param('i', $idPartidaEscenario);
            $stmtBuscaTimeout->execute();
            $resTimeout = $stmtBuscaTimeout->get_result();
            if ($resTimeout->num_rows === 0) {
                responder(['ok' => false, 'error' => 'OPCION_TIMEOUT_NO_EXISTE']);
            }
            $idOpcion = (int)$resTimeout->fetch_assoc()['id_opcion'];
        } elseif ($idOpcion <= 0 && $codigoOpcion !== '') {
            $idPartidaEscenario = isset($_SESSION['partida_escenario_id_actual']) ? (int)$_SESSION['partida_escenario_id_actual'] : 0;
            if ($idPartidaEscenario <= 0) {
                responder(['ok' => false, 'error' => 'NO_ESCENARIO_ACTIVO']);
            }

            $sqlBuscaPorCodigo = "
                SELECT oe.id_opcion
                FROM partida_escenarios pe
                JOIN opciones_escenario oe ON oe.id_escenario = pe.id_escenario
                WHERE pe.id_partida_escenario = ?
                  AND oe.codigo_opcion = ?
                  AND oe.activa = 1
                LIMIT 1
            ";
            $stmtBuscaCodigo = $conn->prepare($sqlBuscaPorCodigo);
            if (!$stmtBuscaCodigo) {
                throw new RuntimeException('Error buscar opcion por codigo: ' . $conn->error);
            }
            $stmtBuscaCodigo->bind_param('is', $idPartidaEscenario, $codigoOpcion);
            $stmtBuscaCodigo->execute();
            $resCodigo = $stmtBuscaCodigo->get_result();
            if ($resCodigo->num_rows === 0) {
                responder(['ok' => false, 'error' => 'OPCION_CODIGO_NO_EXISTE']);
            }
            $idOpcion = (int)$resCodigo->fetch_assoc()['id_opcion'];
        }

        // Obtener impacto base de la opción
        $sqlImpacto = "
            SELECT io.delta_c_base, io.delta_i_base, io.delta_a_base, io.delta_presupuesto_base, io.delta_despido_base, oe.feedback_opcion
            FROM impactos_opcion io
            JOIN opciones_escenario oe ON oe.id_opcion = io.id_opcion
            WHERE io.id_opcion = ? AND io.activo = 1
            LIMIT 1
        ";
        $stmtImpacto = $conn->prepare($sqlImpacto);
        if (!$stmtImpacto) {
            throw new RuntimeException('Error prepare impacto: ' . $conn->error);
        }
        $stmtImpacto->bind_param('i', $idOpcion);
        $stmtImpacto->execute();
        $resImpacto = $stmtImpacto->get_result();
        if ($resImpacto->num_rows === 0) {
            responder(['ok' => false, 'error' => 'OPCION_NO_EXISTE']);
        }
        $impact = $resImpacto->fetch_assoc();
        $deltaConfBase = (int)$impact['delta_c_base'];
        $deltaInteBase = (int)$impact['delta_i_base'];
        $deltaAccBase = (int)$impact['delta_a_base'];
        $deltaPresupuestoBase = (int)$impact['delta_presupuesto_base'];
        $deltaDespigoBase = (float)$impact['delta_despido_base'];
        $feedbackOpcion = $impact['feedback_opcion'];

        // Obtener estado actual de la partida
        $sqlPartidaGetter = "
            SELECT 
                p.cia_inicial,
                p.c_inicial,
                p.i_inicial,
                p.a_inicial,
                p.presupuesto_inicial,
                p.despido_inicial,
                ep.cia_despues,
                ep.c_despues,
                ep.i_despues,
                ep.a_despues,
                ep.presupuesto_despues,
                ep.despido_despues
            FROM partidas p
            LEFT JOIN partida_escenarios pe ON pe.id_partida = p.id_partida
            LEFT JOIN eventos_partida ep ON ep.id_partida_escenario = pe.id_partida_escenario
            WHERE p.id_partida = ?
            ORDER BY pe.orden_en_partida DESC, ep.id_evento DESC
            LIMIT 1
        ";
        $stmtPartidaGet = $conn->prepare($sqlPartidaGetter);
        if (!$stmtPartidaGet) {
            throw new RuntimeException('Error prepare estado partida: ' . $conn->error);
        }
        $stmtPartidaGet->bind_param('i', $idPartida);
        $stmtPartidaGet->execute();
        $estadoRes = $stmtPartidaGet->get_result()->fetch_assoc();
        if (!$estadoRes) {
            responder(['ok' => false, 'error' => 'ESTADO_NO_ENCONTRADO']);
        }
        $desgloseInicial = repartir_cia_inicial((int)$estadoRes['cia_inicial']);

        $confidencialidadActual = $estadoRes['c_despues'] !== null ? (int)$estadoRes['c_despues'] : ((int)($estadoRes['c_inicial'] ?? $desgloseInicial['confidencialidad']));
        $integridadActual = $estadoRes['i_despues'] !== null ? (int)$estadoRes['i_despues'] : ((int)($estadoRes['i_inicial'] ?? $desgloseInicial['integridad']));
        $accesibilidadActual = $estadoRes['a_despues'] !== null ? (int)$estadoRes['a_despues'] : ((int)($estadoRes['a_inicial'] ?? $desgloseInicial['accesibilidad']));

        $ciaActual = (float)calcular_cia_promedio($confidencialidadActual, $integridadActual, $accesibilidadActual);
        $presupuestoActual = (float)($estadoRes['presupuesto_despues'] !== null ? $estadoRes['presupuesto_despues'] : $estadoRes['presupuesto_inicial']);
        $despigoActual = (float)($estadoRes['despido_despues'] !== null ? $estadoRes['despido_despues'] : $estadoRes['despido_inicial']);

        $esPhishing = false;
        if ($idPartidaEscenario > 0) {
            $sqlTipoEscenario = "
                SELECT e.tipo_escenario
                FROM partida_escenarios pe
                INNER JOIN escenarios e ON e.id_escenario = pe.id_escenario
                WHERE pe.id_partida_escenario = ?
                LIMIT 1
            ";
            $stmtTipoEscenario = $conn->prepare($sqlTipoEscenario);
            if (!$stmtTipoEscenario) {
                throw new RuntimeException('Error prepare tipo escenario: ' . $conn->error);
            }
            $stmtTipoEscenario->bind_param('i', $idPartidaEscenario);
            $stmtTipoEscenario->execute();
            $tipoEscenarioRes = $stmtTipoEscenario->get_result()->fetch_assoc();
            $esPhishing = isset($tipoEscenarioRes['tipo_escenario']) && strtolower((string)$tipoEscenarioRes['tipo_escenario']) === 'phishing';
        }

        // Evitar decisiones cuyo costo base de presupuesto no puede cubrir el jugador.
        if (!$esPhishing && $fueTimeout === 0 && $deltaPresupuestoBase < 0 && ($presupuestoActual + $deltaPresupuestoBase) < 0) {
            responder([
                'ok' => false,
                'error' => 'PRESUPUESTO_INSUFICIENTE',
                'mensaje' => 'No te alcanza para tomar esta desicion, elige otra'
            ]);
        }

        // Aplicar modificadores definidos por reglas de balance.
        $modificadores = aplicar_modificadores(
            $ciaActual,
            $presupuestoActual,
            $deltaConfBase,
            $deltaInteBase,
            $deltaAccBase,
            $deltaPresupuestoBase,
            $deltaDespigoBase
        );

        $deltaConfAplicado = $modificadores['delta_confidencialidad'];
        $deltaInteAplicado = $modificadores['delta_integridad'];
        $deltaAccAplicado = $modificadores['delta_accesibilidad'];
        $deltaPresupuestoAplicado = $modificadores['delta_presupuesto'];
        $deltaDespigoAplicado = $modificadores['delta_despido'];

        // Calcular nuevos puntajes
        $confidencialidadNueva = max(0, min(100, round($confidencialidadActual + $deltaConfAplicado)));
        $integridadNueva = max(0, min(100, round($integridadActual + $deltaInteAplicado)));
        $accesibilidadNueva = max(0, min(100, round($accesibilidadActual + $deltaAccAplicado)));
        $ciaNueva = calcular_cia_promedio($confidencialidadNueva, $integridadNueva, $accesibilidadNueva);
        $presupuestoNuevo = max(0, round($presupuestoActual + $deltaPresupuestoAplicado));
        $despigoNuevo = max(0, min(100, round($despigoActual + $deltaDespigoAplicado)));

        // Registrar evento
        $idPartidaEscenario = isset($_SESSION['partida_escenario_id_actual']) ? (int)$_SESSION['partida_escenario_id_actual'] : 0;

        $sqlEvento = "
            INSERT INTO eventos_partida (
                id_partida_escenario, id_opcion_elegida,
                tiempo_respuesta_segundos, fue_timeout,
                cia_antes, c_antes, i_antes, a_antes, presupuesto_antes, despido_antes,
                cia_despues, c_despues, i_despues, a_despues, presupuesto_despues, despido_despues,
                feedback_mostrado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmtEvento = $conn->prepare($sqlEvento);
        if (!$stmtEvento) {
            throw new RuntimeException('Error prepare evento: ' . $conn->error);
        }

        $ciaNuevaInt = (int)$ciaNueva;
        $presupuestoNuevoInt = (int)$presupuestoNuevo;
        $despigoNuevoDecimal = (float)$despigoNuevo;
        $ciaActualInt = (int)round($ciaActual);
        $confidencialidadActualInt = (int)round($confidencialidadActual);
        $integridadActualInt = (int)round($integridadActual);
        $accesibilidadActualInt = (int)round($accesibilidadActual);
        $confidencialidadNuevaInt = (int)$confidencialidadNueva;
        $integridadNuevaInt = (int)$integridadNueva;
        $accesibilidadNuevaInt = (int)$accesibilidadNueva;
        $presupuestoActualInt = (int)round($presupuestoActual);
        $despigoActualDecimal = (float)round($despigoActual);

        $eventoBindTypes = str_repeat('i', 9) . 'd' . str_repeat('i', 5) . 'd' . 's';
        $stmtEvento->bind_param(
            $eventoBindTypes,
            $idPartidaEscenario,
            $idOpcion,
            $tiempoRespuesta,
            $fueTimeout,
            $ciaActualInt,
            $confidencialidadActualInt,
            $integridadActualInt,
            $accesibilidadActualInt,
            $presupuestoActualInt,
            $despigoActualDecimal,
            $ciaNuevaInt,
            $confidencialidadNuevaInt,
            $integridadNuevaInt,
            $accesibilidadNuevaInt,
            $presupuestoNuevoInt,
            $despigoNuevoDecimal,
            $feedbackOpcion
        );
        $stmtEvento->execute();

        $ajusteTrimestral = [
            'emitir_correo' => false,
            'monto' => 0,
            'presupuesto_antes' => $presupuestoNuevoInt,
            'presupuesto_despues' => $presupuestoNuevoInt,
            'despido_actual' => (int)$despigoNuevoDecimal,
            'mensaje_tipo' => 'sin_ajuste',
            'turnos_respondidos' => 0,
            'cia_antes' => $ciaActualInt,
            'cia_despues' => $ciaNuevaInt,
            'confidencialidad_antes' => $confidencialidadActualInt,
            'integridad_antes' => $integridadActualInt,
            'accesibilidad_antes' => $accesibilidadActualInt,
            'confidencialidad_despues' => $confidencialidadNuevaInt,
            'integridad_despues' => $integridadNuevaInt,
            'accesibilidad_despues' => $accesibilidadNuevaInt
        ];

        // Solo cuentan turnos respondidos por el jugador (sin timeout).
        $sqlConteoRespondidos = "
            SELECT COUNT(*) AS total_respondidos
            FROM eventos_partida ep
            INNER JOIN partida_escenarios pe ON pe.id_partida_escenario = ep.id_partida_escenario
            WHERE pe.id_partida = ?
              AND ep.fue_timeout = 0
        ";
        $stmtRespondidos = $conn->prepare($sqlConteoRespondidos);
        if (!$stmtRespondidos) {
            throw new RuntimeException('Error prepare conteo respondidos: ' . $conn->error);
        }
        $stmtRespondidos->bind_param('i', $idPartida);
        $stmtRespondidos->execute();
        $turnosRespondidos = (int)$stmtRespondidos->get_result()->fetch_assoc()['total_respondidos'];

        $ajusteTrimestral['turnos_respondidos'] = $turnosRespondidos;

        if ($turnosRespondidos > 0 && ($turnosRespondidos % 3) === 0) {
            $montoBase = calcular_ajuste_trimestral_por_despido((float)$despigoNuevoDecimal);
            $presupuestoAjustado = max(0, min(100, $presupuestoNuevoInt + $montoBase));
            $montoRealAplicado = $presupuestoAjustado - $presupuestoNuevoInt;

            $presupuestoAntesAjuste = $presupuestoNuevoInt;
            $presupuestoNuevoInt = $presupuestoAjustado;
            $deltaPresupuestoAplicado += $montoRealAplicado;

            $tipoMensaje = 'sin_ajuste';
            if ($montoRealAplicado > 0) {
                $tipoMensaje = 'bono_trimestral';
            } elseif ($montoRealAplicado < 0) {
                $tipoMensaje = 'recorte_rendimiento';
            }

            $ajusteTrimestral = [
                'emitir_correo' => true,
                'monto' => $montoRealAplicado,
                'presupuesto_antes' => $presupuestoAntesAjuste,
                'presupuesto_despues' => $presupuestoNuevoInt,
                'despido_actual' => (int)$despigoNuevoDecimal,
                'mensaje_tipo' => $tipoMensaje,
                'turnos_respondidos' => $turnosRespondidos,
                'cia_antes' => $ciaActualInt,
                'cia_despues' => $ciaNuevaInt,
                'confidencialidad_antes' => $confidencialidadActualInt,
                'integridad_antes' => $integridadActualInt,
                'accesibilidad_antes' => $accesibilidadActualInt,
                'confidencialidad_despues' => $confidencialidadNuevaInt,
                'integridad_despues' => $integridadNuevaInt,
                'accesibilidad_despues' => $accesibilidadNuevaInt
            ];
        }

        $presupuestoNuevo = $presupuestoNuevoInt;

        $estadoFinal = evaluar_estado_final($ciaNuevaInt, $presupuestoNuevoInt, (int)$despigoNuevoDecimal);
        if ($estadoFinal['resultado'] === 'ganada' || $estadoFinal['resultado'] === 'perdida') {
            cerrar_partida(
                $conn,
                $idPartida,
                $estadoFinal['resultado'],
                $ciaNuevaInt,
                $presupuestoNuevoInt,
                (int)$despigoNuevoDecimal,
                $confidencialidadNuevaInt,
                $integridadNuevaInt,
                $accesibilidadNuevaInt
            );

            responder([
                'ok' => true,
                'accion' => 'procesar_opcion',
                'id_partida' => $idPartida,
                'partida_finalizada' => true,
                'resultado' => $estadoFinal['resultado'],
                'mensaje' => ($estadoFinal['resultado'] === 'ganada') ? 'Felicidades, ganaste' : 'Partida perdida',
                'nuevo_estado' => [
                    'cia' => $ciaNuevaInt,
                    'confidencialidad' => $confidencialidadNuevaInt,
                    'integridad' => $integridadNuevaInt,
                    'accesibilidad' => $accesibilidadNuevaInt,
                    'presupuesto' => $presupuestoNuevoInt,
                    'despido' => (int)$despigoNuevoDecimal
                ],
                'delta' => [
                    'delta_cia_aplicado' => round($ciaNuevaInt - $ciaActualInt, 2),
                    'delta_confidencialidad_aplicado' => round($deltaConfAplicado, 2),
                    'delta_integridad_aplicado' => round($deltaInteAplicado, 2),
                    'delta_accesibilidad_aplicado' => round($deltaAccAplicado, 2),
                    'delta_presupuesto_aplicado' => round($deltaPresupuestoAplicado, 2),
                    'delta_despido_aplicado' => round($deltaDespigoAplicado, 2)
                ],
                'feedback' => $feedbackOpcion,
                'ajuste_trimestral' => $ajusteTrimestral
            ]);
        }

        responder([
            'ok' => true,
            'accion' => 'procesar_opcion',
            'id_partida' => $idPartida,
            'nuevo_estado' => [
                'cia' => $ciaNueva,
                'confidencialidad' => $confidencialidadNuevaInt,
                'integridad' => $integridadNuevaInt,
                'accesibilidad' => $accesibilidadNuevaInt,
                'presupuesto' => $presupuestoNuevo,
                'despido' => $despigoNuevo
            ],
            'delta' => [
                'delta_cia_aplicado' => round($ciaNuevaInt - $ciaActualInt, 2),
                'delta_confidencialidad_aplicado' => round($deltaConfAplicado, 2),
                'delta_integridad_aplicado' => round($deltaInteAplicado, 2),
                'delta_accesibilidad_aplicado' => round($deltaAccAplicado, 2),
                'delta_presupuesto_aplicado' => round($deltaPresupuestoAplicado, 2),
                'delta_despido_aplicado' => round($deltaDespigoAplicado, 2)
            ],
            'feedback' => $feedbackOpcion,
            'cia_desglose' => [
                'antes' => [
                    'cia' => $ciaActualInt,
                    'confidencialidad' => $confidencialidadActualInt,
                    'integridad' => $integridadActualInt,
                    'accesibilidad' => $accesibilidadActualInt
                ],
                'despues' => [
                    'cia' => $ciaNuevaInt,
                    'confidencialidad' => $confidencialidadNuevaInt,
                    'integridad' => $integridadNuevaInt,
                    'accesibilidad' => $accesibilidadNuevaInt
                ]
            ],
            'ajuste_trimestral' => $ajusteTrimestral
        ]);
    }

    responder(['ok' => false, 'error' => 'ACCION_INVALIDA']);
} catch (Throwable $e) {
    error_log('partida_api.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'ERROR_INTERNO']);
}
