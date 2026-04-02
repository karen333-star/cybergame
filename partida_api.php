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

function aplicar_modificador_signado(float $deltaBase, float $factorPositivo, float $factorNegativo): float {
    return ($deltaBase > 0) ? ($deltaBase * $factorPositivo) : ($deltaBase * $factorNegativo);
}

function aplicar_modificadores(float $ciaActual, float $presupuestoActual, float $deltaCiaBase, float $deltaPresupuestoBase, float $deltaDespidoBase): array {
    $deltaCiaAplicado = $deltaCiaBase;
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
        'delta_cia' => round($deltaCiaAplicado),
        'delta_presupuesto' => round($deltaPresupuestoAplicado),
        'delta_despido' => round($deltaDespidoAplicado),
    ];
}

function evaluar_estado_final(float $cia, float $presupuesto, float $despido): array {
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

function cerrar_partida(mysqli $conn, int $idPartida, string $resultado, int $ciaFinal, int $presupuestoFinal, int $despidoFinal): void {
    $sqlActualizar = "
        UPDATE partidas
        SET estado_partida = ?,
            cia_final = ?,
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

    $stmtActualizar->bind_param('siiii', $resultado, $ciaFinal, $presupuestoFinal, $despidoFinal, $idPartida);
    $stmtActualizar->execute();
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
            o.feedback_opcion
        FROM opciones_escenario o
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

        if ($cia < 0 || $cia > 100 || $presupuesto < 5 || $presupuesto > 100 || $despido < 0 || $despido > 100 || $maxRondas < 15 || $maxRondas > 40) {
            responder(['ok' => false, 'error' => 'PARAMETROS_INVALIDOS']);
        }

        $sqlPartida = "
            INSERT INTO partidas (id_usuario, estado_partida, cia_inicial, presupuesto_inicial, despido_inicial, max_rondas)
            VALUES (?, 'en_curso', ?, ?, ?, ?)
        ";

        $stmtPartida = $conn->prepare($sqlPartida);
        if (!$stmtPartida) {
            throw new RuntimeException('Error prepare partida: ' . $conn->error);
        }

        $stmtPartida->bind_param('iiidi', $idUsuario, $cia, $presupuesto, $despido, $maxRondas);
        $stmtPartida->execute();

        $idPartida = (int)$stmtPartida->insert_id;
        $_SESSION['partida_id_actual'] = $idPartida;

        $turno = obtener_escenario_random_no_repetido($conn, $idPartida);

        responder([
            'ok' => true,
            'accion' => 'iniciar_partida',
            'id_partida' => $idPartida,
            'estado' => [
                'cia' => $cia,
                'presupuesto' => $presupuesto,
                'despido' => $despido,
                'maxRondas' => $maxRondas
            ],
            'turno' => $turno
        ]);
    }

    if ($accion === 'siguiente_escenario') {
        $idPartida = isset($_SESSION['partida_id_actual']) ? (int)$_SESSION['partida_id_actual'] : 0;

        if ($idPartida <= 0) {
            responder(['ok' => false, 'error' => 'SIN_PARTIDA_ACTIVA']);
        }

        $sqlValidaPartida = "SELECT id_partida, max_rondas FROM partidas WHERE id_partida = ? AND id_usuario = ? LIMIT 1";
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
            responder([
                'ok' => true,
                'accion' => 'siguiente_escenario',
                'id_partida' => $idPartida,
                'partida_finalizada' => true,
                'mensaje' => 'felicidades, acabaste'
            ]);
        }

        $turno = obtener_escenario_random_no_repetido($conn, $idPartida);

        if ($turno === null) {
            responder([
                'ok' => true,
                'accion' => 'siguiente_escenario',
                'id_partida' => $idPartida,
                'partida_finalizada' => true,
                'mensaje' => 'felicidades, acabaste'
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

    if ($accion === 'procesar_opcion') {
        $idOpcion = (int)($_POST['id_opcion'] ?? 0);
        $codigoOpcion = $_POST['codigo_opcion'] ?? '';
        $fueTimeout = (int)($_POST['fue_timeout'] ?? 0);
        $tiempoRespuesta = (int)($_POST['tiempo_respuesta'] ?? 0);
        $idPartida = isset($_SESSION['partida_id_actual']) ? (int)$_SESSION['partida_id_actual'] : 0;

        if ($idPartida <= 0) {
            responder(['ok' => false, 'error' => 'PARAMETROS_INVALIDOS']);
        }

        if ($fueTimeout === 0 && $idOpcion <= 0 && $codigoOpcion === '') {
            responder(['ok' => false, 'error' => 'PARAMETROS_INVALIDOS']);
        }

        // Validar partida pertenece al usuario
        $sqlValidaPartida = "SELECT id_partida FROM partidas WHERE id_partida = ? AND id_usuario = ? LIMIT 1";
        $stmtValida = $conn->prepare($sqlValidaPartida);
        if (!$stmtValida) {
            throw new RuntimeException('Error prepare validar partida: ' . $conn->error);
        }
        $stmtValida->bind_param('ii', $idPartida, $idUsuario);
        $stmtValida->execute();
        if ($stmtValida->get_result()->num_rows === 0) {
            responder(['ok' => false, 'error' => 'PARTIDA_INVALIDA']);
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
            SELECT io.delta_cia_base, io.delta_presupuesto_base, io.delta_despido_base, oe.feedback_opcion
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
        $deltaCiaBase = (int)$impact['delta_cia_base'];
        $deltaPresupuestoBase = (int)$impact['delta_presupuesto_base'];
        $deltaDespigoBase = (float)$impact['delta_despido_base'];
        $feedbackOpcion = $impact['feedback_opcion'];

        // Obtener estado actual de la partida
        $sqlPartidaGetter = "
            SELECT 
                p.cia_inicial,
                p.presupuesto_inicial,
                p.despido_inicial,
                COALESCE(MAX(ep.cia_despues), p.cia_inicial) AS cia_actual,
                COALESCE(MAX(ep.presupuesto_despues), p.presupuesto_inicial) AS presupuesto_actual,
                COALESCE(MAX(ep.despido_despues), p.despido_inicial) AS despido_actual
            FROM partidas p
            LEFT JOIN eventos_partida ep ON ep.id_partida_escenario IN (
                SELECT id_partida_escenario FROM partida_escenarios WHERE id_partida = ?
            )
            WHERE p.id_partida = ?
            GROUP BY p.id_partida
            LIMIT 1
        ";
        $stmtPartidaGet = $conn->prepare($sqlPartidaGetter);
        if (!$stmtPartidaGet) {
            throw new RuntimeException('Error prepare estado partida: ' . $conn->error);
        }
        $stmtPartidaGet->bind_param('ii', $idPartida, $idPartida);
        $stmtPartidaGet->execute();
        $estadoRes = $stmtPartidaGet->get_result()->fetch_assoc();
        if (!$estadoRes) {
            responder(['ok' => false, 'error' => 'ESTADO_NO_ENCONTRADO']);
        }
        $ciaActual = (float)$estadoRes['cia_actual'];
        $presupuestoActual = (float)$estadoRes['presupuesto_actual'];
        $despigoActual = (float)$estadoRes['despido_actual'];

        // Aplicar modificadores definidos por reglas de balance.
        $modificadores = aplicar_modificadores(
            $ciaActual,
            $presupuestoActual,
            $deltaCiaBase,
            $deltaPresupuestoBase,
            $deltaDespigoBase
        );

        $deltaCiaAplicado = $modificadores['delta_cia'];
        $deltaPresupuestoAplicado = $modificadores['delta_presupuesto'];
        $deltaDespigoAplicado = $modificadores['delta_despido'];

        // Calcular nuevos puntajes
        $ciaNueva = max(0, min(100, round($ciaActual + $deltaCiaAplicado)));
        $presupuestoNuevo = max(0, round($presupuestoActual + $deltaPresupuestoAplicado));
        $despigoNuevo = max(0, min(100, round($despigoActual + $deltaDespigoAplicado)));

        // Registrar evento
        $idPartidaEscenario = isset($_SESSION['partida_escenario_id_actual']) ? (int)$_SESSION['partida_escenario_id_actual'] : 0;

        $sqlEvento = "
            INSERT INTO eventos_partida (
                id_partida_escenario, id_opcion_elegida,
                tiempo_respuesta_segundos, fue_timeout,
                cia_antes, presupuesto_antes, despido_antes,
                cia_despues, presupuesto_despues, despido_despues,
                feedback_mostrado
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmtEvento = $conn->prepare($sqlEvento);
        if (!$stmtEvento) {
            throw new RuntimeException('Error prepare evento: ' . $conn->error);
        }

        $ciaNuevaInt = (int)$ciaNueva;
        $presupuestoNuevoInt = (int)$presupuestoNuevo;
        $despigoNuevoDecimal = (float)$despigoNuevo;
        $ciaActualInt = (int)round($ciaActual);
        $presupuestoActualInt = (int)round($presupuestoActual);
        $despigoActualDecimal = (float)round($despigoActual);

        $stmtEvento->bind_param(
            'iiiiiiiidds',
            $idPartidaEscenario,
            $idOpcion,
            $tiempoRespuesta,
            $fueTimeout,
            $ciaActualInt,
            $presupuestoActualInt,
            $despigoActualDecimal,
            $ciaNuevaInt,
            $presupuestoNuevoInt,
            $despigoNuevoDecimal,
            $feedbackOpcion
        );
        $stmtEvento->execute();

        $estadoFinal = evaluar_estado_final($ciaNuevaInt, $presupuestoNuevoInt, (int)$despigoNuevoDecimal);
        if ($estadoFinal['resultado'] === 'ganada' || $estadoFinal['resultado'] === 'perdida') {
            cerrar_partida(
                $conn,
                $idPartida,
                $estadoFinal['resultado'],
                $ciaNuevaInt,
                $presupuestoNuevoInt,
                (int)$despigoNuevoDecimal
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
                    'presupuesto' => $presupuestoNuevoInt,
                    'despido' => (int)$despigoNuevoDecimal
                ],
                'delta' => [
                    'delta_cia_aplicado' => round($deltaCiaAplicado, 2),
                    'delta_presupuesto_aplicado' => round($deltaPresupuestoAplicado, 2),
                    'delta_despido_aplicado' => round($deltaDespigoAplicado, 2)
                ],
                'feedback' => $feedbackOpcion
            ]);
        }

        responder([
            'ok' => true,
            'accion' => 'procesar_opcion',
            'id_partida' => $idPartida,
            'nuevo_estado' => [
                'cia' => $ciaNueva,
                'presupuesto' => $presupuestoNuevo,
                'despido' => $despigoNuevo
            ],
            'delta' => [
                'delta_cia_aplicado' => round($deltaCiaAplicado, 2),
                'delta_presupuesto_aplicado' => round($deltaPresupuestoAplicado, 2),
                'delta_despido_aplicado' => round($deltaDespigoAplicado, 2)
            ],
            'feedback' => $feedbackOpcion
        ]);
    }

    responder(['ok' => false, 'error' => 'ACCION_INVALIDA']);
} catch (Throwable $e) {
    error_log('partida_api.php: ' . $e->getMessage());
    responder(['ok' => false, 'error' => 'ERROR_INTERNO']);
}
