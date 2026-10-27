-- =====================================================================
-- QR SaaS — Esquema de base de datos
-- Motor: MySQL 8+ (compatible con MariaDB 10.5+)
-- Convenciones:
--   * Tablas maestras con campos de auditoría + borrado lógico (eliminado)
--   * Tablas transaccionales / de bitácora sin borrado lógico
--   * Fechas en UTC; la zona horaria del usuario se aplica en la app
-- =====================================================================

-- La base debe existir previamente y estar configurada en DB_NAME.
-- El usuario de la aplicación necesita CREATE, ALTER, INSERT y SELECT.

-- ---------------------------------------------------------------------
-- 1. PLANES (maestra): define precios y límites de cada plan
-- ---------------------------------------------------------------------
CREATE TABLE planes (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo                   VARCHAR(30)  NOT NULL,            -- 'gratis', 'pro', 'negocio'
  nombre                   VARCHAR(80)  NOT NULL,
  descripcion              VARCHAR(500) NULL,
  precio_mensual           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  precio_anual             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  moneda                   CHAR(3)      NOT NULL DEFAULT 'MXN',
  -- Límites (NULL = ilimitado)
  max_qr_estaticos_mes     INT UNSIGNED NULL,
  max_qr_dinamicos         INT UNSIGNED NULL,               -- activos al mismo tiempo
  max_claves_api           INT UNSIGNED NOT NULL DEFAULT 0,
  dias_retencion_escaneos  INT UNSIGNED NULL,
  -- Funcionalidades
  permite_logo             TINYINT(1)   NOT NULL DEFAULT 0,
  permite_svg_pdf          TINYINT(1)   NOT NULL DEFAULT 0,
  permite_analitica        TINYINT(1)   NOT NULL DEFAULT 0,
  permite_masivo           TINYINT(1)   NOT NULL DEFAULT 0,
  marca_agua               TINYINT(1)   NOT NULL DEFAULT 1,
  es_publico               TINYINT(1)   NOT NULL DEFAULT 1,  -- visible en la página de precios
  orden                    SMALLINT     NOT NULL DEFAULT 0,
  -- Auditoría
  usuario_creacion         INT UNSIGNED NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_modificacion     INT UNSIGNED NULL,
  fecha_modificacion       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  eliminado                TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_planes_codigo (codigo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. USUARIOS (maestra)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre                   VARCHAR(120) NOT NULL,
  email                    VARCHAR(190) NOT NULL,
  password_hash            VARCHAR(255) NOT NULL,            -- password_hash() de PHP
  rol                      ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  estatus                  ENUM('pendiente','activo','suspendido') NOT NULL DEFAULT 'pendiente',
  email_verificado_en      DATETIME     NULL,
  zona_horaria             VARCHAR(50)  NOT NULL DEFAULT 'America/Mexico_City',
  id_cliente_pasarela      VARCHAR(100) NULL,                -- Stripe cus_xxx / customer de MP
  id_cliente_conekta       VARCHAR(100) NULL,                -- Conekta cus_xxx (database/migrate_pagos.php)
  ultimo_acceso            DATETIME     NULL,
  -- Auditoría
  usuario_creacion         INT UNSIGNED NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_modificacion     INT UNSIGNED NULL,
  fecha_modificacion       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  eliminado                TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. TOKENS DE USUARIO: verificación de email y recuperación de contraseña
--    (se guarda el hash del token, nunca el token en claro)
-- ---------------------------------------------------------------------
CREATE TABLE tokens_usuario (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id               INT UNSIGNED NOT NULL,
  tipo                     ENUM('verificacion_email','recuperacion_password') NOT NULL,
  token_hash               CHAR(64)     NOT NULL,            -- hash('sha256', $token)
  expira_en                DATETIME     NOT NULL,
  usado_en                 DATETIME     NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tokens_hash (token_hash),
  KEY ix_tokens_usuario (usuario_id, tipo),
  CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. SUSCRIPCIONES: todo usuario tiene una suscripción vigente
--    (al registrarse se le crea una al plan 'gratis')
-- ---------------------------------------------------------------------
CREATE TABLE suscripciones (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id               INT UNSIGNED NOT NULL,
  plan_id                  INT UNSIGNED NOT NULL,
  estatus                  ENUM('activa','en_prueba','pago_pendiente','cancelada','vencida') NOT NULL DEFAULT 'activa',
  periodicidad             ENUM('mensual','anual') NULL,     -- NULL en plan gratis
  pasarela                 ENUM('stripe','mercadopago','conekta') NULL,
  id_externo               VARCHAR(100) NULL,                -- sub_xxx / preapproval id
  periodo_inicio           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  periodo_fin              DATETIME     NULL,                -- NULL = no vence (gratis)
  prueba_fin               DATETIME     NULL,
  cancelar_al_final        TINYINT(1)   NOT NULL DEFAULT 0,  -- el usuario canceló pero conserva acceso hasta periodo_fin
  cancelada_en             DATETIME     NULL,
  -- Auditoría
  usuario_creacion         INT UNSIGNED NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_modificacion     INT UNSIGNED NULL,
  fecha_modificacion       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  eliminado                TINYINT(1)   NOT NULL DEFAULT 0,
  -- Garantiza UNA sola suscripción vigente por usuario
  usuario_vigente          INT UNSIGNED AS (
                             CASE WHEN estatus IN ('activa','en_prueba','pago_pendiente') AND eliminado = 0
                                  THEN usuario_id END) STORED,
  UNIQUE KEY uq_suscripcion_vigente (usuario_vigente),
  UNIQUE KEY uq_suscripcion_externa (pasarela, id_externo),
  KEY ix_suscripciones_usuario (usuario_id),
  KEY ix_suscripciones_vencimiento (estatus, periodo_fin),
  CONSTRAINT fk_suscripciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_suscripciones_plan    FOREIGN KEY (plan_id)    REFERENCES planes(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. PAGOS (transaccional, sin borrado lógico)
-- ---------------------------------------------------------------------
CREATE TABLE pagos (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id               INT UNSIGNED NOT NULL,
  suscripcion_id           INT UNSIGNED NULL,
  pasarela                 ENUM('stripe','mercadopago','conekta') NOT NULL,
  id_externo               VARCHAR(100) NOT NULL,            -- id del cargo/factura en la pasarela
  concepto                 VARCHAR(200) NOT NULL,
  monto                    DECIMAL(10,2) NOT NULL,
  moneda                   CHAR(3)      NOT NULL DEFAULT 'MXN',
  estatus                  ENUM('pendiente','pagado','fallido','reembolsado') NOT NULL DEFAULT 'pendiente',
  metodo_pago              VARCHAR(50)  NULL,                -- tarjeta, oxxo, spei...
  ultimos_digitos          CHAR(4)      NULL,                -- nunca guardar el número completo
  fecha_pago               DATETIME     NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pagos_externo (pasarela, id_externo),
  KEY ix_pagos_usuario (usuario_id, fecha_creacion),
  CONSTRAINT fk_pagos_usuario     FOREIGN KEY (usuario_id)     REFERENCES usuarios(id),
  CONSTRAINT fk_pagos_suscripcion FOREIGN KEY (suscripcion_id) REFERENCES suscripciones(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. EVENTOS DE PASARELA (bitácora de webhooks, garantiza idempotencia)
-- ---------------------------------------------------------------------
CREATE TABLE eventos_pasarela (
  id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pasarela                 ENUM('stripe','mercadopago','conekta') NOT NULL,
  id_evento                VARCHAR(120) NOT NULL,
  tipo                     VARCHAR(100) NOT NULL,            -- invoice.paid, subscription.deleted...
  payload                  JSON         NOT NULL,
  procesado_en             DATETIME     NULL,
  error                    VARCHAR(1000) NULL,
  fecha_recepcion          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_evento (pasarela, id_evento),
  KEY ix_eventos_pendientes (procesado_en)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. QR_CODIGOS (maestra)
--    estatico: el contenido va dentro del QR
--    dinamico: el QR apunta a /r/{codigo_corto} y redirige a url_destino
-- ---------------------------------------------------------------------
CREATE TABLE qr_codigos (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id               INT UNSIGNED NOT NULL,
  nombre                   VARCHAR(120) NOT NULL,
  tipo                     ENUM('estatico','dinamico') NOT NULL,
  tipo_contenido           VARCHAR(30)  NOT NULL,            -- url, wifi, pdf, menu… (catálogo en includes/qr_tipos.php)
  contenido                JSON         NOT NULL,            -- datos estructurados según tipo_contenido
  codigo_corto             VARCHAR(12)  NULL,                -- solo dinámicos, ej. 'a8Kz3P'
  url_destino              VARCHAR(2048) NULL,               -- solo dinámicos; '' en los de tipo página
  diseno                   JSON         NULL,                -- colores, forma de módulos, ojos, margen
  logo_ruta                VARCHAR(255) NULL,
  activo                   TINYINT(1)   NOT NULL DEFAULT 1,  -- un dinámico pausado no redirige
  total_escaneos           INT UNSIGNED NOT NULL DEFAULT 0,  -- contador desnormalizado para listados
  ultimo_escaneo           DATETIME     NULL,
  -- Auditoría
  usuario_creacion         INT UNSIGNED NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_modificacion     INT UNSIGNED NULL,
  fecha_modificacion       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  eliminado                TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_qr_codigo_corto (codigo_corto),
  KEY ix_qr_usuario (usuario_id, eliminado, tipo),
  CONSTRAINT fk_qr_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT ck_qr_dinamico CHECK (
    (tipo = 'estatico' AND codigo_corto IS NULL)
    OR (tipo = 'dinamico' AND codigo_corto IS NOT NULL AND url_destino IS NOT NULL)
  )
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. HISTORIAL DE DESTINOS (qué URL tenía un QR dinámico y cuándo cambió)
-- ---------------------------------------------------------------------
CREATE TABLE qr_historial_destinos (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  qr_id                    INT UNSIGNED NOT NULL,
  url_anterior             VARCHAR(2048) NULL,
  url_nueva                VARCHAR(2048) NOT NULL,
  usuario_creacion         INT UNSIGNED NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_historial_qr (qr_id, fecha_creacion),
  CONSTRAINT fk_historial_qr FOREIGN KEY (qr_id) REFERENCES qr_codigos(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. QR_ESCANEOS (bitácora de alto volumen, sin auditoría ni borrado lógico)
--    La IP se guarda como hash por privacidad (LFPDPPP)
-- ---------------------------------------------------------------------
CREATE TABLE qr_escaneos (
  id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  qr_id                    INT UNSIGNED NOT NULL,
  fecha_escaneo            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash                  CHAR(64)     NULL,                -- sha256(ip + sal) para contar únicos
  pais                     CHAR(2)      NULL,
  region                   VARCHAR(100) NULL,
  ciudad                   VARCHAR(100) NULL,
  dispositivo              ENUM('movil','tablet','escritorio','otro') NULL,
  sistema_operativo        VARCHAR(50)  NULL,
  navegador                VARCHAR(50)  NULL,
  idioma                   VARCHAR(10)  NULL,
  referer                  VARCHAR(500) NULL,
  KEY ix_escaneos_qr_fecha (qr_id, fecha_escaneo),
  KEY ix_escaneos_fecha (fecha_escaneo),                     -- para purgar por retención
  CONSTRAINT fk_escaneos_qr FOREIGN KEY (qr_id) REFERENCES qr_codigos(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. USO MENSUAL: contadores para validar límites del plan rápidamente
-- ---------------------------------------------------------------------
CREATE TABLE uso_mensual (
  usuario_id               INT UNSIGNED NOT NULL,
  periodo                  CHAR(7)      NOT NULL,            -- 'YYYY-MM'
  qr_estaticos_generados   INT UNSIGNED NOT NULL DEFAULT 0,
  qr_dinamicos_creados     INT UNSIGNED NOT NULL DEFAULT 0,
  descargas                INT UNSIGNED NOT NULL DEFAULT 0,
  llamadas_api             INT UNSIGNED NOT NULL DEFAULT 0,
  fecha_modificacion       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, periodo),
  CONSTRAINT fk_uso_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. CLAVES API (maestra) — solo planes que lo permitan
-- ---------------------------------------------------------------------
CREATE TABLE claves_api (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id               INT UNSIGNED NOT NULL,
  nombre                   VARCHAR(80)  NOT NULL,
  prefijo                  CHAR(8)      NOT NULL,            -- visible al usuario: 'qr_live_'
  clave_hash               CHAR(64)     NOT NULL,            -- la clave completa solo se muestra una vez
  ultimo_uso               DATETIME     NULL,
  expira_en                DATETIME     NULL,
  revocada_en              DATETIME     NULL,
  -- Auditoría
  usuario_creacion         INT UNSIGNED NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_modificacion     INT UNSIGNED NULL,
  fecha_modificacion       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  eliminado                TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_claves_hash (clave_hash),
  KEY ix_claves_usuario (usuario_id),
  CONSTRAINT fk_claves_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- =====================================================================
-- VISTA: plan vigente y límites de cada usuario (usar en el middleware)
-- =====================================================================
CREATE OR REPLACE VIEW vw_plan_usuario AS
SELECT
  u.id            AS usuario_id,
  u.email,
  s.id            AS suscripcion_id,
  s.estatus       AS estatus_suscripcion,
  s.periodo_fin,
  s.cancelar_al_final,
  p.codigo        AS plan_codigo,
  p.nombre        AS plan_nombre,
  p.max_qr_estaticos_mes,
  p.max_qr_dinamicos,
  p.max_claves_api,
  p.dias_retencion_escaneos,
  p.permite_logo,
  p.permite_svg_pdf,
  p.permite_analitica,
  p.permite_masivo,
  p.marca_agua,
  (SELECT COUNT(*) FROM qr_codigos q
    WHERE q.usuario_id = u.id AND q.tipo = 'dinamico'
      AND q.activo = 1 AND q.eliminado = 0)           AS qr_dinamicos_activos,
  COALESCE(um.qr_estaticos_generados, 0)              AS qr_estaticos_mes_actual
FROM usuarios u
JOIN suscripciones s ON s.usuario_vigente = u.id
JOIN planes p        ON p.id = s.plan_id
LEFT JOIN uso_mensual um
       ON um.usuario_id = u.id AND um.periodo = DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m')
WHERE u.eliminado = 0;

-- =====================================================================
-- DATOS INICIALES (precios de ejemplo, ajústalos)
-- =====================================================================
INSERT INTO planes
  (codigo, nombre, descripcion, precio_mensual, precio_anual,
   max_qr_estaticos_mes, max_qr_dinamicos, max_claves_api, dias_retencion_escaneos,
   permite_logo, permite_svg_pdf, permite_analitica, permite_masivo, marca_agua, orden)
VALUES
  ('gratis',  'Gratis',  'QR estáticos básicos en PNG',              0.00,    0.00,  50,   0, 0, NULL, 0, 0, 0, 0, 1, 1),
  ('pro',     'Pro',     'QR dinámicos, analítica y diseño',        149.00, 1490.00, NULL, 50, 0,  365, 1, 1, 1, 0, 0, 2),
  ('negocio', 'Negocio', 'Todo Pro + generación masiva y API',      499.00, 4990.00, NULL, NULL, 5, NULL, 1, 1, 1, 1, 0, 3);

-- =====================================================================
-- PLANTILLAS DE CORREO (Administración › Correos)
-- También se pueden crear con: php database/migrate_plantillas_correo.php
-- =====================================================================
CREATE TABLE plantillas_correo (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre                   VARCHAR(150) NOT NULL,                 -- nombre legible, solo para el admin
  codigo                   VARCHAR(100) NOT NULL,                 -- contrato con el código: 'BIENVENIDA'
  tipo                     VARCHAR(50)  NOT NULL DEFAULT 'Personalizado',
  estatus                  ENUM('borrador','activa','inactiva') NOT NULL DEFAULT 'borrador',
  remitente_nombre         VARCHAR(150) NOT NULL DEFAULT '',      -- vacío = el de Configuración › Correo
  remitente_correo         VARCHAR(190) NOT NULL DEFAULT '',
  asunto                   VARCHAR(255) NOT NULL DEFAULT '',
  preheader                VARCHAR(255) NOT NULL DEFAULT '',
  contenido_html           MEDIUMTEXT   NULL,
  usuario_modificacion     INT UNSIGNED NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_plantillas_codigo (codigo),
  KEY ix_plantillas_estatus (estatus)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE variables_correo (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria                VARCHAR(100) NOT NULL,                 -- texto libre; se agrupa en el panel
  nombre                   VARCHAR(100) NOT NULL,                 -- sin llaves: 'codigo_descuento'
  descripcion              VARCHAR(255) NOT NULL DEFAULT '',
  valor_ejemplo            VARCHAR(255) NOT NULL DEFAULT '',      -- vista previa y correo de prueba
  valor                    VARCHAR(500) NOT NULL DEFAULT '',      -- valor real en los envíos (vacío = sin valor)
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_variables_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- NOTAS INTERNAS DE USUARIOS (solo las ve el equipo de administración)
-- También se puede crear con: php database/migrate_notas_usuario.php
-- =====================================================================
CREATE TABLE notas_usuario (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id               INT UNSIGNED NOT NULL,                 -- de quién es la nota
  autor_id                 INT UNSIGNED NULL,                     -- administrador que la escribió
  nota                     TEXT         NOT NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_notas_usuario (usuario_id, fecha_creacion),
  CONSTRAINT fk_notas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_notas_autor   FOREIGN KEY (autor_id)   REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- DOCUMENTOS LEGALES (Términos y condiciones, Aviso de privacidad), con versiones
-- También se crea, con su versión 1, con: php database/migrate_documentos_legales.php
-- =====================================================================
CREATE TABLE documentos_legales (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo                     ENUM('terminos','privacidad') NOT NULL,
  version                  INT UNSIGNED NOT NULL DEFAULT 0,      -- 0 en borradores
  titulo                   VARCHAR(150) NOT NULL,
  contenido_html           MEDIUMTEXT   NOT NULL,
  estatus                  ENUM('borrador','publicado') NOT NULL DEFAULT 'borrador',
  autor_id                 INT UNSIGNED NULL,
  publicado_en             DATETIME     NULL,
  fecha_creacion           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_legales_tipo (tipo, estatus, version),
  CONSTRAINT fk_legales_autor FOREIGN KEY (autor_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
