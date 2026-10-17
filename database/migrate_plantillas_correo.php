<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Crea las tablas del módulo de plantillas de correo (Administración › Correos).
 * Es seguro ejecutarla varias veces: solo crea lo que falta y no toca datos.
 *
 *   php database/migrate_plantillas_correo.php
 */

require_once __DIR__ . '/Database.php';

use App\Core\Database;

$pdo = Database::connection();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS plantillas_correo (
        id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nombre               VARCHAR(150) NOT NULL,                 -- nombre legible, solo para el admin
        codigo               VARCHAR(100) NOT NULL,                 -- contrato con el código: 'BIENVENIDA'
        tipo                 VARCHAR(50)  NOT NULL DEFAULT 'Personalizado',
        estatus              ENUM('borrador','activa','inactiva') NOT NULL DEFAULT 'borrador',
        remitente_nombre     VARCHAR(150) NOT NULL DEFAULT '',      -- vacío = el de Configuración › Correo
        remitente_correo     VARCHAR(190) NOT NULL DEFAULT '',
        asunto               VARCHAR(255) NOT NULL DEFAULT '',
        preheader            VARCHAR(255) NOT NULL DEFAULT '',
        contenido_html       MEDIUMTEXT   NULL,
        usuario_modificacion INT UNSIGNED NULL,
        fecha_creacion       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_plantillas_codigo (codigo),
        KEY ix_plantillas_estatus (estatus)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS variables_correo (
        id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        categoria          VARCHAR(100) NOT NULL,                   -- texto libre; se agrupa en el panel
        nombre             VARCHAR(100) NOT NULL,                   -- sin llaves: 'codigo_descuento'
        descripcion        VARCHAR(255) NOT NULL DEFAULT '',
        valor_ejemplo      VARCHAR(255) NOT NULL DEFAULT '',        -- vista previa y correo de prueba
        valor              VARCHAR(500) NOT NULL DEFAULT '',        -- valor real en los envíos (vacío = sin valor)
        fecha_creacion     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_variables_nombre (nombre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

echo "Tablas de plantillas de correo listas (plantillas_correo, variables_correo).\n";
