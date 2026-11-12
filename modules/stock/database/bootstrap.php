<?php
declare(strict_types=1);

define('STOCK_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = STOCK_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once STOCK_ROOT . '/app/Core/helpers.php';

$config = require STOCK_ROOT . '/config/config.php';
\App\Core\Config::load($config);
date_default_timezone_set((string) \App\Core\Config::get('app.timezone', 'Africa/Casablanca'));
