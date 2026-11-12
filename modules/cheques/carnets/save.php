<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('carnets/index.php');
}
verify_csrf();

$id = int_param('id');
$type = (string) param('type', 'CHEQUE');
$libelle = trim((string) param('libelle'));
$codeRaw = trim((string) param('code'));
$banque = trim((string) param('banque'));
$personneId = int_param('personne_id');

$errors = [];

if (!in_array($type, ['CHEQUE', 'EFFET'], true)) {
    $errors['type'] = t('Type de carnet invalide.');
}

if ($libelle === '') {
    $errors['libelle'] = t('La référence est obligatoire.');
} elseif (mb_strlen($libelle) > 100) {
    $errors['libelle'] = t('La référence ne doit pas dépasser 100 caractères.');
}

$code = null;
if ($codeRaw !== '') {
    if (!ctype_digit($codeRaw)) {
        $errors['code'] = t('Le code doit être un nombre entier.');
    } else {
        $code = (int) $codeRaw;
        if ($code < 0 || $code > 999999) {
            $errors['code'] = t('Le code doit être compris entre 0 et 999999.');
        }
    }
}

if (mb_strlen($banque) > 100) {
    $errors['banque'] = t('La banque ne doit pas dépasser 100 caractères.');
}

if ($personneId <= 0 || !fetch_one('SELECT id FROM personne WHERE id = ?', [$personneId])) {
    $errors['personne_id'] = t('Veuillez sélectionner un propriétaire valide.');
}

if ($errors) {
    set_errors($errors);
    set_old([
        'type'        => $type,
        'libelle'     => $libelle,
        'code'        => $codeRaw,
        'banque'      => $banque,
        'personne_id' => (string) $personneId,
    ]);
    flash('error', t('Veuillez corriger les erreurs du formulaire.'));
    redirect('carnets/form.php' . ($id > 0 ? '?id=' . $id : ''));
}

$data = [
    $libelle,
    $type,
    $code,
    $banque !== '' ? $banque : null,
    $personneId,
];

if ($id > 0) {
    if (!fetch_one('SELECT id FROM carnet WHERE id = ?', [$id])) {
        flash('error', t('Carnet introuvable.'));
        redirect('carnets/index.php');
    }
    q(
        'UPDATE carnet SET libelle = ?, type = ?, code = ?, banque = ?, personne_id = ?, updated_at = NOW() WHERE id = ?',
        array_merge($data, [$id])
    );
    audit('MODIFICATION', 'CARNET', $id, $libelle);
    flash('success', t('Carnet modifié.'));
} else {
    q(
        'INSERT INTO carnet (libelle, type, code, banque, personne_id, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
        $data
    );
    $id = last_insert_id();
    audit('CREATION', 'CARNET', $id, $libelle);
    flash('success', t('Carnet créé.'));
}

redirect('carnets/index.php');
