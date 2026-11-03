<?php
declare(strict_types=1);

namespace App\Controllers;

final class HomeController extends Controller
{
    public function index(): void
    {
        $this->view('home', ['title' => 'Inicio']);
    }

    public function notFound(): void
    {
        $this->view('errors/404', ['title' => 'Página no encontrada']);
    }
}
