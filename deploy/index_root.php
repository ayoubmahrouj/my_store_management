<?php
declare(strict_types=1);

/**
 * Point d'entrée du site — racine.
 *
 * L'application se trouve dans le sous-dossier « gestion_unifiee/ ».
 * Ce fichier y redirige en conservant les paramètres d'URL.
 *
 * À placer à la racine du site (public_html, htdocs, www) lorsque
 * l'application n'est pas déployée à la racine du domaine.
 *
 * PHP uniquement : aucune dépendance, aucun framework.
 */

$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
$suffix = $query !== '' ? '?' . $query : '';

// Cas normal : l'application est dans ./gestion_unifiee/.
if (is_file(__DIR__ . '/gestion_unifiee/login.php')) {
    header('Location: gestion_unifiee/login.php' . $suffix, true, 302);
    exit;
}

// Cas où ce fichier aurait été copié dans le dossier de l'application.
if (is_file(__DIR__ . '/login.php')) {
    header('Location: login.php' . $suffix, true, 302);
    exit;
}

// Application absente : page d'aide, sans dépendance externe.
http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestion Our Stock — installation</title>
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
        background: #0b1220; color: #e6edf7;
        font: 15px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    .card {
        width: min(560px, 100%); padding: 26px 28px;
        background: #121b2d; border: 1px solid #23304a; border-radius: 16px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, .45);
    }
    h1 { margin: 0 0 6px; font-size: 20px; }
    p.lead { margin: 0 0 18px; color: #93a4bf; font-size: 13.5px; }
    ol { margin: 0; padding-left: 20px; }
    li { margin-bottom: 10px; }
    code {
        padding: 1px 6px; background: #0b1220; border: 1px solid #23304a;
        border-radius: 6px; font-size: 12.5px;
    }
    .hint {
        margin-top: 18px; padding-top: 14px; border-top: 1px solid #23304a;
        color: #93a4bf; font-size: 12.5px;
    }
</style>
</head>
<body>
<div class="card">
    <h1>Application non trouvée</h1>
    <p class="lead">Le dossier <code>gestion_unifiee/</code> est absent à côté de ce fichier.</p>
    <ol>
        <li>Copier le dossier <code>gestion_unifiee/</code> à côté de <code>index.php</code>.</li>
        <li>Créer la base : importer <code>database/create_database.sql</code> dans phpMyAdmin.</li>
        <li>Renseigner <code>shared/config.local.php</code> avec les identifiants MySQL.</li>
    </ol>
    <p class="hint">
        Compte par défaut : <code>admin</code> / <code>Admin@2026</code> —
        à changer dès la première connexion.
    </p>
</div>
</body>
</html>
