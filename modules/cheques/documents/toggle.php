<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('documents/index.php');
}
verify_csrf();

$id = int_param('id');
$field = (string) param('field');
$doc = $id > 0 ? fetch_one('SELECT * FROM document_paiement WHERE id = ?', [$id]) : null;
if (!$doc) {
    flash('error', t('Document introuvable.'));
    redirect('documents/index.php');
}

$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$refererPath = parse_url($referer, PHP_URL_PATH);
$refererQuery = parse_url($referer, PHP_URL_QUERY);
$redirectTo = is_string($refererPath)
    ? local_return_path($refererPath . (is_string($refererQuery) && $refererQuery !== '' ? '?' . $refererQuery : ''))
    : '';
if ($redirectTo === '') {
    $redirectTo = url('documents/index.php');
}

if ($field === 'regle') {
    $newValue = bit_true($doc['regle']) ? 0 : 1;
    if ($newValue === 1 && bit_true($doc['rejete'])) {
        flash('error', t('Annulez le rejet avant de marquer ce document réglé.'));
        header('Location: ' . $redirectTo);
        exit;
    }
    q(
        'UPDATE document_paiement SET regle = ?, date_reglement = ?, updated_at = NOW() WHERE id = ?',
        [$newValue, $newValue ? today_iso() : null, $id]
    );
    audit('MODIFICATION', 'DOCUMENT', $id, $newValue ? 'Marqué réglé' : 'Marqué non réglé');
    flash('success', $newValue ? t('Document marqué comme réglé.') : t('Document marqué comme non réglé.'));
} elseif ($field === 'rejete') {
    $newValue = bit_true($doc['rejete']) ? 0 : 1;
    if ($newValue === 1) {
        q('UPDATE document_paiement SET rejete = 1, regle = 0, date_reglement = NULL, updated_at = NOW() WHERE id = ?', [$id]);
        audit('MODIFICATION', 'DOCUMENT', $id, 'Marqué rejeté');
        flash('success', t('Document marqué comme rejeté.'));
    } else {
        q('UPDATE document_paiement SET rejete = 0, updated_at = NOW() WHERE id = ?', [$id]);
        audit('MODIFICATION', 'DOCUMENT', $id, 'Rejet annulé');
        flash('success', t('Rejet annulé.'));
    }
} else {
    flash('error', t('Action inconnue.'));
}

header('Location: ' . $redirectTo);
exit;
