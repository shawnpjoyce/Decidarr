<?php
declare(strict_types=1);

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Security;

define('APP_PATH', __DIR__);
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $path = APP_PATH . DIRECTORY_SEPARATOR . $relative . '.php';

    if (is_file($path)) {
        require $path;
    }
});

load_env(BASE_PATH . '/.env');

$debug = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

$storagePath = BASE_PATH . '/storage';
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0750, true);
}

Security::configureSession();
session_start();
Security::sendHeaders();

$dbPath = getenv('DB_PATH') ?: $storagePath . '/app.sqlite';
$database = new Database($dbPath);
$database->migrate();

return new App(
    database: $database,
    request: Request::capture()
);

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}