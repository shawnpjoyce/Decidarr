<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Security;
use App\Core\View;

abstract class BaseController
{
    public function __construct(
        protected readonly Database $database,
        protected readonly Request $request
    ) {
    }

    protected function view(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    protected function requireValidCsrf(): void
    {
        if (!Security::verifyCsrf($this->request->post('_csrf_token'))) {
            http_response_code(419);
            $this->view('game/index', [
                'movie' => null,
                'history' => [],
                'errors' => ['Your form expired. Please refresh and try again.'],
                'old' => [],
                'libraries' => [],
            ]);
            exit;
        }
    }
}