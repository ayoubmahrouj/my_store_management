<?php
declare(strict_types=1);

/**
 * Bascule de langue globale (FR / AR).
 *
 * Exemple : /lang.php?to=ar&next=%2Fmodules%2Fstock%2Fdashboard
 * Le sélecteur de la coquille commune pointe ici ; la langue choisie est
 * conservée en session pour le socle et les trois modules.
 */

require __DIR__ . '/shared/bootstrap.php';

$to = (($_GET['to'] ?? $_GET['lang'] ?? '') === 'ar') ? 'ar' : 'fr';
sh_set_locale($to);

// Redirection uniquement vers une URL interne (pas d'open redirect).
$next = (string) ($_GET['next'] ?? '');
$isLocal = $next !== ''
    && str_starts_with($next, '/')
    && !str_starts_with($next, '//')
    && !str_contains($next, '\\')
    && !str_contains($next, "\n")
    && !str_contains($next, "\r");

if (! $isLocal) {
    $next = sh_url('/index.php');
}

sh_redirect($next);
