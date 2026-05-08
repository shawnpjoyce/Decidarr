<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\ErrorController;
use App\Controllers\PlexController;

final class App
{
    public function __construct(
        private readonly Database $database,
        private readonly Request $request
    ) {
    }

    public function run(): void
    {
        $router = new Router($this->database);
        $plex = PlexController::class;

        $router->get('/', [$plex, 'index']);
        $router->post('/pick', [$plex, 'pick']);
        $router->get('/poster', [$plex, 'poster']);
        $router->post('/reset', [$plex, 'reset']);

        if (!$router->dispatch($this->request)) {
            http_response_code(404);
            (new ErrorController($this->database, $this->request))->notFound();
        }
    }
}