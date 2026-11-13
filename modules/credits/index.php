<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Point d'entrée — Gestion Crédits (PHP natif)
|--------------------------------------------------------------------------
| Toutes les pages passent par ici : /index.php?p=clients
|--------------------------------------------------------------------------
*/

require __DIR__ . '/inc/config.php';
require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/pdf.php';
require __DIR__ . '/inc/layout.php';

// Changement de langue (FR / AR) — synchronise aussi le module Chèques.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'ar'], true)) {
    sh_set_locale((string) $_GET['lang']);
    $target = preg_replace('/[^a-z_]/', '', (string) ($_GET['p'] ?? 'pos')) ?: 'pos';
    unset($_GET['lang'], $_GET['p']);
    redirect($target, $_GET);
}

// Déconnexion (compte unique du socle)
if (($_GET['p'] ?? '') === 'logout') {
    core_logout();
    sh_redirect(sh_url('/login.php'));
}

$page = preg_replace('/[^a-z_]/', '', (string) ($_GET['p'] ?? 'pos'));
$page = $page !== '' ? $page : 'pos';

// La connexion se fait désormais sur la page unique de l'application.
if ($page === 'login') {
    sh_redirect(sh_url('/login.php'));
}

// Session + droit d'accès au module « credits ».
core_require_login('credits');

$file = __DIR__ . '/pages/' . $page . '.php';

if (! is_file($file)) {
    http_response_code(404);
    $page = 'dashboard';
    $file = __DIR__ . '/pages/dashboard.php';
}

require $file;
