<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('documents/index.php');
}
verify_csrf();

$id = int_param('id');
$doc = $id > 0 ? fetch_one('SELECT * FROM document_paiement WHERE id = ?', [$id]) : null;
if (!$doc) {
    flash('error', t('Document introuvable.'));
    redirect('documents/index.php');
}

q('DELETE FROM document_paiement WHERE id = ?', [$id]);
audit('SUPPRESSION', 'DOCUMENT', $id, type_label($doc['type']) . ' n° ' . $doc['numero'] . ' · ' . money($doc['montant']));
flash('success', t('Document supprimé.'));

redirect('documents/index.php');
