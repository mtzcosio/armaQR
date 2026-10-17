<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Crea la tabla de notas internas de los usuarios (Administración › Usuarios › detalle).
 * Es seguro ejecutarla varias veces: solo crea lo que falta y no toca datos.
 *
 *   php database/migrate_notas_usuario.php
 */

require_once __DIR__ . '/Database.php';

App\Core\Database::connection()->exec(
    "CREATE TABLE IF NOT EXISTS notas_usuario (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id     INT UNSIGNED NOT NULL,                 -- de quién es la nota
        autor_id       INT UNSIGNED NULL,                     -- administrador que la escribió
        nota           TEXT         NOT NULL,
        fecha_creacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY ix_notas_usuario (usuario_id, fecha_creacion),
        CONSTRAINT fk_notas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
        CONSTRAINT fk_notas_autor   FOREIGN KEY (autor_id)   REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

echo "Tabla notas_usuario lista.\n";
