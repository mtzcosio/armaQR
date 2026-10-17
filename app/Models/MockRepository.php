<?php

declare(strict_types=1);

namespace App\Models;

require_once dirname(__DIR__) . '/../includes/mock.php';

final class MockRepository
{
    public function planes(): array
    {
        return mock_planes();
    }

    public function usuarios(): array
    {
        return mock_usuarios();
    }

    public function usuario(int $id): ?array
    {
        return mock_usuario($id);
    }

    public function qrs(): array
    {
        return mock_qrs();
    }

    public function qrsDeUsuario(int $id): array
    {
        return mock_qrs_de($id);
    }

    public function resumenAdmin(): array
    {
        return mock_resumen_admin();
    }

    public function usuarioActual(): array
    {
        return mock_usuario_actual();
    }
}
