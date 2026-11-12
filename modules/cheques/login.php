<?php
declare(strict_types=1);

/*
| La connexion est centralisée : une seule page pour tous les modules.
*/
require __DIR__ . '/bootstrap.php';

$next = isset($_GET['next']) ? (string) $_GET['next'] : '';
$target = sh_url('/login.php');

if (core_user() !== null) {
    sh_redirect(sh_url('/modules/cheques/index.php'));
}

if ($next !== '') {
    $target .= '?next=' . rawurlencode($next);
}

sh_redirect($target);
