<?php

declare(strict_types=1);

namespace App\Core;

require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/app/Models/DatabaseRepository.php';

use App\Models\DatabaseRepository;

final class Auth
{
    /** Usuario cargado en esta petición (evita consultar la BD en cada llamada). */
    private static ?array $cache = null;
    private static ?int $cacheId = null;

    public static function user(): ?array
    {
        if (empty($_SESSION['usuario_id'])) {
            return null;
        }

        $id = (int) $_SESSION['usuario_id'];
        if (self::$cacheId !== $id) {
            self::$cache = (new DatabaseRepository())->usuario($id);
            self::$cacheId = $id;
        }

        return self::$cache;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!self::isActive($user)) {
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        }

        return $user;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return self::isActive($user) && self::isAdminRole($user);
    }

    public static function requireAdmin(): array
    {
        if (!self::isAdmin()) {
            header('Location: ' . BASE_URL . 'login.php?admin_required=1');
            exit;
        }

        return self::user();
    }

    public static function isAdminRole(?array $user): bool
    {
        return $user !== null && ($user['rol'] ?? '') === 'admin';
    }

    public static function isActive(?array $user): bool
    {
        return $user !== null && ($user['estatus'] ?? '') === 'activo';
    }

    public static function logout(): void
    {
        self::$cache = null;
        self::$cacheId = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
