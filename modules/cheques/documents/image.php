<?php
declare(strict_types=1);

/**
 * Affichage protégé des images d'un chèque / effet.
 * URL : /modules/cheques/documents/image.php?id=12&side=front|back
 */

require __DIR__ . '/../bootstrap.php';
require_login();

$id = int_param('id');
$side = param('side') === 'back' ? 'back' : 'front';

if ($id <= 0) {
    http_response_code(404);
    exit('Image introuvable.');
}

$doc = fetch_one('SELECT image_path, image_back_path FROM cheques_documents WHERE id = ?', [$id]);
if (! $doc) {
    http_response_code(404);
    exit('Image introuvable.');
}

$path = $side === 'back' ? ($doc['image_back_path'] ?? null) : ($doc['image_path'] ?? null);
sh_serve_upload($path);
