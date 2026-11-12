<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('parametres/index.php');
}
verify_csrf();

$current = (string) param('current_password');
$new = (string) param('new_password');
$confirm = (string) param('confirm_password');

if ($current === '' || $new === '' || $confirm === '') {
    flash('error', t('Tous les champs du mot de passe sont obligatoires.'));
    redirect('parametres/index.php');
}

if ($new !== $confirm) {
    flash('error', t('Les deux mots de passe ne correspondent pas.'));
    redirect('parametres/index.php');
}

$error = change_password((int) (current_user()['id'] ?? 0), $current, $new);
if ($error !== null) {
    flash('error', $error);
    redirect('parametres/index.php');
}

flash('success', t('Mot de passe modifié avec succès.'));
redirect('parametres/index.php');
