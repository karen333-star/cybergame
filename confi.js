function ocultarPantallas() {
    const ids = ['login', 'register', 'forgot', 'verificarCodigo', 'config', 'partida'];
    ids.forEach(function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('hidden');
        }
    });
}

function aplicarZoomConfiguracion(activo) {
    const zoomValor = activo ? '90%' : '';
    const overflowValor = activo ? 'hidden' : '';

    document.documentElement.style.zoom = zoomValor;
    document.documentElement.style.overflow = overflowValor;
    document.body.style.zoom = zoomValor;
    document.body.style.overflow = overflowValor;
}

function mostrarConfig() {
    ocultarPantallas();
    const config = document.getElementById('config');
    if (config) {
        config.classList.remove('hidden');
    }
    aplicarZoomConfiguracion(true);
    actualizarMedidoresConfiguracion();
}

function mostrarPartida() {
    ocultarPantallas();
    const partida = document.getElementById('partida');
    if (partida) {
        partida.classList.remove('hidden');
    }
    aplicarZoomConfiguracion(false);
}

function actualizarMedidorConfiguracion(id) {
    const input = document.getElementById(id);
    if (!input) return;

    const card = input.closest('.config-card');
    if (!card) return;

    const meter = card.querySelector('.config-meter');
    if (!meter) return;

    const barras = Array.from(meter.querySelectorAll('span'));
    if (barras.length === 0) return;

    const valor = Math.max(0, Number(input.value) || 0);
    let activas = Math.min(barras.length, Math.ceil(valor / 10));

    if (id === 'maxRondas') {
        const min = Number(input.getAttribute('min') || 0);
        const max = Number(input.getAttribute('max') || 100);
        const rango = Math.max(1, max - min);
        const normalizado = Math.max(0, Math.min(1, (valor - min) / rango));
        activas = Math.max(1, Math.min(barras.length, Math.ceil(normalizado * barras.length)));
    }

    barras.forEach(function(barra, indice) {
        barra.classList.toggle('is-on', indice < activas);
    });
}

function actualizarMedidoresConfiguracion() {
    ['cia', 'presupuesto', 'despido', 'maxRondas'].forEach(function(id) {
        actualizarMedidorConfiguracion(id);
    });
}

function actualizarNombreUsuarioPerfil() {
    const perfilEl = document.getElementById('profile-usuario');
    if (!perfilEl) {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const nombreDesdeUrl = (params.get('usuario') || '').trim();
    const nombreDesdeStorage = (localStorage.getItem('cybergame_nombre_usuario') || '').trim();
    const nombreUsuario = nombreDesdeUrl || nombreDesdeStorage;

    if (nombreUsuario) {
        perfilEl.textContent = nombreUsuario;
        if (nombreDesdeUrl && nombreDesdeUrl !== nombreDesdeStorage) {
            localStorage.setItem('cybergame_nombre_usuario', nombreDesdeUrl);
        }
    }
}

// Estado global de la partida actual
window.estadoPartida = {
    id_partida: null,
    id_partida_escenario: null,
    cia: 50,
    cia_desglose: null,
    presupuesto: 50,
    despido: 10,
    maxRondas: 25,
    opciones_actuales: [],
    intervalo_cronometro: null,
    tiempo_restante: 60,
    intervalo_espera: null,
    timeout_mostrar_repercusion: null,
    timeout_siguiente_escenario: null,
    ticker_inter_eventos: null,
    inter_evento_total_ms: 0,
    inter_evento_transcurrido_ms: 0,
    inter_evento_pausado: false,
    inter_evento_efecto_emitido: false,
    inter_evento_contexto: null,
    asunto_actual: '',
    tipo_actual: '',
    remitente_nombre_actual: '',
    remitente_correo_actual: '',
    feedback_general_actual: '',
    escenario_abierto_accionable: false,
    escenario_mail_activo_id: null,
    timeout_escenario_pendiente: null,
    finalizacionPendiente: null,
    bloqueo_final_activo: false,
    partida_finalizada: false,
    ronda_actual: 0,
    rank_global: null,
    mailbox_seq: 1,
    panel_bandeja_abierto: false,
    mailbox: {
        scenarios: { pending: [], history: [] },
        effects: { pending: [], history: [] }
    }
};

function actualizarPerfilOperativo() {
    const rondaEl = document.getElementById('profile-ronda');
    const rankEl = document.getElementById('profile-rank');

    if (rondaEl) {
        const rondaActual = Number(window.estadoPartida.ronda_actual) || 0;
        rondaEl.textContent = 'RONDA: ' + String(rondaActual);
    }

    if (rankEl) {
        const rank = window.estadoPartida.rank_global;
        rankEl.textContent = 'RANK GLOBAL: ' + (rank ? ('#' + String(rank)) : 'N/D');
    }
}

function solicitarRankGlobalUsuario() {
    const body = new URLSearchParams({ accion: 'obtener_rank_global' });

    fetch('partida_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data || !data.ok) {
                return;
            }
            window.estadoPartida.rank_global = data.rank_global || null;
            actualizarPerfilOperativo();
        })
        .catch(function() {
            // Ignorar para no afectar flujo de juego.
        });
}

function alternarVistaCentroSinCorreo(mostrar) {
    const panelResumen = document.getElementById('panel-resumen-centro');
    const panelEscenario = document.querySelector('#partida .scenario-panel');
    const panelRespuesta = document.getElementById('respuesta-panel');
    const botonResponder = document.getElementById('btn-responder-escenario');

    if (panelResumen) {
        panelResumen.style.display = mostrar ? 'block' : 'none';
    }

    if (panelEscenario) {
        panelEscenario.style.display = mostrar ? 'none' : 'block';
    }

    if (mostrar && panelRespuesta) {
        panelRespuesta.style.display = 'none';
    }

    if (mostrar && botonResponder) {
        botonResponder.style.display = 'none';
    }
}

function escaparHtml(valor) {
    return String(valor)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatearPuntajeCIA(valor) {
    const numero = Number(valor);
    return Number.isFinite(numero) ? String(Math.round(numero)) : '-';
}

function formatearDesgloseCIA(desglose) {
    if (!desglose || desglose.confidencialidad == null || desglose.integridad == null || desglose.accesibilidad == null) {
        return 'Puntaje individual no disponible';
    }

    return 'Confidencialidad ' + formatearPuntajeCIA(desglose.confidencialidad) +
        ' | Integridad ' + formatearPuntajeCIA(desglose.integridad) +
        ' | Accesibilidad ' + formatearPuntajeCIA(desglose.accesibilidad) +
        ' | CIA promedio ' + formatearPuntajeCIA(desglose.cia);
}

function formatearDesgloseCIAEvento(respuesta) {
    const desglose = respuesta && respuesta.cia_desglose;
    if (!desglose || !desglose.antes || !desglose.despues) {
        return 'Puntaje individual no disponible';
    }

    const antes = desglose.antes;
    const despues = desglose.despues;

    return 'Antes: C ' + formatearPuntajeCIA(antes.confidencialidad) +
        ' | I ' + formatearPuntajeCIA(antes.integridad) +
        ' | A ' + formatearPuntajeCIA(antes.accesibilidad) +
        ' | CIA ' + formatearPuntajeCIA(antes.cia) +
        ' | Después: C ' + formatearPuntajeCIA(despues.confidencialidad) +
        ' | I ' + formatearPuntajeCIA(despues.integridad) +
        ' | A ' + formatearPuntajeCIA(despues.accesibilidad) +
        ' | CIA ' + formatearPuntajeCIA(despues.cia);
}

function obtenerDesgloseCIAActual() {
    const desglose = window.estadoPartida.cia_desglose;
    if (!desglose) {
        return null;
    }

    if (desglose.confidencialidad == null || desglose.integridad == null || desglose.accesibilidad == null) {
        return null;
    }

    return desglose;
}

function actualizarPanelDesgloseCIA() {
    const panel = document.getElementById('panel-desglose-cia');
    if (!panel) return;

    const desglose = obtenerDesgloseCIAActual();
    if (!desglose) {
        panel.innerHTML = '<strong>CIA</strong><div style="margin-top:6px;">Puntaje individual no disponible</div>';
        return;
    }

    panel.innerHTML =
        '<div><strong>Confidencialidad</strong>: ' + escaparHtml(formatearPuntajeCIA(desglose.confidencialidad)) + '%</div>' +
        '<div><strong>Integridad</strong>: ' + escaparHtml(formatearPuntajeCIA(desglose.integridad)) + '%</div>' +
        '<div><strong>Accesibilidad</strong>: ' + escaparHtml(formatearPuntajeCIA(desglose.accesibilidad)) + '%</div>' +
        '<div style="margin-top:6px;"><strong>CIA promedio</strong>: ' + escaparHtml(formatearPuntajeCIA(desglose.cia)) + '%</div>';
}

function alternarDesgloseCIA() {
    const panel = document.getElementById('panel-desglose-cia');
    if (!panel) return;

    const visible = panel.style.display !== 'none';
    if (visible) {
        panel.style.display = 'none';
        return;
    }

    actualizarPanelDesgloseCIA();
    panel.style.display = 'block';
}

function limpiarTemporizadoresEspera() {
    if (window.estadoPartida.ticker_inter_eventos) {
        clearInterval(window.estadoPartida.ticker_inter_eventos);
        window.estadoPartida.ticker_inter_eventos = null;
    }

    if (window.estadoPartida.intervalo_espera) {
        clearInterval(window.estadoPartida.intervalo_espera);
        window.estadoPartida.intervalo_espera = null;
    }

    if (window.estadoPartida.timeout_mostrar_repercusion) {
        clearTimeout(window.estadoPartida.timeout_mostrar_repercusion);
        window.estadoPartida.timeout_mostrar_repercusion = null;
    }

    if (window.estadoPartida.timeout_siguiente_escenario) {
        clearTimeout(window.estadoPartida.timeout_siguiente_escenario);
        window.estadoPartida.timeout_siguiente_escenario = null;
    }

    window.estadoPartida.inter_evento_total_ms = 0;
    window.estadoPartida.inter_evento_transcurrido_ms = 0;
    window.estadoPartida.inter_evento_pausado = false;
    window.estadoPartida.inter_evento_efecto_emitido = false;
    window.estadoPartida.inter_evento_contexto = null;
}

function limpiarTimerEscenarioPendiente() {
    if (window.estadoPartida.timeout_escenario_pendiente) {
        clearTimeout(window.estadoPartida.timeout_escenario_pendiente);
        window.estadoPartida.timeout_escenario_pendiente = null;
    }
}

function aplicarPenalizacionAcumulacionEscenarios(monto, mensaje) {
    const penalizacion = Math.max(0, Math.round(Number(monto) || 0));
    if (penalizacion <= 0) {
        return false;
    }

    const presupuestoActual = Math.max(0, Math.round(Number(window.estadoPartida.presupuesto) || 0));
    window.estadoPartida.presupuesto = Math.max(0, presupuestoActual - penalizacion);
    actualizarContadores();

    if (mensaje) {
        actualizarEstadoEspera(mensaje);
    }

    if (window.estadoPartida.presupuesto <= 0) {
        mostrarFinPartida('Tu presupuesto llego a cero por dejar acumular escenarios.');
        return true;
    }

    return false;
}

function iniciarTimerEscenarioPendiente() {
    limpiarTimerEscenarioPendiente();

    window.estadoPartida.timeout_escenario_pendiente = setTimeout(function() {
        const pendientes = window.estadoPartida.mailbox.scenarios.pending;
        if (!pendientes || pendientes.length === 0) {
            return;
        }

        // Si el escenario ya está abierto/activo, este watchdog no aplica.
        if (window.estadoPartida.escenario_mail_activo_id) {
            return;
        }

        if (pendientes.length >= 3) {
            if (aplicarPenalizacionAcumulacionEscenarios(
                5,
                'Ya tienes 3 escenarios acumulados. Pierdes 5 por minuto adicional hasta resolver alguno.'
            )) {
                return;
            }
            iniciarTimerEscenarioPendiente();
            return;
        }

        if (aplicarPenalizacionAcumulacionEscenarios(
            10,
            'No abriste el escenario a tiempo. Se acumuló un nuevo escenario y pierdes 10.'
        )) {
            return;
        }
        cargarSiguienteEscenario();
    }, 60000);
}

function ocultarCorreoRepercusion() {
    const correoEl = document.getElementById('correo-repercusion');
    if (correoEl) {
        correoEl.style.display = 'none';
        correoEl.innerHTML = '';
    }
}

function cerrarCorreoRepercusion() {
    if (window.estadoPartida.bloqueo_final_activo) {
        return;
    }

    ocultarCorreoRepercusion();
    establecerVistaCorreoActiva('escenario');
    activarModoVisualCorreo(false);
    alternarVistaCentroSinCorreo(true);
    if (window.estadoPartida.finalizacionPendiente) {
        const msg = window.estadoPartida.finalizacionPendiente.mensaje || 'Partida finalizada';
        const resultado = window.estadoPartida.finalizacionPendiente.resultado || 'finalizada';
        window.estadoPartida.finalizacionPendiente = null;
        mostrarFinPartida(msg, resultado);
        return;
    }
    reanudarInterEventos();
}

function actualizarEstadoEspera(texto) {
    const estadoEl = document.getElementById('estado-espera');
    if (!estadoEl) return;

    if (!texto) {
        estadoEl.style.display = 'none';
        estadoEl.textContent = '';
        return;
    }

    estadoEl.style.display = 'block';
    estadoEl.textContent = texto;
}

function clamp(valor, min, max) {
    return Math.max(min, Math.min(max, valor));
}

function calcularIntervaloEsperaSegundos(presupuestoActual) {
    const presupuesto = clamp(Number(presupuestoActual) || 0, 0, 100);
    const intervalo = 15 - ((presupuesto / 100) * 9);
    return clamp(intervalo, 6, 15);
}

function elegirAleatorio(arr) {
    if (!arr || arr.length === 0) return '';
    return arr[Math.floor(Math.random() * arr.length)];
}

function siguienteIdCorreo() {
    const id = window.estadoPartida.mailbox_seq;
    window.estadoPartida.mailbox_seq += 1;
    return id;
}

function colorBotonBandeja(tipo, hayPendientes) {
    if (tipo === 'scenarios') {
        return hayPendientes ? '#b91c1c' : '#374151';
    }
    return hayPendientes ? '#b45309' : '#1f2937';
}

function actualizarBandejasUI() {
    const pendingScenarios = window.estadoPartida.mailbox.scenarios.pending.length;
    const pendingEffects = window.estadoPartida.mailbox.effects.pending.length;

    const badgeScenarios = document.getElementById('badge-scenarios');
    const badgeEffects = document.getElementById('badge-effects');
    const btnScenarios = document.getElementById('btn-bandeja-escenarios');
    const btnEffects = document.getElementById('btn-bandeja-efectos');

    if (badgeScenarios) {
        badgeScenarios.style.display = pendingScenarios > 0 ? 'inline-block' : 'none';
        badgeScenarios.textContent = pendingScenarios > 9 ? '9+' : String(pendingScenarios);
    }

    if (badgeEffects) {
        badgeEffects.style.display = pendingEffects > 0 ? 'inline-block' : 'none';
        badgeEffects.textContent = pendingEffects > 9 ? '9+' : String(pendingEffects);
    }

    if (btnScenarios) {
        btnScenarios.style.background = colorBotonBandeja('scenarios', pendingScenarios > 0);
    }

    if (btnEffects) {
        btnEffects.style.background = colorBotonBandeja('effects', pendingEffects > 0);
    }
}

function cerrarPanelBandeja() {
    if (window.estadoPartida.bloqueo_final_activo) {
        return;
    }

    const panel = document.getElementById('panel-bandeja');
    if (panel) {
        panel.style.display = 'none';
    }
    const partidaEl = document.getElementById('partida');
    if (partidaEl) {
        partidaEl.classList.remove('modo-inbox-escenarios');
    }
    window.estadoPartida.panel_bandeja_abierto = false;
}

function abrirPanelBandeja(titulo, correos, tipo) {
    if (window.estadoPartida.partida_finalizada && tipo !== 'effects') {
        return;
    }

    const panel = document.getElementById('panel-bandeja');
    const tituloEl = document.getElementById('panel-bandeja-titulo');
    const listaEl = document.getElementById('panel-bandeja-lista');
    if (!panel || !listaEl) return;

    if (tituloEl) {
        tituloEl.textContent = titulo;
    }
    listaEl.innerHTML = '';

    const partidaEl = document.getElementById('partida');
    if (partidaEl) {
        partidaEl.classList.add('modo-inbox-escenarios');
    }

    if (!correos || correos.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'bandeja-empty-msg';
        empty.textContent = 'No hay correos en esta bandeja.';
        listaEl.appendChild(empty);
    } else {
        correos.forEach(function(correo) {
            const payload = correo && correo.payload ? correo.payload : {};
            const escenario = payload && payload.data && payload.data.turno && payload.data.turno.escenario ? payload.data.turno.escenario : null;
            const remitente = escenario ? (escenario.remitente_nombre || 'Sistema') : (tipo === 'effects' ? 'Sistema' : 'SOC');
            const previewRaw = escenario ? (escenario.texto_correo || '') : ((payload && payload.contexto && payload.contexto.feedback_escenario) || (payload && payload.respuesta && payload.respuesta.feedback) || 'Sin contenido adicional.');
            const preview = String(previewRaw || '').replace(/\s+/g, ' ').trim();
            const fecha = correo && correo.createdAt ? new Date(correo.createdAt) : null;
            const hora = fecha ? fecha.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' }) : 'Hoy';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bandeja-mail-row' + (correo.pending ? ' is-new' : '');
            btn.innerHTML = '' +
                '<span class="mail-row-star" aria-hidden="true">☆</span>' +
                '<span class="mail-row-from">' + escaparHtml(remitente) + '</span>' +
                '<span class="mail-row-subject">' + escaparHtml(correo.subject || 'Sin asunto') + '</span>' +
                '<span class="mail-row-preview">' + escaparHtml(preview || 'Sin vista previa') + '</span>' +
                '<span class="mail-row-time">' + escaparHtml(hora) + '</span>';
            btn.onclick = function() {
                abrirCorreoDesdeBandeja(tipo, correo.id);
            };
            listaEl.appendChild(btn);
        });
    }

    panel.style.display = 'block';
    window.estadoPartida.panel_bandeja_abierto = true;
}

function obtenerCorreoPorId(tipo, id) {
    const mbox = window.estadoPartida.mailbox[tipo];
    if (!mbox) return null;
    const all = mbox.pending.concat(mbox.history);
    return all.find(function(c) { return c.id === id; }) || null;
}

function moverPendingAHistorial(tipo, id) {
    const mbox = window.estadoPartida.mailbox[tipo];
    if (!mbox) return null;

    const idx = mbox.pending.findIndex(function(c) { return c.id === id; });
    if (idx === -1) {
        return obtenerCorreoPorId(tipo, id);
    }

    const correo = mbox.pending[idx];
    mbox.pending.splice(idx, 1);
    correo.pending = false;
    mbox.history.unshift(correo);
    return correo;
}

function agregarCorreoBandeja(tipo, correoData) {
    const mbox = window.estadoPartida.mailbox[tipo];
    if (!mbox) return;

    const correo = {
        id: siguienteIdCorreo(),
        pending: true,
        createdAt: Date.now(),
        subject: correoData.subject || 'Sin asunto',
        payload: correoData.payload || {},
        category: tipo
    };

    mbox.pending.push(correo);
    actualizarBandejasUI();
}

function guardarCorreoFinalizacionEnHistorial(mensaje, resultado, asunto) {
    const mbox = window.estadoPartida.mailbox.effects;
    if (!mbox) return;

    const yaExiste = mbox.history.some(function(correo) {
        return correo && correo.payload && correo.payload.tipo === 'finalizacion';
    });

    if (yaExiste) {
        return;
    }

    mbox.history.unshift({
        id: siguienteIdCorreo(),
        pending: false,
        createdAt: Date.now(),
        subject: asunto || 'Notificacion oficial de cierre',
        category: 'effects',
        payload: {
            tipo: 'finalizacion',
            mensaje: mensaje || 'Partida finalizada',
            resultado: resultado === 'ganada' ? 'ganada' : 'perdida'
        }
    });

    actualizarBandejasUI();
}

function pausarInterEventos() {
    if (!window.estadoPartida.ticker_inter_eventos) return;
    window.estadoPartida.inter_evento_pausado = true;
}

function reanudarInterEventos() {
    if (!window.estadoPartida.ticker_inter_eventos) return;
    window.estadoPartida.inter_evento_pausado = false;
}

function limpiarVistaCorreoEscenario() {
    const remitenteEl = document.getElementById('esc-remitente');
    const tipoEl = document.getElementById('esc-tipo');
    const asuntoEl = document.getElementById('esc-titulo');
    const contenidoEl = document.getElementById('esc-texto');

    if (remitenteEl) remitenteEl.textContent = '-';
    if (tipoEl) tipoEl.textContent = '-';
    if (asuntoEl) asuntoEl.textContent = '-';
    if (contenidoEl) contenidoEl.textContent = '-';
}

function establecerVistaCorreoActiva(tipo) {
    const escenarioTextoEl = document.getElementById('esc-texto');
    const repercusionEl = document.getElementById('correo-repercusion');

    if (escenarioTextoEl) {
        escenarioTextoEl.style.display = (tipo === 'escenario') ? 'block' : 'none';
    }

    if (repercusionEl) {
        repercusionEl.style.display = (tipo === 'efecto') ? 'block' : 'none';
        if (tipo !== 'efecto') {
            repercusionEl.innerHTML = '';
        }
    }
}

function mostrarModalFinPartida(titulo, mensaje, esVictoria) {
    const overlay = document.getElementById('modal-fin-partida');
    const card = document.getElementById('modal-fin-card');
    const tituloEl = document.getElementById('modal-fin-titulo');
    const mensajeEl = document.getElementById('modal-fin-mensaje');
    const estadoEl = document.getElementById('modal-fin-estado');

    if (!overlay || !card || !tituloEl || !mensajeEl || !estadoEl) {
        return;
    }

    const esNeutral = esVictoria !== true && esVictoria !== false;
    const esDerrota = esVictoria === false;

    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
    card.classList.toggle('is-victory', !!esVictoria);
    card.classList.toggle('is-defeat', esDerrota);
    card.classList.toggle('is-neutral', esNeutral);
    estadoEl.textContent = esVictoria === true ? 'VICTORIA' : (esDerrota ? 'DERROTA' : 'NEUTRAL');
    tituloEl.textContent = titulo || 'Partida finalizada';
    mensajeEl.textContent = mensaje || 'La partida terminó.';
}

function cerrarCorreoEscenarioVista() {
    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
    if (opcionesContainer) {
        opcionesContainer.style.display = 'none';
    }
    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'none';
        sinOpcionesMsg.innerHTML = '';
    }

    limpiarVistaCorreoEscenario();
    activarModoVisualCorreo(false);
    alternarVistaCentroSinCorreo(true);

    window.estadoPartida.escenario_abierto_accionable = false;
}

function alternarOpcionesEscenario(mostrar) {
    if (!window.estadoPartida.escenario_abierto_accionable) {
        return;
    }

    const respuestaPanel = document.getElementById('respuesta-panel');
    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
    const botonResponder = document.getElementById('btn-responder-escenario');

    const debeMostrar = typeof mostrar === 'boolean'
        ? mostrar
        : !!(respuestaPanel && respuestaPanel.style.display === 'none');

    if (respuestaPanel) {
        respuestaPanel.style.display = debeMostrar ? 'block' : 'none';
    }

    if (opcionesContainer) {
        opcionesContainer.style.display = debeMostrar ? 'block' : 'none';
    }

    if (botonResponder) {
        botonResponder.textContent = 'Responder';
    }

    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'none';
    }
}

function activarModoVisualCorreo(activo) {
    const panelEscenario = document.querySelector('#partida .scenario-panel');
    if (!panelEscenario) {
        return;
    }
    panelEscenario.classList.toggle('gmail-open', !!activo);
}

function activarBloqueoFinalizacion(activo) {
    window.estadoPartida.bloqueo_final_activo = !!activo;
    const partidaEl = document.getElementById('partida');
    if (!partidaEl) {
        return;
    }
    partidaEl.classList.toggle('modo-finalizacion-bloqueo', !!activo);
}

function cerrarCorreosAbiertosSilencioso() {
    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
    const respuestaPanel = document.getElementById('respuesta-panel');
    const botonResponder = document.getElementById('btn-responder-escenario');

    ocultarCorreoRepercusion();
    limpiarVistaCorreoEscenario();
    activarModoVisualCorreo(false);
    window.estadoPartida.escenario_abierto_accionable = false;

    if (opcionesContainer) opcionesContainer.style.display = 'none';
    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'none';
        sinOpcionesMsg.innerHTML = '';
    }
    if (respuestaPanel) respuestaPanel.style.display = 'none';
    if (botonResponder) botonResponder.style.display = 'none';

    alternarVistaCentroSinCorreo(true);
}

function hayCorreoAbiertoEnCentro() {
    const escTexto = document.getElementById('esc-texto');
    const repercusion = document.getElementById('correo-repercusion');

    const escenarioVisible = !!(
        escTexto &&
        escTexto.style.display !== 'none' &&
        String(escTexto.innerHTML || '').trim() !== '' &&
        String(escTexto.textContent || '').trim() !== '-'
    );

    const efectoVisible = !!(
        repercusion &&
        repercusion.style.display !== 'none' &&
        String(repercusion.innerHTML || '').trim() !== ''
    );

    return escenarioVisible || efectoVisible;
}

function abrirBandeja(tipo) {
    if (tipo !== 'scenarios' && tipo !== 'effects') return;

    if (window.estadoPartida.bloqueo_final_activo) {
        return;
    }

    if (window.estadoPartida.partida_finalizada && tipo !== 'effects') {
        return;
    }

    cerrarCorreosAbiertosSilencioso();

    const pendientes = window.estadoPartida.mailbox[tipo].pending;
    const historial = window.estadoPartida.mailbox[tipo].history;

    const correos = pendientes.concat(historial);
    const tituloBase = tipo === 'scenarios' ? 'Bandeja de escenarios' : 'Bandeja de efectos';
    const titulo = pendientes.length > 0 ? (tituloBase + ' (' + pendientes.length + ' nuevos)') : tituloBase;

    abrirPanelBandeja(
        titulo,
        correos,
        tipo
    );
}

function abrirCorreoDesdeBandeja(tipo, correoId) {
    if (window.estadoPartida.bloqueo_final_activo) {
        return;
    }

    if (window.estadoPartida.partida_finalizada && tipo !== 'effects') {
        return;
    }

    const eraPendiente = window.estadoPartida.mailbox[tipo].pending.some(function(c) { return c.id === correoId; });
    let correo = null;
    if (tipo === 'scenarios' && eraPendiente) {
        correo = obtenerCorreoPorId(tipo, correoId);
    } else {
        correo = moverPendingAHistorial(tipo, correoId);
    }
    if (!correo) return;

    cerrarPanelBandeja();
    alternarVistaCentroSinCorreo(false);
    const partidaEl = document.getElementById('partida');
    if (partidaEl) {
        partidaEl.classList.remove('modo-inbox-escenarios');
    }
    actualizarBandejasUI();

    if (tipo === 'effects') {
        window.estadoPartida.escenario_abierto_accionable = false;
        const botonResponder = document.getElementById('btn-responder-escenario');
        const respuestaPanel = document.getElementById('respuesta-panel');
        const opcionesContainer = document.getElementById('opciones-container');
        const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
        if (botonResponder) botonResponder.style.display = 'none';
        if (respuestaPanel) respuestaPanel.style.display = 'none';
        if (opcionesContainer) opcionesContainer.style.display = 'none';
        if (sinOpcionesMsg) {
            sinOpcionesMsg.style.display = 'none';
            sinOpcionesMsg.innerHTML = '';
        }

        if (correo.payload && correo.payload.tipo === 'finalizacion') {
            mostrarCorreoFinalizacion(
                correo.payload.mensaje || 'Partida finalizada',
                correo.payload.resultado || 'perdida',
                { bloqueante: false }
            );
            return;
        }

        if (correo.payload && correo.payload.tipo === 'ajuste_trimestral') {
            mostrarCorreoAjusteTrimestral(correo.payload, true);
            return;
        }
        mostrarCorreoRepercusion(correo.payload.respuesta, correo.payload.contexto, true);
        return;
    }

    if (tipo === 'scenarios') {
        if (eraPendiente) {
            window.estadoPartida.escenario_mail_activo_id = correo.id;
            limpiarTimerEscenarioPendiente();
            mostrarEscenarioEnVista(correo.payload.data, true);
            return;
        }

        const dataHist = JSON.parse(JSON.stringify(correo.payload.data || {}));
        if (dataHist && dataHist.turno && dataHist.turno.escenario) {
            dataHist.turno.escenario.respuesta_historial = correo.payload.respuestaSeleccionada || null;
        }
        mostrarEscenarioEnVista(dataHist, false);
    }
}

function resolverEscenarioActivo(codigoOpcion, textoOpcion) {
    const pending = window.estadoPartida.mailbox.scenarios.pending;
    if (!pending || pending.length === 0) return;

    let idx = -1;
    if (window.estadoPartida.escenario_mail_activo_id) {
        idx = pending.findIndex(function(c) { return c.id === window.estadoPartida.escenario_mail_activo_id; });
    }
    if (idx === -1) {
        idx = 0;
    }

    const correo = pending[idx];
    pending.splice(idx, 1);
    correo.pending = false;
    correo.payload = correo.payload || {};
    correo.payload.resuelto = true;
    correo.payload.respuestaSeleccionada = {
        codigo: codigoOpcion || 'timeout',
        texto: textoOpcion || 'No responder en el tiempo limite'
    };
    window.estadoPartida.mailbox.scenarios.history.unshift(correo);
    window.estadoPartida.escenario_mail_activo_id = null;
    limpiarTimerEscenarioPendiente();
    actualizarBandejasUI();
}

function clasificarCambioCia(deltaCia) {
    if (deltaCia > 7) return 'buena';
    if (deltaCia < -7) return 'mala';
    return 'regular';
}

function obtenerTextoOpcionElegida(codigoOpcion) {
    const codigo = String(codigoOpcion || '').toLowerCase();
    if (codigo === 'timeout') {
        return 'No responder en el tiempo limite';
    }

    const opciones = window.estadoPartida.opciones_actuales || [];
    const encontrada = opciones.find(function(op) {
        return String(op.codigo_opcion || '').toLowerCase() === codigo;
    });
    return encontrada ? encontrada.texto_opcion : ('Opcion ' + String(codigoOpcion || '').toUpperCase());
}

function avanzarDesdeCorreoInformativo() {
    if (window.estadoPartida.partida_finalizada) {
        return;
    }

    // Evita estado residual de timers que deja el boton sin efecto.
    limpiarTemporizadoresEspera();
    actualizarEstadoEspera('');

    resolverEscenarioActivo('informativo', 'Correo informativo leido');

    const respuestaSintetica = {
        ok: true,
        nuevo_estado: {
            cia: window.estadoPartida.cia,
            presupuesto: window.estadoPartida.presupuesto,
            despido: window.estadoPartida.despido
        },
        delta: {
            delta_cia_aplicado: 0,
            delta_presupuesto_aplicado: 0,
            delta_despido_aplicado: 0
        },
        feedback: window.estadoPartida.feedback_general_actual || 'Correo informativo revisado.'
    };

    cerrarCorreoEscenarioVista();
    establecerVistaCorreoActiva('escenario');
    iniciarFlujoEntreEscenarios(respuestaSintetica, {
        fueTimeout: false,
        motivo: 'informativo',
        codigoOpcion: 'informativo',
        suppressEffectMail: true
    });
}

function clasificarCambioDespido(deltaDespido) {
    if (deltaDespido < -7) return 'buena';
    if (deltaDespido > 7) return 'mala';
    return 'regular';
}

function obtenerMensajeEje(tipoEje, clase) {
    const mensajes = {
        cia: {
            buena: [
                'Felicitaciones: esta decision fortalecio claramente la proteccion de la informacion.',
                'Excelente jugada, se nota una mejora real en seguridad.',
                'Muy bien ejecutado: la CIA quedo mas robusta tras esta accion.'
            ],
            regular: [
                '',
                'La CIA se movio poco; no es critico, pero podemos afinarlo.',
                'Impacto moderado en CIA por ahora.'
            ],
            mala: [
                'Podemos mejorarlo despues: esta decision debilito la proteccion de la informacion.',
                'Aqui nos pego, pero podemos corregirlo en el siguiente turno.',
                'Tomemos esto como aprendizaje para reforzar la CIA en la proxima decision.'
            ]
        },
        despido: {
            buena: [
                'Buena senal en direccion: aumento la confianza en tu gestion.',
                'Felicitaciones: la reaccion de jefatura fue favorable.',
                'Este resultado te deja mejor parado frente al jefe.'
            ],
            regular: [
                '',
                'La percepcion de jefatura se mantuvo casi igual por ahora.',
                'Sin cambios criticos en la percepcion directiva.'
            ],
            mala: [
                'Podemos mejorarlo despues: esta accion aumento la preocupacion de jefatura.',
                'La direccion quedo inconforme, pero es recuperable en el siguiente turno.',
                'Nos costo en percepcion directiva, conviene ajustar estrategia.'
            ]
        }
    };

    const pool = (mensajes[tipoEje] && mensajes[tipoEje][clase]) ? mensajes[tipoEje][clase] : [''];
    return elegirAleatorio(pool) || '';
}

function direccionCia(deltaCia) {
    if (deltaCia > 0) return 'mejoro';
    if (deltaCia < 0) return 'empeoro';
    return 'se mantuvo';
}

function direccionDespido(deltaDespido) {
    if (deltaDespido > 0) return 'subio';
    if (deltaDespido < 0) return 'bajo';
    return 'se mantuvo';
}

function formatearCambioConSigno(valor, sufijo) {
    const numero = Number(valor) || 0;
    const abs = Math.abs(numero).toFixed(2).replace(/\.00$/, '');
    const signo = numero >= 0 ? '+' : '-';
    return signo + abs + (sufijo || '');
}

function renderCorreoGmail(opts) {
    const asunto = opts.asunto || 'Sin asunto';
    const deNombre = opts.deNombre || 'Equipo interno';
    const deCorreo = opts.deCorreo || 'soporte@cybergame.local';
    const paraNombre = opts.paraNombre || 'Jefe de Seguridad';
    const paraCorreo = opts.paraCorreo || 'usuario@cybergame.local';
    const cuerpo = opts.cuerpo || '';
    const feedbackEscenario = opts.feedbackEscenario || '';
    const feedbackRespuesta = opts.feedbackRespuesta || '';
    const detalleCIA = opts.detalleCIA || '';
    const firmaNombre = opts.firmaNombre || deNombre;
    const firmaCargo = opts.firmaCargo || 'Equipo interno';
    const firmaCorreo = opts.firmaCorreo || deCorreo;
    const cierre = opts.cierre || '';
    const colorEtiqueta = opts.colorEtiqueta || '#ff4f88';
    const compacto = !!opts.compacto;
    const ocultarFooter = !!opts.ocultarFooter;
    const mostrarCerrar = !!opts.mostrarCerrar;
    const inicial = String(deNombre || 'M').trim().charAt(0).toUpperCase() || 'M';

    return '' +
        '<div class="correo-card correo-gmail' + (compacto ? ' correo-gmail-compacto' : '') + '" style="--correo-accent:' + escaparHtml(colorEtiqueta) + ';">' +
            '<div class="correo-topbar">' +
                '<div class="correo-headline"><div class="correo-subject">' + escaparHtml(asunto) + '</div><span class="correo-inbox-tag">Recibidos</span></div>' +
                '<div class="correo-time">Hoy</div>' +
                (mostrarCerrar ? '<button type="button" class="correo-close-x" onclick="cerrarCorreoRepercusion()" aria-label="Cerrar correo">✖</button>' : '') +
            '</div>' +
            '<div class="correo-sender-row">' +
                '<div class="correo-avatar" aria-hidden="true">' + escaparHtml(inicial) + '</div>' +
                '<div class="correo-sender-meta">' +
                    '<strong class="correo-sender-name">' + escaparHtml(deNombre) + '</strong>' +
                    '<small class="correo-sender-email">&lt;' + escaparHtml(deCorreo) + '&gt;</small>' +
                    '<small class="correo-sender-to">para ' + escaparHtml(paraNombre) + ' &lt;' + escaparHtml(paraCorreo) + '&gt;</small>' +
                '</div>' +
            '</div>' +
            '<div class="correo-body">' +
                '<p>' + escaparHtml(cuerpo) + '</p>' +
            '</div>' +
            (compacto ? '' : (
                '<div class="correo-feeds">' +
                    '<div><strong>Feedback del escenario:</strong> ' + escaparHtml(feedbackEscenario) + '</div>' +
                    '<div><strong>Feedback de tu respuesta:</strong> ' + escaparHtml(feedbackRespuesta) + '</div>' +
                    '<div><strong>Desglose CIA:</strong> ' + escaparHtml(detalleCIA) + '</div>' +
                '</div>' +
                (ocultarFooter ? '' : (
                    '<div class="correo-footer">' +
                        '<div class="correo-signature">' +
                            '<strong>' + escaparHtml(firmaNombre) + '</strong><br>' +
                            escaparHtml(firmaCargo) + '<br>' +
                            escaparHtml(firmaCorreo) +
                        '</div>' +
                        (cierre ? '<div class="correo-closing">' + escaparHtml(cierre) + '</div>' : '') +
                    '</div>'
                ))
            )) +
        '</div>';
}

function construirCorreoAjusteTrimestral(ajuste) {
    const monto = Number(ajuste && ajuste.monto || 0);
    const despido = Number(ajuste && ajuste.despido_actual || window.estadoPartida.despido || 0);
    const presupuesto = Number(ajuste && ajuste.presupuesto_despues || window.estadoPartida.presupuesto || 0);

    const tipoMovimiento = monto < 0 ? 'recorte por rendimiento' : 'bono trimestral';
    const sinAjuste = monto === 0;
    const movimientoTexto = formatearCambioConSigno(monto, 'M');

    const asuntos = [
        'Actualizacion trimestral de presupuesto',
        'Movimiento trimestral aprobado',
        'Informe financiero trimestral'
    ];

    const plantillas = [
        'Jefe, te confirmo que ya se libero el ' + tipoMovimiento + ': ' + movimientoTexto + '. Con el nivel de riesgo actual, quedamos con ' + presupuesto + 'M para operar.',
        'CISO, acaba de llegar actualizacion de finanzas: se aplico el ' + tipoMovimiento + ' por ' + movimientoTexto + '. Presupuesto disponible ahora: ' + presupuesto + 'M.',
        'Te paso novedad de comite: se ejecuto el corte trimestral y el presupuesto se movio ' + movimientoTexto + ' segun tu indicador de despido (' + despido + '%). Saldo actual: ' + presupuesto + 'M.',
        'Ya quedo registrado el ajuste trimestral de presupuesto: ' + movimientoTexto + '. Con el estado actual de riesgo, la caja operativa queda en ' + presupuesto + 'M.',
        'Informe rapido: RRHH y direccion cerraron revision trimestral y autorizaron ' + tipoMovimiento + ' de ' + movimientoTexto + ' en presupuesto. Presupuesto vigente: ' + presupuesto + 'M.',
        'Finanzas notifico actualizacion trimestral: el presupuesto cambio ' + movimientoTexto + ' por la evaluacion de riesgo actual (' + despido + '%). Quedamos con ' + presupuesto + 'M.',
        'Cierro con esta novedad: ya entro el movimiento trimestral de presupuesto por ' + movimientoTexto + '. Con esto, el presupuesto operativo queda en ' + presupuesto + 'M.'
    ];

    const plantillasSinAjuste = [
        'Jefe, ya cerramos el corte trimestral: en este caso no hay bono trimestral. El presupuesto se mantiene en ' + presupuesto + 'M.',
        'CISO, te confirmo la revision de finanzas: esta vez no hay bono trimestral y el presupuesto se mantiene en ' + presupuesto + 'M.',
        'Te comparto la novedad del comite: para este corte no aplica bono trimestral. El presupuesto queda igual en ' + presupuesto + 'M.',
        'Actualizacion del trimestre: no hay bono trimestral en este ciclo y el presupuesto se mantiene sin cambios en ' + presupuesto + 'M.',
        'Informe rapido: con un indicador de despido de ' + despido + '%, este trimestre no hay bono. Presupuesto operativo sin cambios: ' + presupuesto + 'M.'
    ];

    return {
        asunto: elegirAleatorio(asuntos),
        cuerpo: elegirAleatorio(sinAjuste ? plantillasSinAjuste : plantillas),
        cierre: 'Seguimos monitoreando indicadores para el proximo corte.',
        firma: obtenerFirmaCorreo()
    };
}

function mostrarCorreoAjusteTrimestral(payload, mostrarCerrar) {
    const correoEl = document.getElementById('correo-repercusion');
    if (!correoEl) return;

    const correo = construirCorreoAjusteTrimestral((payload && payload.ajuste) || {});

    correoEl.innerHTML = renderCorreoGmail({
        asunto: correo.asunto,
        deNombre: correo.firma.nombre,
        deCorreo: correo.firma.correo,
        paraNombre: 'Jefe de Seguridad',
        paraCorreo: 'jefe.seguridad@empresa.local',
        cuerpo: correo.cuerpo,
        feedbackEscenario: 'Actualizacion administrativa trimestral.',
        feedbackRespuesta: correo.cierre,
        detalleCIA: 'Sin variacion operativa directa.',
        firmaNombre: correo.firma.nombre,
        firmaCargo: correo.firma.cargo,
        firmaCorreo: correo.firma.correo,
        cierre: mostrarCerrar ? ' ' : '',
        ocultarFooter: true,
        mostrarCerrar: !!mostrarCerrar
    });

    window.estadoPartida.escenario_abierto_accionable = false;
    const botonResponder = document.getElementById('btn-responder-escenario');
    const respuestaPanel = document.getElementById('respuesta-panel');
    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
    if (botonResponder) botonResponder.style.display = 'none';
    if (respuestaPanel) respuestaPanel.style.display = 'none';
    if (opcionesContainer) opcionesContainer.style.display = 'none';
    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'none';
        sinOpcionesMsg.innerHTML = '';
    }

    alternarVistaCentroSinCorreo(false);
    establecerVistaCorreoActiva('efecto');
    activarModoVisualCorreo(true);
}

function obtenerFirmaCorreo() {
    const nombre = (window.estadoPartida.remitente_nombre_actual || '').trim();
    const correo = (window.estadoPartida.remitente_correo_actual || '').trim();

    if (nombre) {
        return {
            nombre: nombre,
            cargo: 'Equipo interno',
            correo: correo || 'soporte@cybergame.local'
        };
    }

    const firmas = [
        { nombre: 'Laura Perez', cargo: 'Analista SOC', correo: 'soc@empresa.local' },
        { nombre: 'Andres Gomez', cargo: 'Equipo TI', correo: 'ti@empresa.local' },
        { nombre: 'Camila Ruiz', cargo: 'Auditoria Interna', correo: 'auditoria@empresa.local' },
        { nombre: 'Daniela Torres', cargo: 'Coordinacion RRHH', correo: 'rrhh@empresa.local' }
    ];

    return elegirAleatorio(firmas);
}

function detectarResultadoPhishing(codigoOpcion, feedbackOpcion) {
    const codigo = String(codigoOpcion || '').toLowerCase();
    const feedback = String(feedbackOpcion || '').toLowerCase();
    const esIncorrecto = /\bincorrect|\berror|no se reporto|malicioso|fraude/.test(feedback);
    const esCorrecto = /\bcorrect|\bacert/.test(feedback) && !esIncorrecto;

    if (codigo === 'falso' && esIncorrecto) {
        return 'falso_pero_legitimo';
    }

    if (codigo === 'legitimo' && esIncorrecto) {
        return 'legitimo_pero_falso';
    }

    if (codigo === 'falso' && esCorrecto) {
        return 'acierto_reporte';
    }

    if (codigo === 'legitimo' && esCorrecto) {
        return 'acierto_legitimo';
    }

    if (codigo === 'timeout') {
        return 'timeout';
    }

    return 'indeterminado';
}

function construirCorreoRepercusion(respuesta, contexto) {
    const asuntoEscenario = window.estadoPartida.asunto_actual || 'escenario actual';
    const tipoEscenario = String(window.estadoPartida.tipo_actual || '').toLowerCase();
    const feedbackEscenario = window.estadoPartida.feedback_general_actual || 'Mantener buenas practicas reduce riesgos futuros.';
    const feedbackOpcion = respuesta.feedback || 'Revisa tu decision para mejorar en el siguiente turno.';
    const detalleCIA = formatearDesgloseCIAEvento(respuesta);

    const deltaCia = Number(respuesta.delta.delta_cia_aplicado || 0);
    const deltaDespido = Number(respuesta.delta.delta_despido_aplicado || 0);
    const deltaPresupuesto = Number(respuesta.delta.delta_presupuesto_aplicado || 0);

    const claseCia = clasificarCambioCia(deltaCia);
    const claseDespido = clasificarCambioDespido(deltaDespido);
    const mensajeCia = obtenerMensajeEje('cia', claseCia);
    const mensajeDespido = obtenerMensajeEje('despido', claseDespido);

    const dirCia = direccionCia(deltaCia);
    const dirDespido = direccionDespido(deltaDespido);
    const codigoOpcion = String((contexto && contexto.codigoOpcion) || '').toLowerCase();

    const asuntos = [
        'Seguimiento - ' + asuntoEscenario,
        'Actualizacion de incidente - ' + asuntoEscenario,
        'Informe rapido - ' + asuntoEscenario
    ];

    const cierres = [
        'Quedo atento por si deseas ajustar la estrategia del proximo turno.',
        'Te mantengo al tanto de cualquier novedad del siguiente correo.',
        'Si quieres, preparo recomendaciones para la siguiente decision.'
    ];

    const fraseInicio = contexto && contexto.fueTimeout
        ? 'La accion automatica por falta de respuesta ya mostro consecuencias.'
        : 'La decision que tomaste ya mostro consecuencias en la operacion.';

    const plantillas = [
        function() {
            return 'Hola CISO, te actualizo sobre ' + asuntoEscenario + '. ' + fraseInicio +
                ' El presupuesto se modifico en ' + formatearCambioConSigno(deltaPresupuesto, 'M') + '. ' +
                'Desde auditoria nos comentan que la CIA ' + dirCia + ' ' + Math.abs(deltaCia).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                'Tambien se escucha en direccion que la probabilidad de despido ' + dirDespido + ' ' + Math.abs(deltaDespido).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                mensajeCia + ' ' + mensajeDespido;
        },
        function() {
            return 'Buen dia, reporto novedades del caso ' + asuntoEscenario + '. ' + fraseInicio +
                ' Vimos un cambio de ' + formatearCambioConSigno(deltaPresupuesto, 'M') + ' en presupuesto. ' +
                'El equipo auditor indica que la CIA ' + dirCia + ' ' + Math.abs(deltaCia).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                'En paralelo, la percepcion del jefe hizo que el despido ' + dirDespido + ' ' + Math.abs(deltaDespido).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                mensajeCia + ' ' + mensajeDespido;
        },
        function() {
            return 'Te escribo para cerrar el seguimiento de ' + asuntoEscenario + '. ' + fraseInicio +
                ' Presupuesto: ' + formatearCambioConSigno(deltaPresupuesto, 'M') + '. ' +
                'Segun auditoria interna, la CIA ' + dirCia + ' ' + Math.abs(deltaCia).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                'Desde gerencia, el ambiente cambio y ahora el despido ' + dirDespido + ' ' + Math.abs(deltaDespido).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                mensajeCia + ' ' + mensajeDespido;
        },
        function() {
            return 'Hola, te comparto el balance rapido de ' + asuntoEscenario + '. ' + fraseInicio +
                ' El movimiento en presupuesto fue ' + formatearCambioConSigno(deltaPresupuesto, 'M') + '. ' +
                'El auditor reporta que la CIA ' + dirCia + ' ' + Math.abs(deltaCia).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                'Ademas, en el comite se percibe que tu probabilidad de despido ' + dirDespido + ' ' + Math.abs(deltaDespido).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                mensajeCia + ' ' + mensajeDespido;
        },
        function() {
            return 'Reporte interno sobre ' + asuntoEscenario + ': ya tenemos efectos de tu decision. ' +
                'Variacion de presupuesto: ' + formatearCambioConSigno(deltaPresupuesto, 'M') + '. ' +
                'En seguridad, la CIA ' + dirCia + ' ' + Math.abs(deltaCia).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                'Y por el lado politico, varios jefes comentan que el despido ' + dirDespido + ' ' + Math.abs(deltaDespido).toFixed(2).replace(/\.00$/, '') + ' puntos. ' +
                mensajeCia + ' ' + mensajeDespido;
        }
    ];

    const resultadoPhishing = detectarResultadoPhishing(codigoOpcion, feedbackOpcion);

    const phishingTemplates = {
        falso_pero_legitimo: [
            'Jefe, revisamos el correo con asunto "' + asuntoEscenario + '" y era legitimo, pero se reporto como phishing por error. Esto genero friccion interna y retraso operativo.',
            'Jefe, el correo "' + asuntoEscenario + '" era valido y lo marcamos como phishing. Ya informamos al area para corregir el flujo.',
            'Jefe, confirmamos que "' + asuntoEscenario + '" era un correo legitimo. El reporte como phishing fue un falso positivo y afecto tiempos de gestion.'
        ],
        legitimo_pero_falso: [
            'Jefe, el correo con asunto "' + asuntoEscenario + '" resulto ser phishing y fue tratado como legitimo. Ya activamos contencion inmediata.',
            'Jefe, confirmamos que "' + asuntoEscenario + '" era un intento de fraude. Al confiar en el, aumento la exposicion del area.',
            'Jefe, "' + asuntoEscenario + '" si era phishing y no se identifico a tiempo. Estamos mitigando el impacto.'
        ],
        acierto_reporte: [
            'Jefe, el intento de phishing que reportaste ya fue verificado y solucionado.',
            'Jefe, el correo con asunto "' + asuntoEscenario + '" era phishing y se contuvo a tiempo gracias al reporte.',
            'Jefe, confirmamos que el phishing en "' + asuntoEscenario + '" fue detectado correctamente y ya se cerro el incidente.'
        ],
        acierto_legitimo: [
            'Jefe, validamos el correo con asunto "' + asuntoEscenario + '" y era legitimo. Se gestiono sin incidentes.',
            'Jefe, el correo "' + asuntoEscenario + '" fue confirmado como valido y la operacion continuo con normalidad.'
        ],
        timeout: [
            'Jefe, el correo con asunto "' + asuntoEscenario + '" no recibio respuesta a tiempo y se activo el protocolo de contingencia.',
            'Jefe, por falta de respuesta en "' + asuntoEscenario + '" tuvimos que aplicar una accion automatica de emergencia.'
        ],
        indeterminado: [
            'Jefe, estamos cerrando el analisis del correo con asunto "' + asuntoEscenario + '" y consolidando hallazgos del evento.',
            'Jefe, ya se reviso el incidente de "' + asuntoEscenario + '" y tenemos trazabilidad completa de la respuesta.'
        ]
    };

    const cuerpoPhishingBase = elegirAleatorio(phishingTemplates[resultadoPhishing] || phishingTemplates.indeterminado);
    const cuerpoPhishing = cuerpoPhishingBase + ' Esto tuvo estos efectos en la compania: presupuesto ' +
        formatearCambioConSigno(deltaPresupuesto, 'M') + ', CIA ' + dirCia + ' ' + Math.abs(deltaCia).toFixed(2).replace(/\.00$/, '') +
        ' puntos y probabilidad de despido ' + dirDespido + ' ' + Math.abs(deltaDespido).toFixed(2).replace(/\.00$/, '') +
        ' puntos. ' + mensajeCia + ' ' + mensajeDespido;

    const firma = obtenerFirmaCorreo();

    return {
        asunto: elegirAleatorio(asuntos),
        cuerpo: tipoEscenario === 'phishing' ? cuerpoPhishing : elegirAleatorio(plantillas)(),
        feedbackEscenario: feedbackEscenario,
        feedbackOpcion: feedbackOpcion,
        detalleCIA: detalleCIA,
        cierre: elegirAleatorio(cierres),
        firma: firma
    };
}

function mostrarCorreoRepercusion(respuesta, contexto, mostrarCerrar) {
    const correoEl = document.getElementById('correo-repercusion');
    if (!correoEl) return;

    const correo = construirCorreoRepercusion(respuesta, contexto);

    correoEl.innerHTML = renderCorreoGmail({
        asunto: correo.asunto,
        deNombre: correo.firma.nombre,
        deCorreo: correo.firma.correo,
        paraNombre: 'Jefe de Seguridad',
        paraCorreo: 'jefe.seguridad@empresa.local',
        cuerpo: correo.cuerpo,
        feedbackEscenario: correo.feedbackEscenario,
        feedbackRespuesta: correo.feedbackOpcion,
        detalleCIA: correo.detalleCIA,
        firmaNombre: correo.firma.nombre,
        firmaCargo: correo.firma.cargo,
        firmaCorreo: correo.firma.correo,
        cierre: correo.cierre,
        colorEtiqueta: '#ff4f88',
        ocultarFooter: true,
        mostrarCerrar: !!mostrarCerrar
    });

    window.estadoPartida.escenario_abierto_accionable = false;
    const botonResponder = document.getElementById('btn-responder-escenario');
    const respuestaPanel = document.getElementById('respuesta-panel');
    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
    if (botonResponder) botonResponder.style.display = 'none';
    if (respuestaPanel) respuestaPanel.style.display = 'none';
    if (opcionesContainer) opcionesContainer.style.display = 'none';
    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'none';
        sinOpcionesMsg.innerHTML = '';
    }

    alternarVistaCentroSinCorreo(false);
    establecerVistaCorreoActiva('efecto');
    activarModoVisualCorreo(true);
}

function construirCorreoFinalizacion(mensaje, resultado) {
    const esVictoria = resultado === 'ganada';
    const asunto = esVictoria
        ? 'Notificacion oficial - Proceso de jubilacion'
        : 'Notificacion oficial - Terminacion laboral';

    const cuerpo = esVictoria
        ? 'Direccion y RRHH confirman tu salida por jubilacion tras cerrar con exito tu ciclo como CISO. Tu gestion logro los objetivos y se aprueba el cierre de etapa.'
        : 'Direccion y RRHH informan la finalizacion inmediata de tu contrato como CISO por el resultado acumulado de riesgo operativo.';

    return {
        asunto: asunto,
        cuerpo: cuerpo,
        mensaje: mensaje || (esVictoria ? 'Has finalizado la partida con victoria.' : 'Has finalizado la partida con derrota.'),
        firmaNombre: 'Recursos Humanos',
        firmaCargo: 'Direccion de Talento',
        firmaCorreo: 'rrhh@empresa.local'
    };
}

function aceptarFinalizacionPartida() {
    const payload = window.estadoPartida.finalizacionPendiente;
    window.estadoPartida.finalizacionPendiente = null;
    activarBloqueoFinalizacion(false);

    if (!payload) {
        mostrarFinPartida('Partida finalizada', 'perdida');
        return;
    }

    mostrarFinPartida(payload.mensaje || 'Partida finalizada', payload.resultado || 'perdida');
}

function mostrarCorreoFinalizacion(mensaje, resultado, opciones) {
    const opts = opciones || {};
    const bloqueante = opts.bloqueante !== false;
    const resultadoNormalizado = resultado === 'ganada' ? 'ganada' : 'perdida';
    const correoEl = document.getElementById('correo-repercusion');
    if (!correoEl) {
        mostrarFinPartida(mensaje || 'Partida finalizada', resultadoNormalizado);
        return;
    }

    const correo = construirCorreoFinalizacion(mensaje, resultadoNormalizado);

    window.estadoPartida.partida_finalizada = true;
    window.estadoPartida.finalizacionPendiente = bloqueante ? {
        mensaje: correo.mensaje,
        resultado: resultadoNormalizado
    } : null;

    detenerCronometro();
    limpiarTemporizadoresEspera();
    limpiarTimerEscenarioPendiente();
    actualizarEstadoEspera('');
    cerrarPanelBandeja();
    activarBloqueoFinalizacion(bloqueante);

    guardarCorreoFinalizacionEnHistorial(correo.mensaje, resultadoNormalizado, correo.asunto);

    correoEl.innerHTML = renderCorreoGmail({
        asunto: correo.asunto,
        deNombre: correo.firmaNombre,
        deCorreo: correo.firmaCorreo,
        paraNombre: 'Jefe de Seguridad',
        paraCorreo: 'jefe.seguridad@empresa.local',
        cuerpo: correo.cuerpo,
        feedbackEscenario: 'Cierre oficial de partida.',
        feedbackRespuesta: correo.mensaje,
        detalleCIA: formatearDesgloseCIA(obtenerDesgloseCIAActual()),
        firmaNombre: correo.firmaNombre,
        firmaCargo: correo.firmaCargo,
        firmaCorreo: correo.firmaCorreo,
        cierre: bloqueante ? 'Confirma para finalizar la partida.' : 'Correo de cierre disponible en historial.',
        colorEtiqueta: resultadoNormalizado === 'ganada' ? '#10b981' : '#ef4444',
        ocultarFooter: true,
        mostrarCerrar: !bloqueante
    });

    if (bloqueante) {
        correoEl.innerHTML +=
            '<div class="correo-actions">' +
                '<button type="button" onclick="aceptarFinalizacionPartida()">Aceptar y finalizar partida</button>' +
            '</div>';
    }

    window.estadoPartida.escenario_abierto_accionable = false;
    const botonResponder = document.getElementById('btn-responder-escenario');
    const respuestaPanel = document.getElementById('respuesta-panel');
    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
    if (botonResponder) botonResponder.style.display = 'none';
    if (respuestaPanel) respuestaPanel.style.display = 'none';
    if (opcionesContainer) opcionesContainer.style.display = 'none';
    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'none';
        sinOpcionesMsg.innerHTML = '';
    }

    alternarVistaCentroSinCorreo(false);
    establecerVistaCorreoActiva('efecto');
    activarModoVisualCorreo(true);
}

function iniciarFlujoEntreEscenarios(respuesta, contexto) {
    limpiarTemporizadoresEspera();

    const presupuestoParaEspera = (contexto && typeof contexto.presupuestoParaEspera !== 'undefined')
        ? Number(contexto.presupuestoParaEspera)
        : Number(window.estadoPartida.presupuesto);
    const esperaSegundos = calcularIntervaloEsperaSegundos(presupuestoParaEspera);
    const esperaMs = Math.round(esperaSegundos * 1000);
    const mitadMs = Math.round(esperaMs / 2);

    ocultarCorreoRepercusion();
    establecerVistaCorreoActiva('escenario');
    alternarVistaCentroSinCorreo(true);
    window.estadoPartida.escenario_abierto_accionable = false;
    actualizarEstadoEspera('Espera a siguiente escenario... ' + Math.ceil(esperaSegundos) + 's');

    const opcionesContainer = document.getElementById('opciones-container');
    if (opcionesContainer) {
        opcionesContainer.style.display = 'none';
    }

    window.estadoPartida.inter_evento_total_ms = esperaMs;
    window.estadoPartida.inter_evento_transcurrido_ms = 0;
    window.estadoPartida.inter_evento_pausado = false;
    window.estadoPartida.inter_evento_efecto_emitido = false;
    window.estadoPartida.inter_evento_contexto = {
        respuesta: respuesta,
        contexto: contexto || {}
    };

    const suppressEffectMail = !!(contexto && contexto.suppressEffectMail);

    const tickMs = 250;
    window.estadoPartida.ticker_inter_eventos = setInterval(function() {
        if (window.estadoPartida.inter_evento_pausado) {
            return;
        }

        window.estadoPartida.inter_evento_transcurrido_ms += tickMs;

        if (!window.estadoPartida.inter_evento_efecto_emitido && window.estadoPartida.inter_evento_transcurrido_ms >= mitadMs) {
            window.estadoPartida.inter_evento_efecto_emitido = true;
            if (!suppressEffectMail) {
                aplicarEstadoDesdeRespuesta(window.estadoPartida.inter_evento_contexto.respuesta);
                agregarCorreoBandeja('effects', {
                    subject: 'Repercusion - ' + (window.estadoPartida.asunto_actual || 'Escenario'),
                    payload: {
                        respuesta: window.estadoPartida.inter_evento_contexto.respuesta,
                        contexto: window.estadoPartida.inter_evento_contexto.contexto
                    }
                });

                const ajusteTrimestral = window.estadoPartida.inter_evento_contexto.respuesta.ajuste_trimestral;
                if (ajusteTrimestral && ajusteTrimestral.emitir_correo) {
                    agregarCorreoBandeja('effects', {
                        subject: 'Movimiento trimestral de presupuesto',
                        payload: {
                            tipo: 'ajuste_trimestral',
                            ajuste: ajusteTrimestral
                        }
                    });
                }
            }
        }

        const restanteMs = Math.max(0, window.estadoPartida.inter_evento_total_ms - window.estadoPartida.inter_evento_transcurrido_ms);
        const restanteSeg = Math.ceil(restanteMs / 1000);
        if (restanteSeg > 0) {
            actualizarEstadoEspera('Espera a siguiente escenario... ' + restanteSeg + 's');
        }

        if (window.estadoPartida.inter_evento_transcurrido_ms >= window.estadoPartida.inter_evento_total_ms) {
            const respuestaFinal = window.estadoPartida.inter_evento_contexto
                ? window.estadoPartida.inter_evento_contexto.respuesta
                : null;

            limpiarTemporizadoresEspera();
            actualizarEstadoEspera('');

            if (respuestaFinal && respuestaFinal.partida_finalizada) {
                mostrarCorreoFinalizacion(
                    respuestaFinal.mensaje || 'Partida finalizada',
                    respuestaFinal.resultado || 'perdida'
                );
                return;
            }

            cargarSiguienteEscenario();
        }
    }, tickMs);
}

function actualizarContadores() {
    document.getElementById('stat-cia').textContent = String(Math.round(window.estadoPartida.cia));
    document.getElementById('stat-presupuesto').textContent = String(Math.round(window.estadoPartida.presupuesto));
    document.getElementById('stat-despido').textContent = String(Math.round(window.estadoPartida.despido));

    const statCiaCentro = document.getElementById('stat-cia-centro');
    const statPresupuestoCentro = document.getElementById('stat-presupuesto-centro');
    const statDespidoCentro = document.getElementById('stat-despido-centro');
    if (statCiaCentro) statCiaCentro.textContent = String(Math.round(window.estadoPartida.cia));
    if (statPresupuestoCentro) statPresupuestoCentro.textContent = String(Math.round(window.estadoPartida.presupuesto));
    if (statDespidoCentro) statDespidoCentro.textContent = String(Math.round(window.estadoPartida.despido));

    const maxRondasEl = document.getElementById('stat-max-rondas');
    if (maxRondasEl) {
        maxRondasEl.textContent = String(Math.round(window.estadoPartida.maxRondas));
    }
    actualizarPerfilOperativo();
    actualizarPanelDesgloseCIA();
}

function aplicarEstadoDesdeRespuesta(respuesta) {
    if (!respuesta || !respuesta.nuevo_estado) return;

    window.estadoPartida.cia = Number(respuesta.nuevo_estado.cia);
    if (respuesta.nuevo_estado.confidencialidad != null && respuesta.nuevo_estado.integridad != null && respuesta.nuevo_estado.accesibilidad != null) {
        window.estadoPartida.cia_desglose = {
            confidencialidad: respuesta.nuevo_estado.confidencialidad,
            integridad: respuesta.nuevo_estado.integridad,
            accesibilidad: respuesta.nuevo_estado.accesibilidad,
            cia: respuesta.nuevo_estado.cia
        };
    }
    window.estadoPartida.presupuesto = Number(respuesta.nuevo_estado.presupuesto);
    window.estadoPartida.despido = Number(respuesta.nuevo_estado.despido);
    actualizarContadores();
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
            aplicarTimeoutAutomatico('tiempo');
        }
    }, 1000);
}

function detenerCronometro() {
    if (window.estadoPartida.intervalo_cronometro) {
        clearInterval(window.estadoPartida.intervalo_cronometro);
        window.estadoPartida.intervalo_cronometro = null;
    }
}

function aplicarTimeoutAutomatico(motivo) {
    if (window.estadoPartida.partida_finalizada) {
        return;
    }

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

            resolverEscenarioActivo('timeout', obtenerTextoOpcionElegida('timeout'));

            cerrarCorreoEscenarioVista();
            iniciarFlujoEntreEscenarios(respuesta, {
                fueTimeout: true,
                motivo: motivo || 'tiempo',
                codigoOpcion: 'timeout',
                presupuestoParaEspera: respuesta.nuevo_estado ? respuesta.nuevo_estado.presupuesto : window.estadoPartida.presupuesto
            });
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

function obtenerDeltaPresupuestoBase(opcion) {
    const delta = Number(opcion && opcion.delta_presupuesto_base);
    return Number.isFinite(delta) ? delta : 0;
}

function esEscenarioPhishingActual() {
    return String(window.estadoPartida.tipo_actual || '').toLowerCase() === 'phishing';
}

function puedeCostearOpcion(opcion, presupuestoActual) {
    if (esEscenarioPhishingActual()) {
        return true;
    }

    const deltaPresupuesto = obtenerDeltaPresupuestoBase(opcion);
    if (deltaPresupuesto >= 0) {
        return true;
    }
    return (Number(presupuestoActual) + deltaPresupuesto) >= 0;
}

function mostrarFinPartida(mensaje, resultado) {
    const resultadoFinal = resultado || 'finalizada';
    const esVictoria = resultadoFinal === 'ganada';
    const esDerrota = resultadoFinal === 'perdida';
    const esNeutral = !esVictoria && !esDerrota;
    const titulo = esVictoria ? 'Victoria' : (esDerrota ? 'Derrota' : 'Cierre de partida');
    window.estadoPartida.partida_finalizada = true;
    activarBloqueoFinalizacion(false);
    detenerCronometro();
    limpiarTemporizadoresEspera();
    limpiarTimerEscenarioPendiente();
    actualizarEstadoEspera('');
    ocultarCorreoRepercusion();
    establecerVistaCorreoActiva('escenario');
    deshabilitarOpciones();

    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');

    if (opcionesContainer) {
        opcionesContainer.style.display = 'none';
    }

    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'block';
        const colorFondo = esVictoria ? '#ecfdf5' : (esDerrota ? '#fef2f2' : '#effafe');
        const colorBorde = esVictoria ? '#10b981' : (esDerrota ? '#ef4444' : '#0ea5e9');
        const colorTexto = esVictoria ? '#065f46' : (esDerrota ? '#991b1b' : '#0c4a6e');
        sinOpcionesMsg.innerHTML =
            '<div style="max-width: 720px; margin: 0 auto; padding: 18px 20px; border: 2px solid ' + colorBorde + '; border-radius: 14px; background: ' + colorFondo + '; color: ' + colorTexto + '; box-shadow: 0 8px 24px rgba(0,0,0,0.08);">' +
                '<div style="font-size: 1.15rem; font-weight: 800; margin-bottom: 8px;">' + titulo + '</div>' +
                '<div style="margin-bottom: 14px; line-height: 1.45;">' + escaparHtml(mensaje || 'Partida finalizada') + '</div>' +
                '<button type="button" onclick="window.location.href=\'menu.php\'">Volver al Menú</button>' +
            '</div>';

    }

    mostrarModalFinPartida(
        esVictoria ? 'Ganaste la partida' : (esDerrota ? 'Perdiste la partida' : 'Cierre de partida'),
        mensaje || 'Partida finalizada',
        esNeutral ? null : esVictoria
    );
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
    actualizarMedidorConfiguracion(id);
}

function procesarOpcion(idOpcion, codigoOpcion, deltaPresupuestoBase) {
    if (window.estadoPartida.partida_finalizada) {
        return;
    }

    const deltaPresupuesto = Number(deltaPresupuestoBase || 0);
    if (!esEscenarioPhishingActual() && deltaPresupuesto < 0 && (window.estadoPartida.presupuesto + deltaPresupuesto) < 0) {
        alert('No te alcanza para tomar esta desicion, elige otra');

        const hayOpcionCosteable = (window.estadoPartida.opciones_actuales || []).some(function(opcion) {
            return puedeCostearOpcion(opcion, window.estadoPartida.presupuesto);
        });

        if (!hayOpcionCosteable) {
            setTimeout(function() {
                aplicarTimeoutAutomatico('presupuesto');
            }, 250);
        }
        return;
    }

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
                const mensajeError = respuesta.mensaje || respuesta.error || 'ERROR';
                alert('Error procesando opcion: ' + mensajeError);
                return;
            }

            resolverEscenarioActivo(codigoOpcion, obtenerTextoOpcionElegida(codigoOpcion));

            cerrarCorreoEscenarioVista();
            iniciarFlujoEntreEscenarios(respuesta, {
                fueTimeout: false,
                motivo: 'decision',
                codigoOpcion: codigoOpcion,
                presupuestoParaEspera: respuesta.nuevo_estado ? respuesta.nuevo_estado.presupuesto : window.estadoPartida.presupuesto
            });
        })
        .catch(function(err) {
            console.error(err);
            alert('Error de red al procesar opcion');
        });
}

function mostrarEscenarioEnVista(data, accionable) {
    if (!data || !data.turno || !data.turno.escenario) {
        return;
    }

    const turno = data.turno;
    const escenario = turno.escenario;
    window.estadoPartida.ronda_actual = Number(turno.orden_en_partida) || window.estadoPartida.ronda_actual;
    actualizarPerfilOperativo();
    const remitenteNombre = escenario.remitente_nombre || 'Sin nombre';
    const remitenteCorreo = escenario.remitente_correo || 'sin-correo';
    const respuestaPanelDestinatario = document.getElementById('respuesta-panel-destinatario');
    const respuestaPanelAvatar = document.getElementById('respuesta-panel-avatar');

    window.estadoPartida.id_partida = data.id_partida;
    window.estadoPartida.id_partida_escenario = turno.id_partida_escenario;
    window.estadoPartida.asunto_actual = escenario.titulo_correo || '';
    window.estadoPartida.tipo_actual = escenario.tipo_escenario || '';
    window.estadoPartida.remitente_nombre_actual = remitenteNombre;
    window.estadoPartida.remitente_correo_actual = remitenteCorreo;
    window.estadoPartida.feedback_general_actual = escenario.feedback_general || '';
    window.estadoPartida.escenario_abierto_accionable = !!accionable;
    alternarVistaCentroSinCorreo(false);
    activarModoVisualCorreo(true);
    establecerVistaCorreoActiva('escenario');

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
    if (respuestaPanelDestinatario) {
        respuestaPanelDestinatario.textContent = remitenteNombre + ' (' + remitenteCorreo + ')';
    }
    if (respuestaPanelAvatar) {
        respuestaPanelAvatar.textContent = (remitenteNombre || 'R').trim().charAt(0).toUpperCase() || 'R';
    }
    const escenarioTextoEl = document.getElementById('esc-texto');
    if (escenarioTextoEl) {
        escenarioTextoEl.innerHTML = renderCorreoGmail({
            asunto: escenario.titulo_correo || 'Sin asunto',
            deNombre: remitenteNombre,
            deCorreo: remitenteCorreo,
            paraNombre: 'Jefe de Seguridad',
            paraCorreo: 'jefe.seguridad@empresa.local',
            cuerpo: escenario.texto_correo || '-',
            firmaNombre: remitenteNombre,
            firmaCargo: 'Equipo interno',
            firmaCorreo: remitenteCorreo,
            colorEtiqueta: '#00d4ff',
            compacto: true
        });
    }

    const correoRepercusionEl = document.getElementById('correo-repercusion');
    if (correoRepercusionEl) {
        correoRepercusionEl.style.display = 'none';
        correoRepercusionEl.innerHTML = '';
    }

    const opcionesContainer = document.getElementById('opciones-container');
    const opcionesList = document.getElementById('opciones-lista');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
    const botonResponder = document.getElementById('btn-responder-escenario');
    const respuestaPanel = document.getElementById('respuesta-panel');

    let opcionesValidas = (escenario.opciones || []).filter(function(op) {
        return op.codigo_opcion !== 'timeout';
    });

    if (escenario.tipo_escenario === 'phishing' && opcionesValidas.length === 0) {
        opcionesValidas = [
            { id_opcion: 0, codigo_opcion: 'legitimo', texto_opcion: 'Marcar como correo legitimo' },
            { id_opcion: 0, codigo_opcion: 'falso', texto_opcion: 'Reportar como phishing' }
        ];
    }

    window.estadoPartida.opciones_actuales = opcionesValidas;

    if (!accionable) {
        if (opcionesContainer) opcionesContainer.style.display = 'none';
        if (botonResponder) botonResponder.style.display = 'none';
        if (respuestaPanel) respuestaPanel.style.display = 'block';
        if (sinOpcionesMsg) {
            sinOpcionesMsg.style.display = 'block';
            const respuestaHist = (data && data.turno && data.turno.escenario && data.turno.escenario.respuesta_historial) || null;
            const textoResp = respuestaHist && respuestaHist.texto ? respuestaHist.texto : 'Sin respuesta registrada.';
            const correoRespuestaHistorial = renderCorreoGmail({
                asunto: 'Respuesta seleccionada',
                deNombre: 'Sistema de historial',
                deCorreo: 'historial@cybergame.local',
                paraNombre: 'Jefe de Seguridad',
                paraCorreo: 'jefe.seguridad@empresa.local',
                cuerpo: textoResp,
                firmaNombre: 'Historial de partida',
                firmaCargo: 'Registro',
                firmaCorreo: 'historial@cybergame.local',
                colorEtiqueta: '#44d18f',
                compacto: true
            });
            sinOpcionesMsg.innerHTML =
                '<p style="margin: 0 0 8px 0; color: #4b5563;"><strong>Correo de historial</strong></p>' +
                '<div style="margin: 0 0 10px 0;">' + correoRespuestaHistorial + '</div>' +
                '<button type="button" onclick="cerrarCorreoEscenarioVista()">Cerrar correo</button>';
        }
        return;
    }

    if (!opcionesValidas || opcionesValidas.length === 0) {
        if (opcionesContainer) opcionesContainer.style.display = 'none';
        if (botonResponder) botonResponder.style.display = 'none';
        if (respuestaPanel) respuestaPanel.style.display = 'block';
        if (sinOpcionesMsg) {
            sinOpcionesMsg.style.display = 'block';
            sinOpcionesMsg.innerHTML =
                '<p style="margin: 0 0 8px 0; color: #ffffff;">Este es un correo informativo. No requiere decision.</p>' +
                '<button type="button" onclick="avanzarDesdeCorreoInformativo()">Cerrar correo y continuar</button>';
        }
        detenerCronometro();
        return;
    }

    const presupuestoActual = Number(window.estadoPartida.presupuesto);
    if (botonResponder) botonResponder.style.display = 'inline-flex';
    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'none';
        sinOpcionesMsg.innerHTML = '';
    }
    if (opcionesContainer) opcionesContainer.style.display = 'none';
    if (respuestaPanel) respuestaPanel.style.display = 'none';

    if (opcionesList) {
        opcionesList.innerHTML = '';
        opcionesValidas.forEach(function(opcion) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'decision-option-btn';
            btn.textContent = opcion.codigo_opcion + ': ' + opcion.texto_opcion;
            if (!puedeCostearOpcion(opcion, presupuestoActual)) {
                btn.classList.add('is-disabled');
                btn.title = 'No alcanza el presupuesto para esta decision';
            }
            btn.onclick = function() {
                procesarOpcion(opcion.id_opcion, opcion.codigo_opcion, opcion.delta_presupuesto_base);
            };
            opcionesList.appendChild(btn);
        });
    }

    if (!window.estadoPartida.intervalo_cronometro) {
        iniciarCronometro();
    } else {
        const cronometroEl = document.getElementById('cronometro');
        if (cronometroEl) {
            cronometroEl.textContent = String(window.estadoPartida.tiempo_restante);
        }
    }

    alternarOpcionesEscenario(false);
}

function renderTurno(data) {
    if (window.estadoPartida.partida_finalizada) {
        return;
    }

    if (data && data.partida_finalizada) {
        const resultado = data.resultado || ((data.mensaje || '').toLowerCase().includes('felic') ? 'ganada' : 'perdida');
        mostrarCorreoFinalizacion(data.mensaje || 'Partida finalizada', resultado);
        return;
    }

    if (data && data.sin_escenarios) {
        mostrarFinPartida('No hay más escenarios disponibles para esta partida.', 'finalizada');
        return;
    }

    if (!data || !data.turno || !data.turno.escenario) {
        alert('No hay escenario disponible.');
        return;
    }

    window.estadoPartida.ronda_actual = Number(data.turno.orden_en_partida) || window.estadoPartida.ronda_actual;
    actualizarPerfilOperativo();

    const subject = 'Escenario - ' + (data.turno.escenario.titulo_correo || 'Sin asunto');
    agregarCorreoBandeja('scenarios', {
        subject: subject,
        payload: {
            data: data
        }
    });

    const botonResponder = document.getElementById('btn-responder-escenario');
    if (botonResponder) {
        botonResponder.style.display = 'none';
        botonResponder.textContent = 'Responder';
    }
    const respuestaPanel = document.getElementById('respuesta-panel');
    if (respuestaPanel) {
        respuestaPanel.style.display = 'none';
    }

    const opcionesContainer = document.getElementById('opciones-container');
    const sinOpcionesMsg = document.getElementById('sin-opciones-msg');
    if (opcionesContainer) opcionesContainer.style.display = 'none';
    if (sinOpcionesMsg) {
        sinOpcionesMsg.style.display = 'none';
        sinOpcionesMsg.innerHTML = '';
    }

    limpiarVistaCorreoEscenario();
    ocultarCorreoRepercusion();
    alternarVistaCentroSinCorreo(true);
    activarModoVisualCorreo(false);
    window.estadoPartida.escenario_abierto_accionable = false;

    iniciarTimerEscenarioPendiente();

    actualizarEstadoEspera('');
    mostrarPartida();

    // El cronometro solo debe iniciar cuando el usuario abra un correo accionable.
    detenerCronometro();
    window.estadoPartida.tiempo_restante = 60;
    const cronometroEl = document.getElementById('cronometro');
    if (cronometroEl) {
        cronometroEl.textContent = '60';
        cronometroEl.style.color = '';
    }
}

// Evalúa si las condiciones iniciales generan victoria o derrota automática
function evaluarCondicionesIniciales(cia, presupuesto, despido) {
    if (cia <= 0) {
        return { resultado: 'perdida', motivo: 'cia_cero' };
    }

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

    if (cia < 30 || cia > 80 || despido < 5 || despido > 80 || presupuesto < 10 || presupuesto > 80 || maxRondas < 7 || maxRondas > 25) {
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
    window.estadoPartida.mailbox = {
        scenarios: { pending: [], history: [] },
        effects: { pending: [], history: [] }
    };
    window.estadoPartida.mailbox_seq = 1;
    window.estadoPartida.escenario_mail_activo_id = null;
    window.estadoPartida.escenario_abierto_accionable = false;
    window.estadoPartida.finalizacionPendiente = null;
    window.estadoPartida.bloqueo_final_activo = false;
    window.estadoPartida.partida_finalizada = false;
    window.estadoPartida.ronda_actual = 0;
    const modalFin = document.getElementById('modal-fin-partida');
    if (modalFin) {
        modalFin.style.display = 'none';
        modalFin.setAttribute('aria-hidden', 'true');
    }
    limpiarTemporizadoresEspera();
    limpiarTimerEscenarioPendiente();
    cerrarPanelBandeja();
    activarBloqueoFinalizacion(false);
    ocultarCorreoRepercusion();
    cerrarCorreoEscenarioVista();
    actualizarEstadoEspera('');
    actualizarBandejasUI();
    actualizarContadores();
    alternarVistaCentroSinCorreo(true);
    solicitarRankGlobalUsuario();

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

            // Mostrar la vista de partida apenas el backend confirma inicio.
            mostrarPartida();

            if (data.estado) {
                window.estadoPartida.cia = Number(data.estado.cia);
                window.estadoPartida.cia_desglose = {
                    confidencialidad: data.estado.confidencialidad,
                    integridad: data.estado.integridad,
                    accesibilidad: data.estado.accesibilidad,
                    cia: data.estado.cia
                };
                window.estadoPartida.presupuesto = Number(data.estado.presupuesto);
                window.estadoPartida.despido = Number(data.estado.despido);
                actualizarContadores();
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
    if (window.estadoPartida.partida_finalizada) {
        return;
    }

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
                const resultado = data.resultado || ((data.mensaje || '').toLowerCase().includes('felic') ? 'ganada' : 'perdida');
                mostrarCorreoFinalizacion(data.mensaje || 'Partida finalizada', resultado);
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
    actualizarBandejasUI();
    actualizarNombreUsuarioPerfil();
    actualizarPerfilOperativo();
    solicitarRankGlobalUsuario();

    ['cia', 'presupuesto', 'despido', 'maxRondas'].forEach(function(id) {
        const input = document.getElementById(id);
        if (!input) return;

        input.addEventListener('input', function() {
            actualizarMedidorConfiguracion(id);
        });

        input.addEventListener('change', function() {
            actualizarMedidorConfiguracion(id);
        });
    });

    actualizarMedidoresConfiguracion();

    const params = new URLSearchParams(window.location.search);
    if (params.get('view') === 'config') {
        mostrarConfig();
    } else {
        aplicarZoomConfiguracion(false);
    }
});

