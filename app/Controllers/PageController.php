<?php

declare(strict_types=1);

namespace App\Controllers;

require_once dirname(__DIR__, 2) . '/app/Core/Controller.php';
require_once dirname(__DIR__, 2) . '/app/Core/View.php';
require_once dirname(__DIR__, 2) . '/app/Core/Auth.php';
require_once dirname(__DIR__, 2) . '/app/Models/DatabaseRepository.php';

use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;
use App\Models\DatabaseRepository;

final class PageController extends Controller
{
    private DatabaseRepository $repository;

    public function __construct()
    {
        $this->repository = new DatabaseRepository();
    }

    // Ruta pública: muestra la página principal y los planes disponibles.
    public function home(): void
    {
        View::render('home', ['planes' => $this->repository->planes()], 'publico');
    }

    // Ruta pública: muestra el formulario de inicio de sesión.
    public function login(): void
    {
        View::render('login', [], 'publico');
    }

    public function registro(): void
    {
        View::render('registro', ['planes' => $this->repository->planes()], 'publico');
    }

    public function clientDashboard(): void
    {
        $usuario = Auth::requireUser();
        View::render('client/dashboard', [
            'usuario' => $usuario,
            'qrs' => $this->repository->qrsDeUsuario((int) $usuario['id']),
        ], 'app');
    }

    public function crear(): void
    {
        $usuario = Auth::requireUser();
        View::render('client/crear', [
            'usuario' => $usuario,
            'qrs' => $this->repository->qrsDeUsuario((int) $usuario['id']),
        ], 'app');
    }

    public function estadisticas(): void
    {
        $usuario = Auth::requireUser();
        View::render('client/estadisticas', [
            'usuario' => $usuario,
            'qrs' => $this->repository->qrsDeUsuario((int) $usuario['id']),
        ], 'app');
    }

    public function plan(): void
    {
        $usuario = Auth::requireUser();
        View::render('client/plan', [
            'usuario' => $usuario,
            'planes' => $this->repository->planes(),
            'qrs' => $this->repository->qrsDeUsuario((int) $usuario['id']),
        ], 'app');
    }

    public function adminIndex(): void
    {
        Auth::requireAdmin();
        View::render('admin/index', [
            'resumen' => $this->repository->resumenAdmin(),
            'usuarios' => $this->repository->usuarios(),
        ], 'admin');
    }

    public function adminQrs(): void
    {
        Auth::requireAdmin();
        View::render('admin/qrs', [
            'qrs' => $this->repository->qrs(),
            'usuarios' => $this->repository->usuarios(),
        ], 'admin');
    }
}
