<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('parametres/index.php');
}
verify_csrf();

$nom = trim((string) param('nom'));
$adresse = trim((string) param('adresse'));
$telephone = trim((string) param('telephone'));
$email = trim((string) param('email'));

$errors = [];
if ($nom === '') {
    $errors[] = t('Le nom de la société est obligatoire.');
}
if ($nom !== '' && mb_strlen($nom) > 150) {
    $errors[] = t('Le nom de la société ne doit pas dépasser 150 caractères.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = t('Adresse email invalide.');
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('parametres/index.php');
}

q(
    'INSERT INTO parametres_societe (id, nom, adresse, telephone, email) VALUES (1, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE nom = VALUES(nom), adresse = VALUES(adresse), telephone = VALUES(telephone), email = VALUES(email)',
    [$nom, $adresse !== '' ? $adresse : null, $telephone !== '' ? $telephone : null, $email !== '' ? $email : null]
);

audit('MODIFICATION', 'SOCIETE', 1, 'Paramètres société mis à jour');
flash('success', t('Paramètres société enregistrés.'));
redirect('parametres/index.php');
