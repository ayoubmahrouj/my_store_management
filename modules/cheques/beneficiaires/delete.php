<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('beneficiaires/index.php');
}
verify_csrf();

$id = int_param('id');
$item = $id > 0 ? fetch_one('SELECT * FROM beneficiaire WHERE id = ?', [$id]) : null;
if (!$item) {
    flash('error', t('Bénéficiaire introuvable.'));
    redirect('beneficiaires/index.php');
}

$nb = (int) scalar('SELECT COUNT(*) FROM document_paiement WHERE beneficiaire_id = ?', [$id], 0);
if ($nb > 0) {
    flash('error', t('Impossible de supprimer : ce bénéficiaire est utilisé par') . ' ' . $nb . ' ' . t('document(s).'));
    redirect('beneficiaires/index.php');
}

try {
    q('DELETE FROM beneficiaire WHERE id = ?', [$id]);
    audit('SUPPRESSION', 'BENEFICIAIRE', $id, (string) $item['nom']);
    flash('success', t('Bénéficiaire supprimé.'));
} catch (PDOException $e) {
    flash('error', t('Suppression impossible : ce bénéficiaire est référencé.'));
}

redirect('beneficiaires/index.php');
