<?php

declare(strict_types=1);

namespace App\Models;

require_once dirname(__DIR__, 2) . '/database/Database.php';

use App\Core\Database;
use PDO;

final class DatabaseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function planes(): array
    {
        return $this->pdo->query(
            'SELECT id, codigo, nombre, descripcion, precio_mensual, precio_anual,
                    moneda, max_qr_estaticos_mes, max_qr_dinamicos, max_claves_api,
                    dias_retencion_escaneos, permite_logo, permite_svg_pdf,
                    permite_analitica, permite_masivo, marca_agua, es_publico,
                    orden
             FROM planes
             WHERE eliminado = 0
             ORDER BY orden, id'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function usuarios(): array
    {
        return $this->pdo->query(
            'SELECT id, nombre, email, rol, estatus, email_verificado_en,
                    zona_horaria, ultimo_acceso, fecha_creacion, eliminado
             FROM usuarios
             WHERE eliminado = 0
             ORDER BY fecha_creacion DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function usuarioPorEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, email, password_hash, rol, estatus,
                    email_verificado_en, zona_horaria, ultimo_acceso,
                    fecha_creacion, eliminado
             FROM usuarios
             WHERE email = :email AND eliminado = 0
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function usuario(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nombre, email, rol, estatus, email_verificado_en,
                    zona_horaria, ultimo_acceso, fecha_creacion, eliminado
             FROM usuarios
             WHERE id = :id AND eliminado = 0
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function qrsDeUsuario(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, usuario_id, nombre, tipo, tipo_contenido, contenido,
                    codigo_corto, url_destino, diseno, logo_ruta, activo,
                    total_escaneos, ultimo_escaneo, fecha_creacion,
                    fecha_modificacion, eliminado
             FROM qr_codigos
             WHERE usuario_id = :usuario_id AND eliminado = 0
             ORDER BY fecha_creacion DESC'
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function qrs(): array
    {
        return $this->pdo->query(
            'SELECT id, usuario_id, nombre, tipo, tipo_contenido, contenido,
                    codigo_corto, url_destino, activo, total_escaneos,
                    fecha_creacion, eliminado
             FROM qr_codigos
             WHERE eliminado = 0
             ORDER BY fecha_creacion DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea el usuario y su suscripción vigente al plan gratis en una sola transacción.
     */
    public function registrarUsuario(array $data): int
    {
        $this->pdo->beginTransaction();
        try {
            $usuarioId = $this->insertarUsuario($data);
            $this->pdo->prepare(
                'INSERT INTO suscripciones (usuario_id, plan_id, estatus, usuario_creacion, fecha_creacion)
                 SELECT :usuario_id, id, "activa", :usuario_creacion, NOW()
                 FROM planes WHERE codigo = "gratis" AND eliminado = 0 LIMIT 1'
            )->execute([':usuario_id' => $usuarioId, ':usuario_creacion' => $usuarioId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $usuarioId;
    }

    private function insertarUsuario(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios
               (nombre, email, password_hash, rol, estatus,
                email_verificado_en, zona_horaria, usuario_creacion,
                usuario_modificacion, fecha_creacion, fecha_modificacion,
                eliminado)
             VALUES (:nombre, :email, :password_hash, :rol, :estatus,
                     :email_verificado_en, :zona_horaria, :usuario_creacion,
                     :usuario_modificacion, NOW(), NOW(), 0)'
        );
        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':email' => strtolower($data['email']),
            ':password_hash' => $data['password_hash'],
            ':rol' => $data['rol'] ?? 'cliente',
            ':estatus' => $data['estatus'] ?? 'pendiente',
            ':email_verificado_en' => $data['email_verificado_en'] ?? null,
            ':zona_horaria' => $data['zona_horaria'] ?? 'America/Mexico_City',
            ':usuario_creacion' => $data['usuario_creacion'] ?? 1,
            ':usuario_modificacion' => $data['usuario_modificacion'] ?? 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarUltimoAcceso(int $usuarioId): void
    {
        $this->pdo->prepare(
            'UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id'
        )->execute([':id' => $usuarioId]);
    }

    public function crearQr(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO qr_codigos
               (usuario_id, nombre, tipo, tipo_contenido, contenido,
                codigo_corto, url_destino, diseno, logo_ruta, activo,
                total_escaneos, usuario_creacion, usuario_modificacion,
                fecha_creacion, fecha_modificacion, eliminado)
             VALUES (:usuario_id, :nombre, :tipo, :tipo_contenido, :contenido,
                     :codigo_corto, :url_destino, :diseno, :logo_ruta, :activo,
                     0, :usuario_creacion, :usuario_modificacion, NOW(), NOW(), 0)'
        );
        $stmt->execute([
            ':usuario_id' => $data['usuario_id'],
            ':nombre' => $data['nombre'],
            ':tipo' => $data['tipo'],
            ':tipo_contenido' => $data['tipo_contenido'],
            ':contenido' => json_encode($data['contenido']),
            ':codigo_corto' => $data['codigo_corto'] ?? null,
            ':url_destino' => $data['url_destino'] ?? null,
            ':diseno' => json_encode($data['diseno'] ?? []),
            ':logo_ruta' => $data['logo_ruta'] ?? null,
            ':activo' => $data['activo'] ?? 1,
            ':usuario_creacion' => $data['usuario_creacion'] ?? 1,
            ':usuario_modificacion' => $data['usuario_modificacion'] ?? 1,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function incrementarEscaneos(int $qrId): void
    {
        $this->pdo->prepare(
            'UPDATE qr_codigos SET total_escaneos = total_escaneos + 1,
                                     ultimo_escaneo = NOW()
             WHERE id = :id AND activo = 1'
        )->execute([':id' => $qrId]);
    }

    public function resumenAdmin(): array
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) AS total_usuarios,
                    SUM(CASE WHEN rol = "cliente" AND estatus = "activo" THEN 1 ELSE 0 END) AS usuarios_activos,
                    SUM(CASE WHEN rol = "admin" THEN 1 ELSE 0 END) AS administradores
             FROM usuarios
             WHERE eliminado = 0'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $qrs = $this->pdo->query('SELECT COUNT(*) AS total FROM qr_codigos WHERE eliminado = 0')->fetch(PDO::FETCH_ASSOC);
        $escaneos = $this->pdo->query('SELECT COALESCE(SUM(total_escaneos), 0) AS total FROM qr_codigos WHERE eliminado = 0')->fetch(PDO::FETCH_ASSOC);

        return [
            'total_usuarios' => (int) ($row['total_usuarios'] ?? 0),
            'usuarios_activos' => (int) ($row['usuarios_activos'] ?? 0),
            'administradores' => (int) ($row['administradores'] ?? 0),
            'qr_total' => (int) ($qrs['total'] ?? 0),
            'escaneos_total' => (int) ($escaneos['total'] ?? 0),
        ];
    }
}
