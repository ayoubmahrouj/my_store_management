<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('personnes/index.php');
}
verify_csrf();

$id = int_param('id');
$item = $id > 0 ? fetch_one('SELECT * FROM personne WHERE id = ?', [$id]) : null;
if (!$item) {
    flash('error', t('Personne introuvable.'));
    redirect('personnes/index.php');
}

$nbDocs = (int) scalar('SELECT COUNT(*) FROM document_paiement WHERE personne_id = ?', [$id], 0);
$nbCarnets = (int) scalar('SELECT COUNT(*) FROM carnet WHERE personne_id = ?', [$id], 0);
if ($nbDocs > 0 || $nbCarnets > 0) {
    flash('error', t('Impossible de supprimer : cette personne est référencée par des documents ou des carnets.'));
    redirect('personnes/index.php');
}

try {
    q("DELETE FROM clients WHERE id = ? AND type = 'PERSONNE'", [$id]);
    audit('SUPPRESSION', 'PERSONNE', $id, (string) $item['nom']);
    flash('success', t('Personne supprimée.'));
} catch (PDOException $e) {
    flash('error', t('Suppression impossible : cette personne est référencée.'));
}

redirect('personnes/index.php');
