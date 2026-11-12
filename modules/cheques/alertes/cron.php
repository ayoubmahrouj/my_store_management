<?php
declare(strict_types=1);

/**
 * Planificateur des rappels.
 *
 * Utilisation :
 *   php alertes/cron.php
 *   ou, depuis le web, avec un en-tête HTTP (jamais dans l'URL) :
 *     X-Cron-Token: CRON_TOKEN
 *     ou Authorization: Bearer CRON_TOKEN
 *
 * À exécuter chaque minute (ou au moins une fois par heure) via le
 * Planificateur de tâches Windows ou une crontab Linux.
 */

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/i18n.php';

// Pas de session en CLI : évite les avertissements et n'écrit pas de cookie.
$_SESSION = [];

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');

    $token = (string) ($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
    $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($token === '' && str_starts_with($authorization, 'Bearer ')) {
        $token = substr($authorization, 7);
    }

    $tokenConfigured = CRON_TOKEN !== 'change-me-cron-token' && strlen(CRON_TOKEN) >= 32;
    if (!$tokenConfigured || $token === '' || !hash_equals(CRON_TOKEN, $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Jeton de cron invalide ou absent.']);
        exit;
    }
}

require_once __DIR__ . '/../lib/AlerteService.php';

$result = AlerteService::run_scheduled();

if ($isCli) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . ($result['message'] ?? '') . PHP_EOL;
    if (!empty($result['skipped'])) {
        echo 'Aucun envoi (non dû ou rappels désactivés).' . PHP_EOL;
    } else {
        echo sprintf(
            'Documents : %d · emails : %d · SMS : %d · échecs : %d%s%s',
            $result['documents'] ?? 0,
            $result['emails'] ?? 0,
            $result['sms'] ?? 0,
            $result['echecs'] ?? 0,
            !empty($result['simule']) ? ' · simulation' : '',
            PHP_EOL
        );
    }
    exit(empty($result['ok']) ? 1 : 0);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
