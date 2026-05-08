<?php
declare(strict_types=1);

namespace App\Controllers;

final class ErrorController extends BaseController
{
    public function notFound(): void
    {
        $this->view('game/index', [
            'movie' => null,
            'history' => [],
            'errors' => ['The page you requested was not found.'],
            'old' => [],
            'libraries' => [],
        ]);
    }
}