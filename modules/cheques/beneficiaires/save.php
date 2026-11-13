<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('beneficiaires/index.php');
}
verify_csrf();

$id = int_param('id');
$nom = trim((string) param('nom'));
$type = (string) param('type', 'SOCIETE');
$ice = trim((string) param('ice'));
$telephone = trim((string) param('telephone'));
$email = trim((string) param('email'));
$adresse = trim((string) param('adresse'));

$errors = [];
if ($nom === '') {
    $errors['nom'] = t('Le nom est obligatoire.');
} elseif (mb_strlen($nom) > 150) {
    $errors['nom'] = t('Le nom ne doit pas dépasser 150 caractères.');
}
if (!in_array($type, ['SOCIETE', 'PERSONNE'], true)) {
    $type = 'SOCIETE';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = t('Adresse e-mail invalide.');
}
if (mb_strlen($ice) > 20) {
    $errors['ice'] = t('L’ICE ne doit pas dépasser 20 caractères.');
}
if (mb_strlen($telephone) > 30) {
    $errors['telephone'] = t('Le téléphone ne doit pas dépasser 30 caractères.');
}
if (mb_strlen($adresse) > 255) {
    $errors['adresse'] = t('L’adresse ne doit pas dépasser 255 caractères.');
}

if ($errors) {
    set_errors($errors);
    set_old(compact('nom', 'type', 'ice', 'telephone', 'email', 'adresse'));
    flash('error', t('Veuillez corriger les erreurs du formulaire.'));
    redirect('beneficiaires/form.php' . ($id > 0 ? '?id=' . $id : ''));
}

$data = [
    $nom,
    $type,
    $ice !== '' ? $ice : null,
    $telephone !== '' ? $telephone : null,
    $email !== '' ? $email : null,
    $adresse !== '' ? $adresse : null,
];

if ($id > 0) {
    $exists = fetch_one("SELECT id FROM clients WHERE id = ? AND type = 'CLIENT'", [$id]);
    if (!$exists) {
        flash('error', t('Bénéficiaire introuvable.'));
        redirect('beneficiaires/index.php');
    }
    q(
        "UPDATE clients SET name = ?, nature = ?, ice = ?, phone = ?, email = ?, address = ?
         WHERE id = ? AND type = 'CLIENT'",
        array_merge($data, [$id])
    );
    audit('MODIFICATION', 'BENEFICIAIRE', $id, $nom);
    flash('success', t('Bénéficiaire modifié.'));
} else {
    q(
        "INSERT INTO clients (name, type, nature, ice, phone, email, address, created_at)
         VALUES (?, 'CLIENT', ?, ?, ?, ?, ?, NOW())",
        $data
    );
    $id = last_insert_id();
    audit('CREATION', 'BENEFICIAIRE', $id, $nom);
    flash('success', t('Bénéficiaire créé.'));
}

redirect('beneficiaires/index.php');
