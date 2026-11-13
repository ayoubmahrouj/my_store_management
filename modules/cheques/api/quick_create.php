<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_api_login();

if (!is_post()) {
    json_response(['ok' => false, 'error' => t('Méthode non autorisée.')], 405);
}
verify_csrf();

$type = (string) param('type');

function clean(?string $value, int $max): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    return mb_substr($value, 0, $max);
}

try {
    if ($type === 'personne') {
        $nom = trim((string) param('nom'));
        if ($nom === '') {
            json_response(['ok' => false, 'error' => t('Le nom est obligatoire.')], 422);
        }
        $role = (string) param('role', 'EMPLOYE');
        if (!in_array($role, ['PROPRIETAIRE_SOCIETE', 'GERANT', 'EMPLOYE'], true)) {
            $role = 'EMPLOYE';
        }
        $email = clean(param('email'), 150);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['ok' => false, 'error' => t('Adresse e-mail invalide.')], 422);
        }
        $responsable = (new \Gos\Domain\Repositories\TiersRepository())->quickCreateResponsable(
            mb_substr($nom, 0, 160),
            $role,
            clean(param('telephone'), 30),
            $email,
            clean(param('adresse'), 255),
        );
        $id = $responsable->id();
        audit('CREATION', 'PERSONNE', $id, $nom);
        json_response([
            'ok'      => true,
            'type'    => 'personne',
            'message' => t('Personne créée.'),
            'item'    => ['id' => $id, 'label' => $nom, 'role' => $role],
        ]);
    }

    if ($type === 'beneficiaire') {
        $nom = trim((string) param('nom'));
        if ($nom === '') {
            json_response(['ok' => false, 'error' => t('Le nom est obligatoire.')], 422);
        }
        $btype = (string) param('nature', 'PERSONNE');
        if (!in_array($btype, ['SOCIETE', 'PERSONNE'], true)) {
            $btype = 'PERSONNE';
        }
        $email = clean(param('email'), 150);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['ok' => false, 'error' => t('Adresse e-mail invalide.')], 422);
        }
        $beneficiaire = (new \Gos\Domain\Repositories\TiersRepository())->quickCreateBeneficiaire(
            mb_substr($nom, 0, 160),
            $btype,
            clean(param('ice'), 20),
            clean(param('telephone'), 30),
            $email,
            clean(param('adresse'), 255),
        );
        $id = $beneficiaire->id();
        audit('CREATION', 'BENEFICIAIRE', $id, $nom);
        json_response([
            'ok'      => true,
            'type'    => 'beneficiaire',
            'message' => t('Bénéficiaire créé.'),
            'item'    => ['id' => $id, 'label' => $nom, 'type' => $btype],
        ]);
    }

    if ($type === 'carnet') {
        $libelle = trim((string) param('libelle'));
        if ($libelle === '') {
            json_response(['ok' => false, 'error' => t('La référence est obligatoire.')], 422);
        }
        $ctype = (string) param('type', 'CHEQUE');
        if (!in_array($ctype, ['CHEQUE', 'EFFET'], true)) {
            $ctype = 'CHEQUE';
        }
        $codeRaw = param('code', '');
        $code = $codeRaw !== '' && is_numeric($codeRaw) ? (int) $codeRaw : null;
        if ($code !== null && ($code < 0 || $code > 999999)) {
            $code = null;
        }
        $personneId = int_param('personneId');
        if ($personneId > 0 && !fetch_one('SELECT id FROM personne WHERE id = ?', [$personneId])) {
            $personneId = 0;
        }
        $banque = clean(param('banque'), 100);
        q(
            'INSERT INTO carnet (libelle, type, code, banque, personne_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
            [mb_substr($libelle, 0, 100), $ctype, $code, $banque, $personneId > 0 ? $personneId : null]
        );
        $id = last_insert_id();
        audit('CREATION', 'CARNET', $id, $libelle);
        $label = $libelle . ($code !== null ? ' · ' . $code : '');
        json_response([
            'ok'      => true,
            'type'    => 'carnet',
            'message' => t('Carnet créé.'),
            'item'    => [
                'id'         => $id,
                'label'      => $label,
                'banque'     => $banque ?? '',
                'personneId' => $personneId > 0 ? $personneId : '',
                'type'       => $ctype,
            ],
        ]);
    }
} catch (PDOException $e) {
    json_response(['ok' => false, 'error' => t('Enregistrement impossible. Vérifiez les informations saisies.')], 500);
}

json_response(['ok' => false, 'error' => t('Type de création inconnu.')], 422);
