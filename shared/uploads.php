<?php
declare(strict_types=1);

/**
 * Téléversement et lecture sécurisée des images (bons, chèques, effets…).
 *
 * Les fichiers sont stockés dans storage/uploads/<sous-dossier>/<AAAA>/<MM>/
 * et ne sont jamais servis directement : chaque module les expose via un
 * point d'entrée PHP protégé par la session.
 */

const SH_UPLOAD_MAX_BYTES = 6291456; // 6 Mo

function sh_upload_root(): string
{
    return GOS_ROOT . '/storage/uploads';
}

function sh_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux (limite du serveur ou 6 Mo maximum).',
        UPLOAD_ERR_PARTIAL   => 'Le téléversement a été interrompu. Réessayez.',
        UPLOAD_ERR_NO_FILE   => 'Aucun fichier sélectionné.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire introuvable sur le serveur.',
        UPLOAD_ERR_CANT_WRITE => 'Écriture du fichier impossible sur le serveur.',
        UPLOAD_ERR_EXTENSION => 'Téléversement bloqué par une extension PHP.',
        default              => 'Le téléversement a échoué.',
    };
}

/**
 * Enregistre une image téléversée.
 *
 * @return array{ok: bool, path?: string, error?: string}
 *         ok=false avec error='' lorsqu'aucun fichier n'a été fourni.
 */
function sh_store_image(array $file, string $subdir, int $maxBytes = SH_UPLOAD_MAX_BYTES): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => ''];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => sh_upload_error_message($error)];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return ['ok' => false, 'error' => 'Le fichier est trop volumineux (limite du serveur ou 6 Mo maximum).'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'Téléversement invalide.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'error' => "Le fichier n'est pas une image valide."];
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $mime = (string) ($info['mime'] ?? '');
    if (!isset($extensions[$mime])) {
        return ['ok' => false, 'error' => 'Format non accepté. Utilisez JPG, PNG ou WEBP.'];
    }

    $subdir = trim(str_replace(['\\', '..'], ['/', ''], $subdir), '/');
    if ($subdir === '') {
        $subdir = 'divers';
    }

    $relDir = $subdir . '/' . date('Y/m');
    $absDir = sh_upload_root() . '/' . $relDir;
    if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
        return ['ok' => false, 'error' => "Dossier d'upload inaccessible."];
    }

    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $abs = $absDir . '/' . $name;

    if (!@move_uploaded_file($tmp, $abs)) {
        return ['ok' => false, 'error' => "Enregistrement de l'image impossible."];
    }
    @chmod($abs, 0644);

    return ['ok' => true, 'path' => $relDir . '/' . $name];
}

/**
 * Chemin absolu d'un fichier téléversé, ou null si hors du dossier autorisé.
 */
function sh_upload_path(?string $relative): ?string
{
    $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
    if ($relative === '' || str_contains($relative, '..')) {
        return null;
    }

    $root = realpath(sh_upload_root());
    $abs = realpath(sh_upload_root() . '/' . $relative);
    if ($root === false || $abs === false) {
        return null;
    }

    $root = str_replace('\\', '/', $root);
    $abs = str_replace('\\', '/', $abs);
    if (!str_starts_with($abs, rtrim($root, '/') . '/')) {
        return null;
    }
    if (!is_file($abs)) {
        return null;
    }

    return $abs;
}

function sh_delete_upload(?string $relative): void
{
    $abs = sh_upload_path($relative);
    if ($abs !== null) {
        @unlink($abs);
    }
}

/**
 * Envoie un fichier téléversé au navigateur (après contrôle de session).
 */
function sh_serve_upload(?string $relative): never
{
    $abs = sh_upload_path($relative);
    if ($abs === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Image introuvable.');
    }

    $mime = @mime_content_type($abs) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    $size = filesize($abs);
    if ($size !== false) {
        header('Content-Length: ' . (string) $size);
    }
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline');
    readfile($abs);
    exit;
}
