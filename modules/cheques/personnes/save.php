<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('personnes/index.php');
}
verify_csrf();

$id = int_param('id');
$nom = trim((string) param('nom'));
$role = (string) param('role', 'PROPRIETAIRE_SOCIETE');
$telephone = trim((string) param('telephone'));
$email = trim((string) param('email'));
$adresse = trim((string) param('adresse'));

$errors = [];
if ($nom === '') {
    $errors['nom'] = t('Le nom est obligatoire.');
} elseif (mb_strlen($nom) > 150) {
    $errors['nom'] = t('Le nom ne doit pas dépasser 150 caractères.');
}
if (!in_array($role, ['PROPRIETAIRE_SOCIETE', 'GERANT', 'EMPLOYE'], true)) {
    $role = 'PROPRIETAIRE_SOCIETE';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = t('Adresse e-mail invalide.');
}
if (mb_strlen($telephone) > 30) {
    $errors['telephone'] = t('Le téléphone ne doit pas dépasser 30 caractères.');
}
if (mb_strlen($adresse) > 255) {
    $errors['adresse'] = t('L’adresse ne doit pas dépasser 255 caractères.');
}

if ($errors) {
    set_errors($errors);
    set_old(compact('nom', 'role', 'telephone', 'email', 'adresse'));
    flash('error', t('Veuillez corriger les erreurs du formulaire.'));
    redirect('personnes/form.php' . ($id > 0 ? '?id=' . $id : ''));
}

$repo = new \Gos\Domain\Repositories\TiersRepository();
$responsable = new \Gos\Domain\Entities\Responsable(
    $id > 0 ? $id : null,
    $nom,
    \Gos\Domain\Entities\Personne::NATURE_PERSONNE,
    $telephone !== '' ? $telephone : null,
    $email !== '' ? $email : null,
    $adresse !== '' ? $adresse : null,
    null,
    null,
    true,
    $role,
);

if ($id > 0) {
    if (!$repo->exists($id, 'personne')) {
        flash('error', t('Personne introuvable.'));
        redirect('personnes/index.php');
    }
    $repo->save($responsable);
    audit('MODIFICATION', 'PERSONNE', $id, $nom);
    flash('success', t('Personne modifiée.'));
} else {
    $id = $repo->save($responsable);
    audit('CREATION', 'PERSONNE', $id, $nom);
    flash('success', t('Personne créée.'));
}

redirect('personnes/index.php');
