<?php
declare(strict_types=1);

/**
 * Routeur pour le serveur PHP intégré :
 *   php -S 127.0.0.1:8080 -t stock stock/router.php
 *
 * Seuls les fichiers du dossier /assets sont servis directement.
 * Tout le reste passe par le front controller (donc par le routeur MVC/API).
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$assetsDir = realpath(__DIR__ . '/assets');
$requested = realpath(__DIR__ . $path);

if (
    $assetsDir !== false
    && $requested !== false
    && is_file($requested)
    && str_starts_with($requested, $assetsDir . DIRECTORY_SEPARATOR)
) {
    return false;
}

require __DIR__ . '/index.php';
