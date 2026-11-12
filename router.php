<?php
declare(strict_types=1);

/**
 * Routeur pour le serveur PHP intégré (développement local).
 *
 *   php -S 127.0.0.1:8080 -t gestion_unifiee gestion_unifiee/router.php
 *
 * En production (Apache/cPanel), ce sont les fichiers .htaccess qui jouent
 * ce rôle ; ce fichier n'est jamais exposé.
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

// Dossiers internes : jamais servis directement.
if (preg_match('#^/(shared|database|storage)(/|$)#', $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 — Ressource introuvable';
    return true;
}

$target = __DIR__ . $path;

// Fichiers statiques et scripts PHP existants : le serveur intégré s'en charge.
if ($path !== '/' && is_file($target)) {
    return false;
}

// Dossiers avec index.php (modules, api…).
if (is_dir($target) && is_file(rtrim($target, '/\\') . '/index.php')) {
    require rtrim($target, '/\\') . '/index.php';
    return true;
}

// Module Stock : front controller unique (URLs sans .php).
if ($path === '/modules/stock' || str_starts_with($path, '/modules/stock/')) {
    require __DIR__ . '/modules/stock/index.php';
    return true;
}

// Racine de l'application.
if ($path === '/' || $path === '') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Page introuvable</title>'
    . '<body style="font-family:system-ui;background:#080b10;color:#e7eef8;padding:48px">'
    . '<h1>404 — Page introuvable</h1><p><a style="color:#5b8cff" href="/">Retour à l\'accueil</a></p></body></html>';
return true;
