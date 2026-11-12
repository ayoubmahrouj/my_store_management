<?php
declare(strict_types=1);

/**
 * Routeur de sécurité pour le serveur PHP intégré.
 *
 * Lancement :
 *   php -S 0.0.0.0:8001 router.php
 *
 * Il bloque les dossiers internes, les fichiers cachés et les fichiers
 * sensibles, comme le fait le fichier .htaccess avec Apache.
 */

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
if ($https) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$deny = static function (): bool {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    return true;
};

$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($uri) || $uri === '' || $uri[0] !== '/') {
    return $deny();
}

$relative = rawurldecode(ltrim($uri, '/'));
if (str_contains($relative, "\0") || str_contains($relative, '\\')) {
    return $deny();
}

$segments = explode('/', $relative);
foreach ($segments as $segment) {
    if ($segment !== '' && $segment[0] === '.') {
        return $deny();
    }
    if ($segment === '..') {
        return $deny();
    }
}

$blockedDirs = ['config', 'includes', 'lib', 'storage', 'database'];
$first = strtolower((string) ($segments[0] ?? ''));
if (in_array($first, $blockedDirs, true)) {
    return $deny();
}

$base = strtolower(basename($relative));
if (in_array($base, ['bootstrap.php', 'router.php'], true)) {
    return $deny();
}

$blockedExtensions = [
    'sql', 'sqlite', 'db', 'log', 'ini', 'env', 'bak', 'old',
    'zip', 'tar', 'gz', 'md', 'json', 'lock', 'yml', 'yaml',
];
$extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
if ($extension !== '' && in_array($extension, $blockedExtensions, true)) {
    return $deny();
}

$root = realpath(__DIR__);
$path = $relative === '' ? $root : realpath(__DIR__ . DIRECTORY_SEPARATOR . $relative);

if ($root === false || $path === false) {
    return false; // Le serveur intégré renverra la 404 standard.
}

$rootNorm = str_replace('\\', '/', $root);
$pathNorm = str_replace('\\', '/', $path);
if ($pathNorm !== $rootNorm && !str_starts_with($pathNorm, $rootNorm . '/')) {
    return $deny();
}

if (is_dir($path) && !is_file($path . DIRECTORY_SEPARATOR . 'index.php')) {
    return $deny();
}

return false;
