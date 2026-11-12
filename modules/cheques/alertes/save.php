<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();
require_once __DIR__ . '/../lib/AlerteService.php';

if (!is_post()) {
    redirect('alertes/index.php');
}
verify_csrf();

$result = AlerteService::save_config($_POST);
if (!$result['ok']) {
    flash('error', (string) $result['error']);
} else {
    flash('success', t('Configuration des alertes enregistrée.'));
}

redirect('alertes/index.php');
