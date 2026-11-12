<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('carnets/index.php');
}
verify_csrf();

$id = int_param('id');
$item = $id > 0 ? fetch_one('SELECT * FROM carnet WHERE id = ?', [$id]) : null;
if (!$item) {
    flash('error', t('Carnet introuvable.'));
    redirect('carnets/index.php');
}

$nb = (int) scalar('SELECT COUNT(*) FROM document_paiement WHERE carnet_id = ?', [$id], 0);
if ($nb > 0) {
    flash('error', t('Impossible de supprimer : ce carnet contient') . ' ' . $nb . ' ' . t('document(s).'));
    redirect('carnets/index.php');
}

try {
    q('DELETE FROM carnet WHERE id = ?', [$id]);
    audit('SUPPRESSION', 'CARNET', $id, (string) $item['libelle']);
    flash('success', t('Carnet supprimé.'));
} catch (PDOException $e) {
    flash('error', t('Suppression impossible : ce carnet est référencé.'));
}

redirect('carnets/index.php');
