-- =========================================
-- CYBERGAME - Base de datos (version simple)
-- =========================================

CREATE DATABASE IF NOT EXISTS CYBERGAME
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE CYBERGAME;

-- 1) USUARIOS
CREATE TABLE usuarios (
  id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2) TOKENS RECUPERACION
CREATE TABLE tokens_recuperacion (
  id_token BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expira_en DATETIME NOT NULL,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  usado_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tokens_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3) ESCENARIOS
CREATE TABLE escenarios (
  id_escenario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_escenario ENUM('informativo', 'decision', 'phishing') NOT NULL,
  titulo_correo VARCHAR(180) NOT NULL,
  texto_correo TEXT NOT NULL,
  feedback_general TEXT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  dificultad_base TINYINT UNSIGNED NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4) OPCIONES ESCENARIO
CREATE TABLE opciones_escenario (
  id_opcion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_escenario INT UNSIGNED NOT NULL,
  codigo_opcion VARCHAR(20) NOT NULL, -- A, B, C, D, legitimo, falso, timeout
  texto_opcion VARCHAR(255) NOT NULL,
  es_correcta TINYINT(1) NULL,        -- util en phishing
  feedback_opcion TEXT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_opciones_escenario
    FOREIGN KEY (id_escenario) REFERENCES escenarios(id_escenario)
    ON DELETE CASCADE,
  CONSTRAINT uq_opcion_por_escenario
    UNIQUE (id_escenario, codigo_opcion)
) ENGINE=InnoDB;

-- 5) IMPACTOS OPCION (puntaje base estandar)
CREATE TABLE impactos_opcion (
  id_impacto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_opcion INT UNSIGNED NOT NULL UNIQUE,
  delta_cia_base SMALLINT NOT NULL DEFAULT 0,
  delta_presupuesto_base INT NOT NULL DEFAULT 0,
  delta_despido_base DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  version_balance SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_impactos_opcion
    FOREIGN KEY (id_opcion) REFERENCES opciones_escenario(id_opcion)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6) PARTIDAS (resumen)
CREATE TABLE partidas (
  id_partida BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT UNSIGNED NOT NULL,
  estado_partida ENUM('en_curso', 'ganada', 'perdida') NOT NULL DEFAULT 'en_curso',
  cia_inicial TINYINT UNSIGNED NOT NULL,
  presupuesto_inicial INT UNSIGNED NOT NULL,
  despido_inicial DECIMAL(5,2) NOT NULL,
  cia_final TINYINT UNSIGNED NULL,
  presupuesto_final INT UNSIGNED NULL,
  despido_final DECIMAL(5,2) NULL,
  tiempo_inicial DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tiempo_final DATETIME NULL,
  duracion_segundos INT UNSIGNED NULL,
  CONSTRAINT fk_partidas_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
    ON DELETE CASCADE,
  CONSTRAINT chk_partidas_cia_inicial CHECK (cia_inicial BETWEEN 0 AND 100),
  CONSTRAINT chk_partidas_despido_inicial CHECK (despido_inicial BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- 7) PARTIDA_ESCENARIOS (orden de escenarios por partida)
CREATE TABLE partida_escenarios (
  id_partida_escenario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_partida BIGINT UNSIGNED NOT NULL,
  id_escenario INT UNSIGNED NOT NULL,
  orden_en_partida SMALLINT UNSIGNED NOT NULL,
  notificado_en DATETIME NULL,
  abierto_en DATETIME NULL,
  cerrado_en DATETIME NULL,
  CONSTRAINT fk_pe_partida
    FOREIGN KEY (id_partida) REFERENCES partidas(id_partida)
    ON DELETE CASCADE,
  CONSTRAINT fk_pe_escenario
    FOREIGN KEY (id_escenario) REFERENCES escenarios(id_escenario)
    ON DELETE RESTRICT,
  CONSTRAINT uq_pe_orden UNIQUE (id_partida, orden_en_partida)
) ENGINE=InnoDB;

-- 8) EVENTOS_PARTIDA (detalle/historial)
CREATE TABLE eventos_partida (
  id_evento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_partida_escenario BIGINT UNSIGNED NOT NULL,
  id_opcion_elegida INT UNSIGNED NULL, -- NULL si fue informativo
  tiempo_respuesta_segundos SMALLINT UNSIGNED NULL,
  fue_timeout TINYINT(1) NOT NULL DEFAULT 0, -- 1 si paso de 60s
  cia_antes TINYINT UNSIGNED NOT NULL,
  presupuesto_antes INT UNSIGNED NOT NULL,
  despido_antes DECIMAL(5,2) NOT NULL,
  cia_despues TINYINT UNSIGNED NOT NULL,
  presupuesto_despues INT UNSIGNED NOT NULL,
  despido_despues DECIMAL(5,2) NOT NULL,
  feedback_mostrado TEXT NOT NULL,
  fecha_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evento_partida_escenario
    FOREIGN KEY (id_partida_escenario) REFERENCES partida_escenarios(id_partida_escenario)
    ON DELETE CASCADE,
  CONSTRAINT fk_evento_opcion
    FOREIGN KEY (id_opcion_elegida) REFERENCES opciones_escenario(id_opcion)
    ON DELETE SET NULL,
  CONSTRAINT chk_eventos_cia_antes CHECK (cia_antes BETWEEN 0 AND 100),
  CONSTRAINT chk_eventos_cia_despues CHECK (cia_despues BETWEEN 0 AND 100),
  CONSTRAINT chk_eventos_despido_antes CHECK (despido_antes BETWEEN 0 AND 100),
  CONSTRAINT chk_eventos_despido_despues CHECK (despido_despues BETWEEN 0 AND 100)
) ENGINE=InnoDB;



CREATE TABLE remitentes_email (
  id_remitente INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  correo VARCHAR(150) NOT NULL UNIQUE,
  nombre_mostrado VARCHAR(120) NOT NULL,
  tipo_remitente ENUM('legitimo','phishing') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 1) Agregar campo remitente en escenarios
ALTER TABLE escenarios
ADD COLUMN id_remitente INT UNSIGNED NULL AFTER tipo_escenario;

-- 2) Crear la relacion (FK) hacia remitentes_email
ALTER TABLE escenarios
ADD CONSTRAINT fk_escenarios_remitente
FOREIGN KEY (id_remitente) REFERENCES remitentes_email(id_remitente)
ON DELETE SET NULL
ON UPDATE CASCADE;

ALTER TABLE escenarios
DROP COLUMN dificultad_base;


USE CYBERGAME;

-- 2) Ya no usamos versionado de balance en BD
ALTER TABLE impactos_opcion
DROP COLUMN version_balance;

USE CYBERGAME;

ALTER TABLE opciones_escenario
DROP COLUMN es_correcta;


//////////////****//////////////
USE CYBERGAME;

-- =========================================
-- 1) ESCENARIO INFORMATIVO
-- =========================================
INSERT INTO escenarios (
  tipo_escenario, titulo_correo, texto_correo, feedback_general, activo, creado_en
) VALUES (
  'informativo',
  'Boletín de seguridad: Contraseñas seguras',
  'Recuerda aplicar contraseñas largas, únicas y con doble factor de autenticación.',
  'Buen recordatorio. La prevención reduce riesgos futuros.',
  1,
  NOW()
);

-- =========================================
-- 2) ESCENARIO DE DECISION
-- =========================================
INSERT INTO escenarios (
  tipo_escenario, titulo_correo, texto_correo, feedback_general, activo, creado_en
) VALUES (
  'decision',
  'Servidor desactualizado detectado',
  'El equipo de TI reporta un servidor sin parches críticos. ¿Qué decides?',
  'Tu respuesta impacta CIA, presupuesto y riesgo de despido.',
  1,
  NOW()
);

-- Guardamos el id del escenario de decision recién creado
SET @id_decision = LAST_INSERT_ID();

-- Opciones del escenario de decision
INSERT INTO opciones_escenario (
  id_escenario, codigo_opcion, texto_opcion, es_correcta, feedback_opcion, activa
) VALUES
(@id_decision, 'A', 'Aplicar parches hoy en ventana de mantenimiento', NULL, 'Buena práctica: reduces exposición rápidamente.', 1),
(@id_decision, 'B', 'Posponer 2 semanas para evitar interrupciones', NULL, 'Riesgoso: aumentas la ventana de ataque.', 1),
(@id_decision, 'timeout', 'No responder en el tiempo límite', NULL, 'Penalización por demora en la decisión.', 1);
-- Impactos base por opción (estándar) para el escenario de decisión
-- Requiere que ya exista: SET @id_decision = ...;

INSERT INTO impactos_opcion (
  id_opcion,
  delta_cia_base,
  delta_presupuesto_base,
  delta_despido_base,
  activo
)
SELECT
  id_opcion,
  CASE codigo_opcion
    WHEN 'A' THEN 8
    WHEN 'B' THEN -6
    WHEN 'C' THEN 3
    WHEN 'D' THEN -9
    WHEN 'timeout' THEN -4
    ELSE 0
  END AS delta_cia_base,
  CASE codigo_opcion
    WHEN 'A' THEN -120000
    WHEN 'B' THEN 0
    WHEN 'C' THEN -60000
    WHEN 'D' THEN -20000
    WHEN 'timeout' THEN -30000
    ELSE 0
  END AS delta_presupuesto_base,
  CASE codigo_opcion
    WHEN 'A' THEN -2.00
    WHEN 'B' THEN 4.00
    WHEN 'C' THEN -0.50
    WHEN 'D' THEN 6.00
    WHEN 'timeout' THEN 3.00
    ELSE 0.00
  END AS delta_despido_base,
  1 AS activo
FROM opciones_escenario
WHERE id_escenario = @id_decision;


-- =========================================
-- 3) ESCENARIO PHISHING
-- =========================================
INSERT INTO escenarios (
  tipo_escenario, titulo_correo, texto_correo, feedback_general, activo, creado_en
) VALUES (
  'phishing',
  'URGENTE: Verifica tu nómina ahora',
  'Has recibido un correo con enlace externo y tono de urgencia para “validar nómina”.',
  'Debes identificar si es legítimo o phishing.',
  1,
  NOW()
);

-- Guardamos el id del escenario phishing recién creado
SET @id_phishing = LAST_INSERT_ID();

-- Opciones del escenario phishing
INSERT INTO opciones_escenario (
  id_escenario, codigo_opcion, texto_opcion, es_correcta, feedback_opcion, activa
) VALUES
(@id_phishing, 'legitimo', 'Marcar como legítimo', 0, 'Incorrecto: el correo tenía señales claras de phishing.', 1),
(@id_phishing, 'falso', 'Reportar como phishing', 1, 'Correcto: detectaste indicadores de fraude.', 1),
(@id_phishing, 'timeout', 'No responder en el tiempo límite', 0, 'Penalización por no actuar a tiempo.', 1);

-- Impactos base phishing
INSERT INTO impactos_opcion (
  id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, version_balance, activo
)
SELECT id_opcion,
       CASE codigo_opcion
         WHEN 'falso' THEN 6
         WHEN 'legitimo' THEN -8
         WHEN 'timeout' THEN -5
       END,
       CASE codigo_opcion
         WHEN 'falso' THEN 0
         WHEN 'legitimo' THEN -90000
         WHEN 'timeout' THEN -40000
       END,
       CASE codigo_opcion
         WHEN 'falso' THEN -1.50
         WHEN 'legitimo' THEN 5.00
         WHEN 'timeout' THEN 3.50
       END,
       1,
       1
FROM opciones_escenario
WHERE id_escenario = @id_phishing;


////*/////*///




///////*****//////


USE CYBERGAME;

START TRANSACTION;

-- =====================================================
-- REMITENTES (si existen, solo los actualiza)
-- =====================================================
INSERT INTO remitentes_email (correo, nombre_mostrado, tipo_remitente, activo) VALUES
('ti@empresa.local', 'Equipo TI', 'legitimo', 1),
('soc@empresa.local', 'SOC', 'legitimo', 1),
('rrhh@empresa.local', 'Recursos Humanos', 'legitimo', 1),
('finanzas@empresa.local', 'Finanzas', 'legitimo', 1),
('comunicaciones@empresa.local', 'Comunicaciones', 'legitimo', 1),
('nomina@urgente-pagos.co', 'Nómina Segura', 'phishing', 1),
('soporte@microsoft-alerta.co', 'Soporte Microsoft', 'phishing', 1),
('banco@verifica-cuenta.co', 'Banco Corporativo', 'phishing', 1),
('vpn@acceso-urgente.co', 'VPN Access', 'phishing', 1),
('compras@proveedor-prioridad.co', 'Proveedor Prioritario', 'phishing', 1)
ON DUPLICATE KEY UPDATE
nombre_mostrado = VALUES(nombre_mostrado),
tipo_remitente = VALUES(tipo_remitente),
activo = VALUES(activo);

-- =====================================================
-- 5 INFORMATIVOS (sin opciones)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en) VALUES
(
 'informativo',
 (SELECT id_remitente FROM remitentes_email WHERE correo='ti@empresa.local' LIMIT 1),
 'Boletín: Contraseñas seguras',
 'Recuerda usar contraseñas únicas, largas y con doble factor.',
 'Buenas prácticas reducen superficie de ataque.',
 1, NOW()
),
(
 'informativo',
 (SELECT id_remitente FROM remitentes_email WHERE correo='soc@empresa.local' LIMIT 1),
 'Resumen SOC semanal',
 'Se detectaron intentos de acceso no autorizado bloqueados exitosamente.',
 'Mantener monitoreo activo evita escalamiento.',
 1, NOW()
),
(
 'informativo',
 (SELECT id_remitente FROM remitentes_email WHERE correo='rrhh@empresa.local' LIMIT 1),
 'Capacitación obligatoria anti-phishing',
 'Todo el personal debe completar el módulo de detección de correos falsos.',
 'Capacitar usuarios reduce riesgo humano.',
 1, NOW()
),
(
 'informativo',
 (SELECT id_remitente FROM remitentes_email WHERE correo='finanzas@empresa.local' LIMIT 1),
 'Control de pagos de alto valor',
 'Pagos sensibles requieren doble validación por canal alterno.',
 'Control administrativo clave contra fraude.',
 1, NOW()
),
(
 'informativo',
 (SELECT id_remitente FROM remitentes_email WHERE correo='comunicaciones@empresa.local' LIMIT 1),
 'Uso seguro de USB',
 'Se restringe uso de dispositivos externos no autorizados.',
 'Previene infecciones por medios removibles.',
 1, NOW()
);

-- =====================================================
-- 5 DECISION (A,B,C,D,timeout)
-- =====================================================

-- DECISION 1
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='ti@empresa.local' LIMIT 1),
 'Servidor sin parches críticos',
 'TI reporta servidor con vulnerabilidades críticas. ¿Qué decides?',
 'Tu elección impacta CIA, presupuesto y despido.',
 1, NOW()
);
SET @dec1 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@dec1, 'A', 'Parchear hoy en ventana nocturna', 'Respuesta rápida y responsable.', 1),
(@dec1, 'B', 'Postergar dos semanas', 'Aumenta ventana de exposición.', 1),
(@dec1, 'C', 'Mitigación temporal con controles', 'Ayuda parcialmente, no reemplaza parche.', 1),
(@dec1, 'D', 'No actuar este ciclo', 'Riesgo operativo alto.', 1),
(@dec1, 'timeout', 'No responder en tiempo', 'Demora de decisión penalizada.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 10 WHEN 'B' THEN -7 WHEN 'C' THEN 4 WHEN 'D' THEN -11 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -8 WHEN 'B' THEN 0 WHEN 'C' THEN -4 WHEN 'D' THEN -2 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN -3.00 WHEN 'B' THEN 5.00 WHEN 'C' THEN -1.00 WHEN 'D' THEN 7.00 ELSE 4.00 END,
1
FROM opciones_escenario
WHERE id_escenario = @dec1;

-- DECISION 2
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='soc@empresa.local' LIMIT 1),
 'Alerta de ransomware',
 'SOC detecta actividad anómala en equipo de contabilidad.',
 'Tiempo de reacción define el impacto.',
 1, NOW()
);
SET @dec2 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@dec2, 'A', 'Aislar equipo y activar protocolo', 'Contención efectiva.', 1),
(@dec2, 'B', 'Esperar más evidencia', 'Puede escalar el incidente.', 1),
(@dec2, 'C', 'Reiniciar y continuar', 'Medida débil para este tipo de amenaza.', 1),
(@dec2, 'D', 'Ignorar alerta', 'Decisión de muy alto riesgo.', 1),
(@dec2, 'timeout', 'No responder en tiempo', 'Demora penalizada.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 9 WHEN 'B' THEN -6 WHEN 'C' THEN -4 WHEN 'D' THEN -10 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -6 WHEN 'B' THEN -2 WHEN 'C' THEN -1 WHEN 'D' THEN -4 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN -2.50 WHEN 'B' THEN 4.50 WHEN 'C' THEN 3.00 WHEN 'D' THEN 8.00 ELSE 4.50 END,
1
FROM opciones_escenario
WHERE id_escenario = @dec2;

-- DECISION 3
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='rrhh@empresa.local' LIMIT 1),
 'Solicitud de acceso masivo a datos',
 'RRHH solicita acceso ampliado para tercero temporal.',
 'Debes balancear operación y seguridad.',
 1, NOW()
);
SET @dec3 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@dec3, 'A', 'Permitir mínimo privilegio por tiempo limitado', 'Aplicación correcta de control de acceso.', 1),
(@dec3, 'B', 'Aprobar acceso total', 'Exceso de privilegios.', 1),
(@dec3, 'C', 'Negar totalmente', 'Protege, pero puede afectar operación.', 1),
(@dec3, 'D', 'Delegar sin revisión', 'Gobierno de acceso insuficiente.', 1),
(@dec3, 'timeout', 'No responder en tiempo', 'Se penaliza inacción.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 8 WHEN 'B' THEN -8 WHEN 'C' THEN 2 WHEN 'D' THEN -9 ELSE -4 END,
CASE codigo_opcion WHEN 'A' THEN -3 WHEN 'B' THEN 0 WHEN 'C' THEN -1 WHEN 'D' THEN -1 ELSE -2 END,
CASE codigo_opcion WHEN 'A' THEN -2.00 WHEN 'B' THEN 5.50 WHEN 'C' THEN 1.00 WHEN 'D' THEN 6.00 ELSE 3.50 END,
1
FROM opciones_escenario
WHERE id_escenario = @dec3;

-- DECISION 4
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='finanzas@empresa.local' LIMIT 1),
 'Renovación de herramientas de seguridad',
 'Vencen licencias de protección en 72 horas.',
 'Inversión y riesgo deben equilibrarse.',
 1, NOW()
);
SET @dec4 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@dec4, 'A', 'Renovar suite completa ahora', 'Control integral sostenido.', 1),
(@dec4, 'B', 'Renovar solo lo mínimo', 'Cobertura parcial con riesgo residual.', 1),
(@dec4, 'C', 'Negociar prórroga corta', 'Mitigación temporal aceptable.', 1),
(@dec4, 'D', 'No renovar', 'Riesgo crítico de exposición.', 1),
(@dec4, 'timeout', 'No responder en tiempo', 'Inacción penalizada.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 11 WHEN 'B' THEN -3 WHEN 'C' THEN 2 WHEN 'D' THEN -10 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -10 WHEN 'B' THEN -4 WHEN 'C' THEN -2 WHEN 'D' THEN 0 ELSE -2 END,
CASE codigo_opcion WHEN 'A' THEN -3.50 WHEN 'B' THEN 2.50 WHEN 'C' THEN 1.50 WHEN 'D' THEN 7.50 ELSE 4.00 END,
1
FROM opciones_escenario
WHERE id_escenario = @dec4;

-- DECISION 5
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='comunicaciones@empresa.local' LIMIT 1),
 'Rumor de filtración en redes',
 'Se difundió posible filtración de datos en redes sociales.',
 'Tu respuesta afecta confianza y control de crisis.',
 1, NOW()
);
SET @dec5 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@dec5, 'A', 'Comunicar estado preliminar y activar comité', 'Respuesta transparente y ordenada.', 1),
(@dec5, 'B', 'Negar sin investigación previa', 'Puede empeorar impacto reputacional.', 1),
(@dec5, 'C', 'Guardar silencio por 48h', 'Aumenta incertidumbre.', 1),
(@dec5, 'D', 'Publicar detalles sin filtro legal', 'Riesgo legal y operativo.', 1),
(@dec5, 'timeout', 'No responder en tiempo', 'Inacción agrava crisis.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 6 WHEN 'B' THEN -5 WHEN 'C' THEN -4 WHEN 'D' THEN -7 ELSE -4 END,
CASE codigo_opcion WHEN 'A' THEN -4 WHEN 'B' THEN -2 WHEN 'C' THEN -1 WHEN 'D' THEN -3 ELSE -2 END,
CASE codigo_opcion WHEN 'A' THEN -2.00 WHEN 'B' THEN 4.00 WHEN 'C' THEN 3.00 WHEN 'D' THEN 5.00 ELSE 3.50 END,
1
FROM opciones_escenario
WHERE id_escenario = @dec5;

-- =====================================================
-- 5 PHISHING (legitimo, falso, timeout)
-- =====================================================

-- PHISHING 1
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'phishing',
 (SELECT id_remitente FROM remitentes_email WHERE correo='nomina@urgente-pagos.co' LIMIT 1),
 'URGENTE: valida tu nómina',
 'Solicita validar nómina por enlace externo y tono de urgencia.',
 'Detecta señales de phishing antes de actuar.',
 1, NOW()
);
SET @ph1 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@ph1, 'legitimo', 'Marcar como legítimo', 'Incorrecto: dominio y urgencia sospechosos.', 1),
(@ph1, 'falso', 'Reportar phishing', 'Correcto: detección acertada.', 1),
(@ph1, 'timeout', 'No responder en tiempo', 'Penalización por no actuar.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'falso' THEN 7 WHEN 'legitimo' THEN -9 ELSE -5 END,
CASE codigo_opcion WHEN 'falso' THEN 0 WHEN 'legitimo' THEN -8 ELSE -3 END,
CASE codigo_opcion WHEN 'falso' THEN -2.00 WHEN 'legitimo' THEN 6.00 ELSE 3.50 END,
1
FROM opciones_escenario
WHERE id_escenario = @ph1;

-- PHISHING 2
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'phishing',
 (SELECT id_remitente FROM remitentes_email WHERE correo='soporte@microsoft-alerta.co' LIMIT 1),
 'Tu cuenta será suspendida en 2 horas',
 'Pide iniciar sesión en portal externo para evitar bloqueo.',
 'El miedo y urgencia son tácticas de ingeniería social.',
 1, NOW()
);
SET @ph2 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@ph2, 'legitimo', 'Confiar y seguir enlace', 'Incorrecto: señales claras de fraude.', 1),
(@ph2, 'falso', 'Reportar phishing', 'Correcto: protegiste credenciales.', 1),
(@ph2, 'timeout', 'No responder en tiempo', 'No actuar también cuesta.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'falso' THEN 6 WHEN 'legitimo' THEN -8 ELSE -4 END,
CASE codigo_opcion WHEN 'falso' THEN 0 WHEN 'legitimo' THEN -7 ELSE -2 END,
CASE codigo_opcion WHEN 'falso' THEN -1.80 WHEN 'legitimo' THEN 5.50 ELSE 3.00 END,
1
FROM opciones_escenario
WHERE id_escenario = @ph2;

-- PHISHING 3
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'phishing',
 (SELECT id_remitente FROM remitentes_email WHERE correo='banco@verifica-cuenta.co' LIMIT 1),
 'Alerta bancaria: movimiento no reconocido',
 'Solicita confirmar tarjeta y token en sitio no oficial.',
 'Nunca compartir credenciales por correo.',
 1, NOW()
);
SET @ph3 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@ph3, 'legitimo', 'Ingresar datos solicitados', 'Incorrecto: expone información crítica.', 1),
(@ph3, 'falso', 'Reportar phishing', 'Correcto: evitaste fraude.', 1),
(@ph3, 'timeout', 'No responder en tiempo', 'Demora sin reporte disminuye respuesta.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'falso' THEN 8 WHEN 'legitimo' THEN -10 ELSE -5 END,
CASE codigo_opcion WHEN 'falso' THEN 0 WHEN 'legitimo' THEN -10 ELSE -3 END,
CASE codigo_opcion WHEN 'falso' THEN -2.30 WHEN 'legitimo' THEN 6.80 ELSE 3.80 END,
1
FROM opciones_escenario
WHERE id_escenario = @ph3;

-- PHISHING 4
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'phishing',
 (SELECT id_remitente FROM remitentes_email WHERE correo='vpn@acceso-urgente.co' LIMIT 1),
 'Restablece tu VPN corporativa',
 'Incluye adjunto ejecutable y mensaje de urgencia.',
 'Adjuntos ejecutables en correo son de alto riesgo.',
 1, NOW()
);
SET @ph4 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@ph4, 'legitimo', 'Abrir adjunto y ejecutar', 'Incorrecto: posible malware.', 1),
(@ph4, 'falso', 'Reportar phishing', 'Correcto: evitaste infección.', 1),
(@ph4, 'timeout', 'No responder en tiempo', 'No reportar deja riesgo activo.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'falso' THEN 7 WHEN 'legitimo' THEN -11 ELSE -5 END,
CASE codigo_opcion WHEN 'falso' THEN 0 WHEN 'legitimo' THEN -9 ELSE -3 END,
CASE codigo_opcion WHEN 'falso' THEN -2.10 WHEN 'legitimo' THEN 7.20 ELSE 3.90 END,
1
FROM opciones_escenario
WHERE id_escenario = @ph4;

-- PHISHING 5
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'phishing',
 (SELECT id_remitente FROM remitentes_email WHERE correo='compras@proveedor-prioridad.co' LIMIT 1),
 'Compra urgente: cambiar cuenta bancaria',
 'Solicita cambiar cuenta de proveedor solo por correo.',
 'Siempre validar por doble canal antes de pagar.',
 1, NOW()
);
SET @ph5 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@ph5, 'legitimo', 'Aceptar cambio y pagar', 'Incorrecto: riesgo de fraude por suplantación.', 1),
(@ph5, 'falso', 'Reportar phishing y validar por llamada', 'Correcto: control de fraude aplicado.', 1),
(@ph5, 'timeout', 'No responder en tiempo', 'Demora sin validación aumenta riesgo.', 1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'falso' THEN 8 WHEN 'legitimo' THEN -10 ELSE -5 END,
CASE codigo_opcion WHEN 'falso' THEN 0 WHEN 'legitimo' THEN -12 ELSE -4 END,
CASE codigo_opcion WHEN 'falso' THEN -2.40 WHEN 'legitimo' THEN 7.00 ELSE 4.00 END,
1
FROM opciones_escenario
WHERE id_escenario = @ph5;

COMMIT;



USE CYBERGAME;
START TRANSACTION;

-- =====================================================
-- ESCENARIO 1 (Mejor: A, Peor: D)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='ti@empresa.local' LIMIT 1),
 'Implementacion urgente de MFA',
 'TI propone activar MFA para todos los accesos corporativos esta semana.',
 'Escenario de prueba. Mejor: A | Peor: D',
 1, NOW()
);
SET @d1 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d1,'A','Activar MFA para toda la compania de inmediato','[MEJOR OPCION] Mejora fuerte de seguridad de acceso.',1),
(@d1,'B','Activar MFA solo para directivos','Mejora parcial con brechas restantes.',1),
(@d1,'C','Hacer piloto de 2 meses','Aplaza proteccion global.',1),
(@d1,'D','Cancelar MFA por friccion de usuarios','[PEOR OPCION] Aumenta riesgo de compromiso.',1),
(@d1,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 12 WHEN 'B' THEN 5 WHEN 'C' THEN -2 WHEN 'D' THEN -11 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -6 WHEN 'B' THEN -3 WHEN 'C' THEN -1 WHEN 'D' THEN 0 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN -3.5 WHEN 'B' THEN -1.5 WHEN 'C' THEN 2.0 WHEN 'D' THEN 7.0 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d1;

-- =====================================================
-- ESCENARIO 2 (Mejor: C, Peor: B)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='soc@empresa.local' LIMIT 1),
 'Prueba de restauracion de backups',
 'SOC sugiere ejecutar un simulacro completo de restauracion este mes.',
 'Escenario de prueba. Mejor: C | Peor: B',
 1, NOW()
);
SET @d2 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d2,'A','Probar solo backup de base de datos','Cobertura incompleta.',1),
(@d2,'B','No probar y confiar en que funciona','[PEOR OPCION] Alto riesgo operacional.',1),
(@d2,'C','Simulacro integral de restauracion','[MEJOR OPCION] Reduce riesgo de indisponibilidad.',1),
(@d2,'D','Posponer al proximo trimestre','Riesgo moderado por demora.',1),
(@d2,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 4 WHEN 'B' THEN -10 WHEN 'C' THEN 11 WHEN 'D' THEN -3 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -2 WHEN 'B' THEN 0 WHEN 'C' THEN -5 WHEN 'D' THEN -1 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN -1.0 WHEN 'B' THEN 6.5 WHEN 'C' THEN -3.0 WHEN 'D' THEN 2.5 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d2;

-- =====================================================
-- ESCENARIO 3 (Mejor: B, Peor: D)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='rrhh@empresa.local' LIMIT 1),
 'Acceso temporal de proveedor externo',
 'RRHH solicita acceso para un consultor externo por 30 dias.',
 'Escenario de prueba. Mejor: B | Peor: D',
 1, NOW()
);
SET @d3 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d3,'A','Acceso total sin restricciones','Demasiado privilegio.',1),
(@d3,'B','Acceso minimo por rol con expiracion','[MEJOR OPCION] Control correcto de privilegios.',1),
(@d3,'C','Acceso minimo pero sin expiracion','Mejor que total, pero incompleto.',1),
(@d3,'D','Compartir credenciales de un empleado','[PEOR OPCION] Practica critica e insegura.',1),
(@d3,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN -6 WHEN 'B' THEN 10 WHEN 'C' THEN 3 WHEN 'D' THEN -12 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -1 WHEN 'B' THEN -2 WHEN 'C' THEN -1 WHEN 'D' THEN 0 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN 4.0 WHEN 'B' THEN -3.0 WHEN 'C' THEN -0.5 WHEN 'D' THEN 8.0 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d3;

-- =====================================================
-- ESCENARIO 4 (Mejor: A, Peor: C)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='soc@empresa.local' LIMIT 1),
 'Exceso de alertas en monitoreo',
 'El equipo reporta fatiga por exceso de alertas de bajo valor.',
 'Escenario de prueba. Mejor: A | Peor: C',
 1, NOW()
);
SET @d4 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d4,'A','Afinar reglas y priorizar alertas criticas','[MEJOR OPCION] Mejora eficiencia y deteccion real.',1),
(@d4,'B','Agregar mas personal sin ajustar reglas','Ayuda pero no corrige raiz.',1),
(@d4,'C','Desactivar alertas por una semana','[PEOR OPCION] Riesgo alto de ceguera operativa.',1),
(@d4,'D','Ignorar reporte del equipo','Riesgo creciente por fatiga.',1),
(@d4,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 9 WHEN 'B' THEN 2 WHEN 'C' THEN -11 WHEN 'D' THEN -7 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -2 WHEN 'B' THEN -5 WHEN 'C' THEN 0 WHEN 'D' THEN 0 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN -2.5 WHEN 'B' THEN -0.5 WHEN 'C' THEN 7.5 WHEN 'D' THEN 4.5 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d4;

-- =====================================================
-- ESCENARIO 5 (Mejor: D, Peor: A)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='ti@empresa.local' LIMIT 1),
 'Equipos con sistema operativo obsoleto',
 'Se detectaron 120 endpoints con version sin soporte.',
 'Escenario de prueba. Mejor: D | Peor: A',
 1, NOW()
);
SET @d5 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d5,'A','Mantenerlos igual y monitorear','[PEOR OPCION] Riesgo de explotacion conocido.',1),
(@d5,'B','Actualizar solo area financiera','Mitigacion parcial.',1),
(@d5,'C','Aislar red y actualizar por fases','Mejora aceptable.',1),
(@d5,'D','Plan de reemplazo/actualizacion inmediata','[MEJOR OPCION] Reduccion fuerte de riesgo.',1),
(@d5,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN -10 WHEN 'B' THEN 1 WHEN 'C' THEN 6 WHEN 'D' THEN 12 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN 0 WHEN 'B' THEN -3 WHEN 'C' THEN -5 WHEN 'D' THEN -8 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN 6.0 WHEN 'B' THEN 1.5 WHEN 'C' THEN -1.5 WHEN 'D' THEN -3.5 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d5;

-- =====================================================
-- ESCENARIO 6 (Mejor: B, Peor: C)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='finanzas@empresa.local' LIMIT 1),
 'Recorte de presupuesto de seguridad',
 'Finanzas solicita recortar costos del area de ciberseguridad.',
 'Escenario de prueba. Mejor: B | Peor: C',
 1, NOW()
);
SET @d6 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d6,'A','Aceptar recorte lineal del 30%','Afecta controles clave.',1),
(@d6,'B','Recortar solo gastos no criticos','[MEJOR OPCION] Sostiene controles esenciales.',1),
(@d6,'C','Eliminar SOC nocturno','[PEOR OPCION] Aumenta riesgo en horas criticas.',1),
(@d6,'D','Congelar nuevas licencias menores','Impacto moderado.',1),
(@d6,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN -5 WHEN 'B' THEN 7 WHEN 'C' THEN -12 WHEN 'D' THEN -2 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN 3 WHEN 'B' THEN 2 WHEN 'C' THEN 5 WHEN 'D' THEN 1 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN 3.5 WHEN 'B' THEN -2.5 WHEN 'C' THEN 8.0 WHEN 'D' THEN 1.5 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d6;

-- =====================================================
-- ESCENARIO 7 (Mejor: C, Peor: A)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='comunicaciones@empresa.local' LIMIT 1),
 'Simulacion de crisis por filtracion',
 'Comunicaciones propone simulacro de manejo reputacional ante incidente.',
 'Escenario de prueba. Mejor: C | Peor: A',
 1, NOW()
);
SET @d7 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d7,'A','No hacer simulacro por ahora','[PEOR OPCION] Te deja sin preparacion.',1),
(@d7,'B','Hacer taller teorico corto','Aporta parcialmente.',1),
(@d7,'C','Ejecutar simulacro integral con areas clave','[MEJOR OPCION] Mejora respuesta real.',1),
(@d7,'D','Simulacro solo con TI','Cobertura parcial.',1),
(@d7,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN -9 WHEN 'B' THEN 2 WHEN 'C' THEN 10 WHEN 'D' THEN 4 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN 0 WHEN 'B' THEN -1 WHEN 'C' THEN -3 WHEN 'D' THEN -2 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN 6.0 WHEN 'B' THEN 0.5 WHEN 'C' THEN -3.0 WHEN 'D' THEN -1.0 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d7;

-- =====================================================
-- ESCENARIO 8 (Mejor: A, Peor: D)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='ti@empresa.local' LIMIT 1),
 'Inventario de activos incompleto',
 'Faltan activos criticos en el inventario y no tienen dueño definido.',
 'Escenario de prueba. Mejor: A | Peor: D',
 1, NOW()
);
SET @d8 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d8,'A','Completar inventario y asignar responsables','[MEJOR OPCION] Fortalece gobierno de activos.',1),
(@d8,'B','Registrar solo servidores','Cobertura limitada.',1),
(@d8,'C','Registrar activos nuevos desde hoy','No corrige deuda historica.',1),
(@d8,'D','Posponer hasta auditoria anual','[PEOR OPCION] Riesgo y descontrol aumentan.',1),
(@d8,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 9 WHEN 'B' THEN 2 WHEN 'C' THEN -1 WHEN 'D' THEN -10 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -2 WHEN 'B' THEN -1 WHEN 'C' THEN 0 WHEN 'D' THEN 0 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN -2.5 WHEN 'B' THEN -0.5 WHEN 'C' THEN 1.5 WHEN 'D' THEN 6.5 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d8;

-- =====================================================
-- ESCENARIO 9 (Mejor: D, Peor: B)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='soc@empresa.local' LIMIT 1),
 'Accesos privilegiados sin rotacion',
 'Se detecta que claves de admin no rotan desde hace 9 meses.',
 'Escenario de prueba. Mejor: D | Peor: B',
 1, NOW()
);
SET @d9 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d9,'A','Rotar solo cuentas de base de datos','Mejora parcial.',1),
(@d9,'B','No tocar cuentas privilegiadas','[PEOR OPCION] Riesgo critico por credenciales estaticas.',1),
(@d9,'C','Rotacion manual trimestral','Ayuda pero depende de disciplina.',1),
(@d9,'D','Rotacion automatica + vault de secretos','[MEJOR OPCION] Control maduro y sostenible.',1),
(@d9,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN 4 WHEN 'B' THEN -12 WHEN 'C' THEN 6 WHEN 'D' THEN 11 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN -2 WHEN 'B' THEN 0 WHEN 'C' THEN -3 WHEN 'D' THEN -5 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN -1.0 WHEN 'B' THEN 8.0 WHEN 'C' THEN -1.5 WHEN 'D' THEN -3.5 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d9;

-- =====================================================
-- ESCENARIO 10 (Mejor: B, Peor: A)
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='finanzas@empresa.local' LIMIT 1),
 'Herramienta de deteccion de fraude por correo',
 'Hay opcion de contratar servicio anti-fraude para transferencias y proveedores.',
 'Escenario de prueba. Mejor: B | Peor: A',
 1, NOW()
);
SET @d10 = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d10,'A','No contratar nada','[PEOR OPCION] Mantiene exposicion actual sin mejoras.',1),
(@d10,'B','Contratar servicio completo con alertas','[MEJOR OPCION] Reduce fraude de forma consistente.',1),
(@d10,'C','Contratar plan basico','Mitigacion parcial.',1),
(@d10,'D','Hacer proceso manual temporal','Control limitado y propenso a error.',1),
(@d10,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion WHEN 'A' THEN -11 WHEN 'B' THEN 10 WHEN 'C' THEN 4 WHEN 'D' THEN -2 ELSE -5 END,
CASE codigo_opcion WHEN 'A' THEN 0 WHEN 'B' THEN -6 WHEN 'C' THEN -3 WHEN 'D' THEN -1 ELSE -3 END,
CASE codigo_opcion WHEN 'A' THEN 7.0 WHEN 'B' THEN -3.0 WHEN 'C' THEN -1.0 WHEN 'D' THEN 2.0 ELSE 4.0 END,
1
FROM opciones_escenario WHERE id_escenario=@d10;

COMMIT;



USE CYBERGAME;

START TRANSACTION;

-- =====================================================
-- ESCENARIO EXTRA (decision) - Mejor: C | Peor: A
-- =====================================================
INSERT INTO escenarios (tipo_escenario, id_remitente, titulo_correo, texto_correo, feedback_general, activo, creado_en)
VALUES (
 'decision',
 (SELECT id_remitente FROM remitentes_email WHERE correo='soc@empresa.local' LIMIT 1),
 'Excepcion urgente de firewall para proveedor',
 'Un proveedor solicita abrir puertos amplios en produccion para una integracion urgente.',
 'Escenario de prueba. Mejor: C | Peor: A',
 1, NOW()
);
SET @d_extra = LAST_INSERT_ID();

INSERT INTO opciones_escenario (id_escenario, codigo_opcion, texto_opcion, feedback_opcion, activa) VALUES
(@d_extra,'A','Abrir todos los puertos solicitados sin validacion','[PEOR OPCION] Exposicion critica e innecesaria.',1),
(@d_extra,'B','Abrir puertos por 30 dias y revisar despues','Reduce algo el riesgo, pero sigue siendo amplio.',1),
(@d_extra,'C','Habilitar acceso minimo por IP, puerto y horario, con monitoreo','[MEJOR OPCION] Control granular con menor superficie de ataque.',1),
(@d_extra,'D','Rechazar totalmente sin alternativa tecnica','Protege, pero puede bloquear operacion critica.',1),
(@d_extra,'timeout','No responder en el tiempo','Demora penalizada.',1);

INSERT INTO impactos_opcion (id_opcion, delta_cia_base, delta_presupuesto_base, delta_despido_base, activo)
SELECT id_opcion,
CASE codigo_opcion
  WHEN 'A' THEN -12
  WHEN 'B' THEN -4
  WHEN 'C' THEN 11
  WHEN 'D' THEN 2
  WHEN 'timeout' THEN -5
END,
CASE codigo_opcion
  WHEN 'A' THEN 0
  WHEN 'B' THEN -1
  WHEN 'C' THEN -3
  WHEN 'D' THEN -2
  WHEN 'timeout' THEN -3
END,
CASE codigo_opcion
  WHEN 'A' THEN 8.0
  WHEN 'B' THEN 3.0
  WHEN 'C' THEN -3.5
  WHEN 'D' THEN 1.0
  WHEN 'timeout' THEN 4.0
END,
1
FROM opciones_escenario
WHERE id_escenario = @d_extra;

COMMIT;


UPDATE escenarios
SET activo = 1
WHERE id_escenario = 2;



ALTER TABLE partidas ADD COLUMN max_rondas INT DEFAULT 25 AFTER despido_inicial;










USE CYBERGAME;

START TRANSACTION;

-- =====================================================
-- 1) PARTIDAS: agregar desglose CIA inicial/final
-- =====================================================
ALTER TABLE partidas
  ADD COLUMN c_inicial TINYINT UNSIGNED NULL AFTER cia_inicial,
  ADD COLUMN i_inicial TINYINT UNSIGNED NULL AFTER c_inicial,
  ADD COLUMN a_inicial TINYINT UNSIGNED NULL AFTER i_inicial,
  ADD COLUMN c_final TINYINT UNSIGNED NULL AFTER cia_final,
  ADD COLUMN i_final TINYINT UNSIGNED NULL AFTER c_final,
  ADD COLUMN a_final TINYINT UNSIGNED NULL AFTER i_final;

-- =====================================================
-- 2) EVENTOS_PARTIDA: agregar desglose CIA antes/después
-- =====================================================
ALTER TABLE eventos_partida
  ADD COLUMN c_antes TINYINT UNSIGNED NULL AFTER cia_antes,
  ADD COLUMN i_antes TINYINT UNSIGNED NULL AFTER c_antes,
  ADD COLUMN a_antes TINYINT UNSIGNED NULL AFTER i_antes,
  ADD COLUMN c_despues TINYINT UNSIGNED NULL AFTER cia_despues,
  ADD COLUMN i_despues TINYINT UNSIGNED NULL AFTER c_despues,
  ADD COLUMN a_despues TINYINT UNSIGNED NULL AFTER i_despues;

-- =====================================================
-- 3) IMPACTOS_OPCION: agregar deltas CIA separados
-- =====================================================
ALTER TABLE impactos_opcion
  ADD COLUMN delta_c_base SMALLINT NOT NULL DEFAULT 0 AFTER delta_cia_base,
  ADD COLUMN delta_i_base SMALLINT NOT NULL DEFAULT 0 AFTER delta_c_base,
  ADD COLUMN delta_a_base SMALLINT NOT NULL DEFAULT 0 AFTER delta_i_base;

-- =====================================================
-- 4) Opcional: dejar columnas antiguas intactas por compatibilidad
--    No se borran cia_* para no romper historial viejo ni el flujo actual.
-- =====================================================

COMMIT;

UPDATE impactos_opcion
SET delta_c_base = delta_cia_base,
    delta_i_base = delta_cia_base,
    delta_a_base = delta_cia_base
WHERE id_impacto > 0
  AND delta_c_base = 0
  AND delta_i_base = 0
  AND delta_a_base = 0;

USE CYBERGAME;

SHOW COLUMNS FROM partidas LIKE 'c_inicial';
SHOW COLUMNS FROM partidas LIKE 'i_inicial';
SHOW COLUMNS FROM partidas LIKE 'a_inicial';
SHOW COLUMNS FROM partidas LIKE 'c_final';
SHOW COLUMNS FROM partidas LIKE 'i_final';
SHOW COLUMNS FROM partidas LIKE 'a_final';

SHOW COLUMNS FROM eventos_partida LIKE 'c_antes';
SHOW COLUMNS FROM eventos_partida LIKE 'i_antes';
SHOW COLUMNS FROM eventos_partida LIKE 'a_antes';
SHOW COLUMNS FROM eventos_partida LIKE 'c_despues';
SHOW COLUMNS FROM eventos_partida LIKE 'i_despues';
SHOW COLUMNS FROM eventos_partida LIKE 'a_despues';

SHOW COLUMNS FROM impactos_opcion LIKE 'delta_c_base';
SHOW COLUMNS FROM impactos_opcion LIKE 'delta_i_base';
SHOW COLUMNS FROM impactos_opcion LIKE 'delta_a_base';



USE CYBERGAME;

UPDATE impactos_opcion
SET
  delta_c_base = delta_cia_base,
  delta_i_base = delta_cia_base,
  delta_a_base = delta_cia_base
WHERE id_impacto > 0
  AND (
    delta_c_base IS NULL
    OR delta_i_base IS NULL
    OR delta_a_base IS NULL
    OR (delta_c_base = 0 AND delta_i_base = 0 AND delta_a_base = 0)
  );
  
  
  USE CYBERGAME;

SELECT
  COUNT(*) AS filas_sin_desglose
FROM impactos_opcion
WHERE
  delta_c_base IS NULL
  OR delta_i_base IS NULL
  OR delta_a_base IS NULL
  OR (delta_c_base = 0 AND delta_i_base = 0 AND delta_a_base = 0);