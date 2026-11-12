<?php
declare(strict_types=1);

/**
 * Affichage protégé de l'image d'un bon société.
 * URL : /modules/credits/bon_image.php?id=12
 */

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Image introuvable.');
}

$bon = one('SELECT image_path FROM credit_bons WHERE id = ?', [$id]);
if (! $bon || empty($bon['image_path'])) {
    http_response_code(404);
    exit('Image introuvable.');
}

sh_serve_upload((string) $bon['image_path']);
