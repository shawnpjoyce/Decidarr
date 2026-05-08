<?php

use App\Core\Security;

$appName = getenv('APP_NAME') ?: 'Decidarrr';
$nonce = Security::nonce();
$cssVersion = is_file(BASE_PATH . '/public/assets/css/style.css')
    ? (string) filemtime(BASE_PATH . '/public/assets/css/style.css')
    : '1';
$jsVersion = is_file(BASE_PATH . '/public/assets/js/script.js')
    ? (string) filemtime(BASE_PATH . '/public/assets/js/script.js')
    : '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= Security::escape(Security::csrfToken()) ?>">
    <title><?= Security::escape($appName) ?></title>
    <link rel="stylesheet" href="/assets/css/token.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= Security::escape($cssVersion) ?>">
    <script src="/assets/js/script.js?v=<?= Security::escape($jsVersion) ?>" nonce="<?= Security::escape($nonce) ?>" defer></script>
</head>
<body class="<?= !empty($movie) ? 'has-winner' : '' ?>">
    <main class="shell">
        <?= $content ?>
    </main>
</body>
</html>