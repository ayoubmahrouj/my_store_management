<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();
require_once __DIR__ . '/../lib/AlerteService.php';

if (!is_post()) {
    redirect('alertes/index.php');
}
verify_csrf();

$saved = AlerteService::save_config($_POST);
if (!$saved['ok']) {
    flash('error', (string) $saved['error']);
    redirect('alertes/index.php');
}

$result = AlerteService::run_now();
$message = $result['message'] . ' ' . $result['emails'] . ' email(s), ' . $result['sms'] . ' SMS.';
if ($result['echecs'] > 0) {
    flash('error', $message . ' ' . $result['echecs'] . ' ' . t('échec(s).'));
} else {
    flash('success', $message . ($result['simule'] ? ' (' . t('simulation') . ')' : ''));
}

redirect('alertes/index.php');
