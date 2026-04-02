function ocultarPantallas() {
    const ids = ['login', 'register', 'forgot', 'verificarCodigo', 'config', 'partida'];
    ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('hidden');
        }
    });
}

function mostrarConfig() {
    ocultarPantallas();
    const config = document.getElementById('config');
    if (config) {
        config.classList.remove('hidden');
    }
}

function mostrarPartida() {
    ocultarPantallas();
    const partida = document.getElementById('partida');
    if (partida) {
        partida.classList.remove('hidden');
    }
}

// Estado global de la partida actual
window.estadoPartida = {
    id_partida: null,
    id_partida_escenario: null,
    cia: 50,
    presupuesto: 50,
    despido: 10,
    maxRondas: 25,
    intervalo_cronometro: null,
    tiempo_restante: 60
};

function actualizarContadores() {
    document.getElementById('stat-cia').textContent = String(Math.round(window.estadoPartida.cia));
    document.getElementById('stat-presupuesto').textContent = String(Math.round(window.estadoPartida.presupuesto));
    document.getElementById('stat-despido').textContent = String(Math.round(window.estadoPartida.despido));
}

function iniciarCronometro() {
    window.estadoPartida.tiempo_restante = 60;
    document.getElementById('cronometro').textContent = '60';

    if (window.estadoPartida.intervalo_cronometro) {
        clearInterval(window.estadoPartida.intervalo_cronometro);
    }

    window.estadoPartida.intervalo_cronometro = setInterval(function() {
        window.estadoPartida.tiempo_restante--;

        const cronometroEl = document.getElementById('cronometro');
        if (cronometroEl) {
            cronometroEl.textContent = String(window.estadoPartida.tiempo_restante);
            
            // Cambiar color a rojo cuando faltan 10 segundos
            if (window.estadoPartida.tiempo_restante <= 10) {
                cronometroEl.style.color = '#d32f2f';
            }
        }

        if (window.estadoPartida.tiempo_restante <= 0) {
            clearInterval(window.estadoPartida.intervalo_cronometro);
            aplicarTimeoutAutomatico();
        }
    }, 1000);
}

function detenerCronometro() {
    if (window.estadoPartida.intervalo_cronometro) {
        clearInterval(window.estadoPartida.intervalo_cronometro);
        window.estadoPartida.intervalo_cronometro = null;
    }
}

function aplicarTimeoutAutomatico() {
    detenerCronometro();
    deshabilitarOpciones();
    
    const body = new URLSearchParams({
        accion: 'procesar_opcion',
        id_opcion: '0', // 0 indica que es timeout, no opción real
        codigo_opcion: 'timeout',
        fue_timeout: '1',
        tiempo_respuesta: '60'
    });

    fetch('partida_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
        .then(function(response) { return response.json(); })
        .then(function(respuesta) {
            if (!respuesta.ok) {
                alert('Error aplicando timeout: ' + (respuesta.error || 'ERROR'));
                return;
            }

            window.estadoPartida.cia = respuesta.nuevo_estado.cia;
            window.estadoPartida.presupuesto = respuesta.nuevo_estado.presupuesto;
            window.estadoPartida.despido = respuesta.nuevo_estado.despido;
            actualizarContadores();

            if (respuesta.partida_finalizada) {
                mostrarFinPartida(respuesta.mensaje || 'Partida finalizada');
                return;
            }

            alert('Se acabó el tiempo.\nPenalización automática aplicada:\nCIA: ' + respuesta.delta.delta_cia_aplicado +
                  '\nPresupuesto: ' + respuesta.delta.delta_presupuesto_aplicado +
                  '\nDespido: ' + respuesta.delta.delta_despido_aplicado.toFixed(2));

            setTimeout(function() {
                cargarSiguienteEscenario();
            }, 1000);
        })
        .catch(function(err) {
            console.error(err);
            alert('Error de red al aplicar timeout');
        });
}

function deshabilitarOpciones() {
    const opcionesList = document.getElementById('opciones-lista');
    if (opcionesList) {
        const botones = opcionesList.querySelectorAll('button');
        botones.forEach(function(btn) {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
        });
    }
}

function mostrarFinPartida(mensaje) {
    detenerCronometro();
    deshabilitarOpciones();

    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');

    if (opcionesContainer) {
        opcionesContainer.style.display = 'none';
    }

    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'block';
        sinOpcionesMsg.innerHTML =
            '<p style="margin-bottom: 12px; font-weight: 700;">' + (mensaje || 'Partida finalizada') + '</p>' +
            '<button type="button" onclick="window.location.href=\'menu.php\'">Volver al Menú</button>';
    }
}

function cambiarValor(id, delta) {
    const input = document.getElementById(id);
    if (!input) return;

    let valor = Number(input.value) || 0;
    valor += delta;

    const min = input.hasAttribute('min') ? Number(input.getAttribute('min')) : null;
    const max = input.hasAttribute('max') ? Number(input.getAttribute('max')) : null;

    if (min !== null && valor < min) valor = min;
    if (max !== null && valor > max) valor = max;

    input.value = valor;
}

function procesarOpcion(idOpcion, codigoOpcion) {
    detenerCronometro();
    deshabilitarOpciones();

    const data = new URLSearchParams({
        accion: 'procesar_opcion',
        id_opcion: String(idOpcion),
        codigo_opcion: codigoOpcion,
        fue_timeout: '0',
        tiempo_respuesta: String(60 - window.estadoPartida.tiempo_restante)
    });

    fetch('partida_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
    })
        .then(function(response) { return response.json(); })
        .then(function(respuesta) {
            if (!respuesta.ok) {
                alert('Error procesando opcion: ' + (respuesta.error || 'ERROR'));
                return;
            }

            window.estadoPartida.cia = respuesta.nuevo_estado.cia;
            window.estadoPartida.presupuesto = respuesta.nuevo_estado.presupuesto;
            window.estadoPartida.despido = respuesta.nuevo_estado.despido;
            actualizarContadores();

            if (respuesta.partida_finalizada) {
                mostrarFinPartida(respuesta.mensaje || 'Partida finalizada');
                return;
            }

            alert('Impacto aplicado:\nCIA: ' + respuesta.delta.delta_cia_aplicado +
                  '\nPresupuesto: ' + respuesta.delta.delta_presupuesto_aplicado +
                  '\nDespido: ' + respuesta.delta.delta_despido_aplicado.toFixed(2) +
                  '\n\nFeedback:\n' + respuesta.feedback);

            setTimeout(function() {
                cargarSiguienteEscenario();
            }, 1000);
        })
        .catch(function(err) {
            console.error(err);
            alert('Error de red al procesar opcion');
        });
}

function renderTurno(data) {
    if (data && data.partida_finalizada) {
        mostrarFinPartida('felicidades, acabaste');
        return;
    }

    if (data && data.sin_escenarios) {
        mostrarFinPartida('No hay más escenarios disponibles para esta partida.');
        return;
    }

    if (!data || !data.turno || !data.turno.escenario) {
        alert('No hay escenario disponible.');
        return;
    }

    const turno = data.turno;
    const escenario = turno.escenario;
    const remitenteNombre = escenario.remitente_nombre || 'Sin nombre';
    const remitenteCorreo = escenario.remitente_correo || 'sin-correo';

    window.estadoPartida.id_partida = data.id_partida;
    window.estadoPartida.id_partida_escenario = turno.id_partida_escenario;

    // Guardar id_partida_escenario en sesión vía fetch silencioso
    fetch('partida_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'accion=guardar_sesion&id_partida_escenario=' + String(turno.id_partida_escenario)
    }).catch(function() { /* ignorar errores silenciosamente */ });

    document.getElementById('partida-id').textContent = String(data.id_partida || '-');
    document.getElementById('turno-orden').textContent = String(turno.orden_en_partida || '-');
    document.getElementById('esc-remitente').textContent = remitenteNombre + ' <' + remitenteCorreo + '>';
    document.getElementById('esc-tipo').textContent = escenario.tipo_escenario || '-';
    document.getElementById('esc-titulo').textContent = escenario.titulo_correo || '-';
    document.getElementById('esc-texto').textContent = escenario.texto_correo || '-';

    const opcionesContainer = document.getElementById('opciones-container');
    const opcionesList = document.getElementById('opciones-lista');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');

    // Filtrar opciones para excluir timeout (se maneja aparte con cronómetro)
    let opcionesValidas = (escenario.opciones || []).filter(function(op) {
        return op.codigo_opcion !== 'timeout';
    });

    // Fallback: algunos escenarios phishing pueden venir sin opciones por datos incompletos.
    if (escenario.tipo_escenario === 'phishing' && opcionesValidas.length === 0) {
        opcionesValidas = [
            { id_opcion: 0, codigo_opcion: 'legitimo', texto_opcion: 'Marcar como correo legitimo' },
            { id_opcion: 0, codigo_opcion: 'falso', texto_opcion: 'Reportar como phishing' }
        ];
    }

    if (!opcionesValidas || opcionesValidas.length === 0) {
        opcionesContainer.style.display = 'none';
        sinOpcionesMsg.style.display = 'block';
        sinOpcionesMsg.innerHTML = '<button type="button" onclick="cargarSiguienteEscenario()">Siguiente Escenario</button>';
        detenerCronometro();
    } else {
        opcionesContainer.style.display = 'block';
        sinOpcionesMsg.style.display = 'none';
        opcionesList.innerHTML = '';

        opcionesValidas.forEach(function(opcion) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.style.display = 'block';
            btn.style.width = '100%';
            btn.style.padding = '10px';
            btn.style.marginBottom = '5px';
            btn.style.cursor = 'pointer';
            btn.textContent = opcion.codigo_opcion + ': ' + opcion.texto_opcion;
            btn.onclick = function() {
                procesarOpcion(opcion.id_opcion, opcion.codigo_opcion);
            };
            opcionesList.appendChild(btn);
        });

        // Iniciar cronómetro solo si hay opciones
        iniciarCronometro();
    }

    mostrarPartida();
}

// Evalúa si las condiciones iniciales generan victoria o derrota automática
function evaluarCondicionesIniciales(cia, presupuesto, despido) {
    // Validar presupuesto <= 0 (immediate loss)
    if (presupuesto <= 0) {
        return { resultado: 'perdida', motivo: 'presupuesto_cero' };
    }

    // Validar despido >= 100 (immediate loss)
    if (despido >= 100) {
        return { resultado: 'perdida', motivo: 'despido_cien' };
    }

    // Condiciones de derrota por combinación de despido+cia
    if (despido > 95 && cia < 75) return { resultado: 'perdida', motivo: 'despido_gt_95_cia_lt_75' };
    if (despido > 90 && cia < 70) return { resultado: 'perdida', motivo: 'despido_gt_90_cia_lt_70' };
    if (despido > 80 && cia < 60) return { resultado: 'perdida', motivo: 'despido_gt_80_cia_lt_60' };
    if (despido > 70 && cia < 50) return { resultado: 'perdida', motivo: 'despido_gt_70_cia_lt_50' };
    if (despido > 60 && cia < 40) return { resultado: 'perdida', motivo: 'despido_gt_60_cia_lt_40' };
    if (despido > 50 && cia < 30) return { resultado: 'perdida', motivo: 'despido_gt_50_cia_lt_30' };
    if (despido > 40 && cia < 20) return { resultado: 'perdida', motivo: 'despido_gt_40_cia_lt_20' };
    if (despido > 30 && cia < 10) return { resultado: 'perdida', motivo: 'despido_gt_30_cia_lt_10' };
    if (despido > 20 && cia < 5) return { resultado: 'perdida', motivo: 'despido_gt_20_cia_lt_5' };
    if (despido > 10 && cia < 2) return { resultado: 'perdida', motivo: 'despido_gt_10_cia_lt_2' };

    // Condiciones de victoria
    if (cia === 100 && despido < 80) return { resultado: 'ganada', motivo: 'cia_100_despido_lt_80' };
    if (cia > 95 && despido < 70) return { resultado: 'ganada', motivo: 'cia_gt_95_despido_lt_70' };
    if (cia > 95 && despido < 60) return { resultado: 'ganada', motivo: 'cia_gt_95_despido_lt_60' };
    if (cia > 90 && despido < 50) return { resultado: 'ganada', motivo: 'cia_gt_90_despido_lt_50' };

    // En curso (partida normal)
    return { resultado: 'en_curso', motivo: 'sin_condicion' };
}

function iniciarPartida() {
    const cia = Number(document.getElementById('cia').value);
    const presupuesto = Number(document.getElementById('presupuesto').value);
    const despido = Number(document.getElementById('despido').value);
    const maxRondas = Number(document.getElementById('maxRondas').value);

    if (cia < 30 || cia > 80 || despido < 5 || despido > 80 || presupuesto < 10 || presupuesto > 80 || maxRondas < 15 || maxRondas > 40) {
        alert('Valores invalidos en la configuracion');
        return;
    }

    // Validar que las condiciones iniciales no sean ya victoria/derrota
    const evaluacion = evaluarCondicionesIniciales(cia, presupuesto, despido);
    if (evaluacion.resultado !== 'en_curso') {
        const tipo = evaluacion.resultado === 'ganada' ? 'GANADA' : 'PERDIDA';
        const detalle = evaluacion.motivo.replace(/_/g, ' ').toUpperCase();
        alert(`❌ NO PUEDES INICIAR CON ESTAS CONFIGURACIONES\n\nLa partida estaría ${tipo} automáticamente.\nMotivo: ${detalle}\n\nAjusta los valores CIA y Despido para un juego justo.`);
        return;
    }

    window.estadoPartida.cia = cia;
    window.estadoPartida.presupuesto = presupuesto;
    window.estadoPartida.despido = despido;
    window.estadoPartida.maxRondas = maxRondas;
    actualizarContadores();

    const body = new URLSearchParams({
        accion: 'iniciar_partida',
        cia: String(cia),
        presupuesto: String(presupuesto),
        despido: String(despido),
        maxRondas: String(maxRondas)
    });

    fetch('partida_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.ok) {
                alert('No se pudo iniciar la partida: ' + (data.error || 'ERROR'));
                return;
            }
            if (data.partida_finalizada || data.sin_escenarios || !data.turno) {
                renderTurno(data);
                return;
            }
            renderTurno(data);
        })
        .catch(function() {
            alert('Error de red al iniciar partida');
        });
}

function cargarSiguienteEscenario() {
    const body = new URLSearchParams({ accion: 'siguiente_escenario' });

    fetch('partida_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.ok) {
                alert('No se pudo cargar el escenario: ' + (data.error || 'ERROR'));
                return;
            }

            if (data.partida_finalizada) {
                mostrarFinPartida('felicidades, acabaste');
                return;
            }

            if (data.sin_escenarios) {
                alert('No hay mas escenarios disponibles para esta partida.');
                return;
            }

            renderTurno(data);
        })
        .catch(function() {
            alert('Error de red al cargar siguiente escenario');
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('view') === 'config') {
        mostrarConfig();
    }
});

