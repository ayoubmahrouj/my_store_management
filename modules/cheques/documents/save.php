<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

if (!is_post()) {
    redirect('documents/index.php');
}
verify_csrf();

$id = int_param('id');
$type = (string) param('type');
$carnetId = int_param('carnet_id');
$numero = trim((string) param('numero'));
$beneficiaireId = int_param('beneficiaire_id');
$personneId = int_param('personne_id');
$montant = float_param('montant');
$dateEmission = (string) param('date_emission');
$dateEcheance = (string) param('date_echeance');
$regle = param('regle') !== '' ? 1 : 0;
$rejete = param('rejete') !== '' ? 1 : 0;
$dateReglement = trim((string) param('date_reglement'));
$remarque = trim((string) param('remarque'));
$banque = trim((string) param('banque'));

$errors = [];
$carnet = null;
$existing = $id > 0 ? fetch_one('SELECT * FROM cheques_documents WHERE id = ?', [$id]) : null;

if (!in_array($type, ['CHEQUE', 'EFFET'], true)) {
    $errors['type'] = t('Type de document invalide.');
}

if ($carnetId > 0) {
    $carnet = fetch_one('SELECT * FROM carnet WHERE id = ?', [$carnetId]);
}
if (!$carnet) {
    $errors['carnet_id'] = t('Veuillez sélectionner un carnet.');
}

if ($numero === '') {
    $errors['numero'] = t('Le numéro est obligatoire.');
} elseif (mb_strlen($numero) > 50) {
    $errors['numero'] = t('Le numéro ne doit pas dépasser 50 caractères.');
} elseif ($carnet) {
    $duplicate = fetch_one(
        'SELECT id FROM document_paiement WHERE carnet_id = ? AND numero = ? AND id <> ? LIMIT 1',
        [$carnetId, $numero, $id]
    );
    if ($duplicate) {
        $errors['numero'] = t('Ce numéro existe déjà dans ce carnet.');
    }
}

if ($beneficiaireId <= 0 || !fetch_one('SELECT id FROM beneficiaire WHERE id = ?', [$beneficiaireId])) {
    $errors['beneficiaire_id'] = t('Veuillez sélectionner un bénéficiaire valide.');
}

if ($personneId <= 0 || !fetch_one('SELECT id FROM personne WHERE id = ?', [$personneId])) {
    $errors['personne_id'] = t('Veuillez sélectionner une personne responsable.');
}

if ($montant === null || $montant <= 0) {
    $errors['montant'] = t('Saisissez un montant supérieur à 0.');
}

if (!isValidDate($dateEmission)) {
    $errors['date_emission'] = t("Saisissez une date d'émission valide.");
}
if (!isValidDate($dateEcheance)) {
    $errors['date_echeance'] = t("Saisissez une date d'échéance valide.");
}
if (isValidDate($dateEmission) && isValidDate($dateEcheance) && $dateEcheance < $dateEmission) {
    $errors['date_echeance'] = t("L'échéance doit être après l'émission.");
}
if ($dateReglement !== '' && !isValidDate($dateReglement)) {
    $errors['date_reglement'] = t('Saisissez une date de règlement valide.');
}

if ($regle && $rejete) {
    $errors['rejete'] = t('Un document ne peut pas être à la fois réglé et rejeté.');
}
if (mb_strlen($banque) > 100) {
    $errors['banque'] = t('La banque ne doit pas dépasser 100 caractères.');
}
if (mb_strlen($remarque) > 500) {
    $errors['remarque'] = t('La remarque ne doit pas dépasser 500 caractères.');
}

if ($rejete) {
    $regle = 0;
}
if ($regle) {
    if ($dateReglement === '' || !isValidDate($dateReglement)) {
        $dateReglement = today_iso();
    }
} else {
    $dateReglement = '';
}

if ($errors) {
    set_errors($errors);
    set_old([
        'type'            => $type,
        'carnet_id'       => (string) $carnetId,
        'numero'          => $numero,
        'beneficiaire_id' => (string) $beneficiaireId,
        'personne_id'     => (string) $personneId,
        'montant'         => $montant !== null ? (string) $montant : '',
        'banque'          => $banque,
        'date_emission'   => $dateEmission,
        'date_echeance'   => $dateEcheance,
        'regle'           => $regle ? '1' : '0',
        'rejete'          => $rejete ? '1' : '0',
        'date_reglement'  => $dateReglement,
        'remarque'        => $remarque,
    ]);
    flash('error', t('Veuillez corriger les erreurs du formulaire.'));
    redirect('documents/form.php' . ($id > 0 ? '?id=' . $id : ''));
}

$carnetLibelle = $carnet['libelle'] ?? null;

/* -------------------------------------------------------------------------
| Images recto / verso
|-------------------------------------------------------------------------*/

$imagePath = $existing['image_path'] ?? null;
$imageBackPath = $existing['image_back_path'] ?? null;
$newFiles = [];
$uploadErrors = [];

if (!empty($_FILES['image']['name'])) {
    $upload = sh_store_image($_FILES['image'], 'cheques');
    if ($upload['ok']) {
        $newFiles[] = $imagePath = $upload['path'];
    } elseif (($upload['error'] ?? '') !== '') {
        $uploadErrors['image'] = $upload['error'];
    }
} elseif (param('remove_image') !== '' && $imagePath !== null) {
    sh_delete_upload($imagePath);
    $imagePath = null;
}

if (!empty($_FILES['image_back']['name'])) {
    $upload = sh_store_image($_FILES['image_back'], 'cheques');
    if ($upload['ok']) {
        $newFiles[] = $imageBackPath = $upload['path'];
    } elseif (($upload['error'] ?? '') !== '') {
        $uploadErrors['image_back'] = $upload['error'];
    }
} elseif (param('remove_image_back') !== '' && $imageBackPath !== null) {
    sh_delete_upload($imageBackPath);
    $imageBackPath = null;
}

if ($uploadErrors) {
    foreach ($newFiles as $file) {
        sh_delete_upload($file);
    }
    set_errors($uploadErrors);
    set_old([
        'type'            => $type,
        'carnet_id'       => (string) $carnetId,
        'numero'          => $numero,
        'beneficiaire_id' => (string) $beneficiaireId,
        'personne_id'     => (string) $personneId,
        'montant'         => $montant !== null ? (string) $montant : '',
        'banque'          => $banque,
        'date_emission'   => $dateEmission,
        'date_echeance'   => $dateEcheance,
        'regle'           => $regle ? '1' : '0',
        'rejete'          => $rejete ? '1' : '0',
        'date_reglement'  => $dateReglement,
        'remarque'        => $remarque,
    ]);
    flash('error', t('Images non enregistrées. Vérifiez le format et la taille.'));
    redirect('documents/form.php' . ($id > 0 ? '?id=' . $id : ''));
}

try {
    if ($id > 0) {
        q(
            'UPDATE document_paiement SET
                banque = ?, date_echeance = ?, date_emission = ?, montant = ?, numero = ?, remarque = ?,
                type = ?, updated_at = NOW(), beneficiaire_id = ?, personne_id = ?, carnet = ?,
                date_reglement = ?, regle = ?, carnet_id = ?, rejete = ?,
                image_path = ?, image_back_path = ?
             WHERE id = ?',
            [
                $banque !== '' ? $banque : null,
                $dateEcheance,
                $dateEmission,
                $montant,
                $numero,
                $remarque !== '' ? $remarque : null,
                $type,
                $beneficiaireId,
                $personneId,
                $carnetLibelle,
                $dateReglement !== '' ? $dateReglement : null,
                $regle,
                $carnetId,
                $rejete,
                $imagePath,
                $imageBackPath,
                $id,
            ]
        );
        if (($existing['image_path'] ?? null) && $existing['image_path'] !== $imagePath) {
            sh_delete_upload($existing['image_path']);
        }
        if (($existing['image_back_path'] ?? null) && $existing['image_back_path'] !== $imageBackPath) {
            sh_delete_upload($existing['image_back_path']);
        }
        audit('MODIFICATION', 'DOCUMENT', $id, type_label($type) . ' n° ' . $numero . ' · ' . money($montant));
        flash('success', t('Document modifié avec succès.'));
    } else {
        q(
            'INSERT INTO document_paiement
                (banque, created_at, date_echeance, date_emission, montant, numero, remarque, type, updated_at,
                 beneficiaire_id, personne_id, carnet, date_reglement, regle, carnet_id, rejete,
                 image_path, image_back_path)
             VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $banque !== '' ? $banque : null,
                $dateEcheance,
                $dateEmission,
                $montant,
                $numero,
                $remarque !== '' ? $remarque : null,
                $type,
                $beneficiaireId,
                $personneId,
                $carnetLibelle,
                $dateReglement !== '' ? $dateReglement : null,
                $regle,
                $carnetId,
                $rejete,
                $imagePath,
                $imageBackPath,
            ]
        );
        $id = last_insert_id();
        audit('CREATION', 'DOCUMENT', $id, type_label($type) . ' n° ' . $numero . ' · ' . money($montant));
        flash('success', t('Document créé avec succès.'));
    }
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) === 1062) {
        foreach ($newFiles as $file) {
            sh_delete_upload($file);
        }
        set_errors(['numero' => t('Ce numéro existe déjà dans ce carnet.')]);
        flash('error', t('Ce numéro existe déjà dans ce carnet.'));
        redirect('documents/form.php' . ($id > 0 ? '?id=' . $id : ''));
    }
    throw $e;
}

redirect($id > 0 ? 'documents/index.php' : 'documents/index.php');
