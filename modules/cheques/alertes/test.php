<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();
require_once __DIR__ . '/../lib/AlerteService.php';

if (!is_post()) {
    redirect('alertes/index.php');
}
verify_csrf();

// On enregistre d'abord les champs affichés, puis on teste la configuration.
$saved = AlerteService::save_config($_POST);
if (!$saved['ok']) {
    flash('error', (string) $saved['error']);
    redirect('alertes/index.php');
}

$result = AlerteService::send_test();
$counts = $result['emails'] . ' email(s), ' . $result['sms'] . ' SMS';

if ($result['ok']) {
    $message = $result['simule']
        ? t('Test traité en mode simulation (aucun fournisseur configuré).') . ' ' . $counts
        : t('Test envoyé') . ' : ' . $counts . '.';
    flash('success', $message);
} else {
    flash('error', (string) $result['error'] . ' ' . $counts);
}

redirect('alertes/index.php');
