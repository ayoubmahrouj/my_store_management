<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Nouvelle personne';
$active = 'personnes';

$id = int_param('id');
$item = null;
if ($id > 0) {
    $item = fetch_one('SELECT * FROM personne WHERE id = ?', [$id]);
    if (!$item) {
        flash('error', t('Personne introuvable.'));
        redirect('personnes/index.php');
    }
    $title = 'Modifier la personne';
}

$errors = form_errors();
clear_errors();
$old = $_SESSION['old'] ?? null;
clear_old();

$defaults = [
    'nom'       => $item['nom'] ?? '',
    'role'      => $item['role'] ?? 'PROPRIETAIRE_SOCIETE',
    'telephone' => $item['telephone'] ?? '',
    'email'     => $item['email'] ?? '',
    'adresse'   => $item['adresse'] ?? '',
];
$form = is_array($old) ? array_merge($defaults, $old) : $defaults;

$nbDocuments = $id > 0 ? (int) scalar('SELECT COUNT(*) FROM document_paiement WHERE personne_id = ?', [$id], 0) : 0;
$nbCarnets = $id > 0 ? (int) scalar('SELECT COUNT(*) FROM carnet WHERE personne_id = ?', [$id], 0) : 0;

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <a class="back-link" href="<?= h(url('personnes/index.php')) ?>">← <?= h(t('Retour à la liste')) ?></a>
        <h1 class="page-title"><?= h($id > 0 ? t('Modifier la personne') : t('Nouvelle personne')) ?></h1>
        <p class="page-sub"><?= h(t('Personne interne responsable du suivi des documents.')) ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert-error"><span class="alert-icon">!</span><?= h(t('Veuillez corriger les erreurs ci-dessous.')) ?></div>
<?php endif; ?>

<form method="post" action="<?= h(url('personnes/save.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="card" style="max-width:820px">
        <div class="form-section">
            <div class="form-section-title"><?= h(t('Identité')) ?></div>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="nom"><?= h(t('Nom')) ?> <span class="req">*</span></label>
                    <input id="nom" name="nom" type="text" class="input" maxlength="150" required value="<?= h($form['nom']) ?>">
                    <?php if (isset($errors['nom'])): ?><span class="error-text"><?= h($errors['nom']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="role"><?= h(t('Rôle')) ?> <span class="req">*</span></label>
                    <select id="role" name="role" class="select" required>
                        <option value="PROPRIETAIRE_SOCIETE"<?= $form['role'] === 'PROPRIETAIRE_SOCIETE' ? ' selected' : '' ?>><?= h(t('Propriétaire de la société')) ?></option>
                        <option value="GERANT"<?= $form['role'] === 'GERANT' ? ' selected' : '' ?>><?= h(t('Gérant')) ?></option>
                        <option value="EMPLOYE"<?= $form['role'] === 'EMPLOYE' ? ' selected' : '' ?>><?= h(t('Employé')) ?></option>
                    </select>
                    <span class="hint"><?= h(t('Le propriétaire est la personne à qui appartiennent les carnets.')) ?></span>
                </div>

                <div class="field">
                    <label for="telephone"><?= h(t('Téléphone')) ?></label>
                    <input id="telephone" name="telephone" type="text" class="input" maxlength="30" value="<?= h($form['telephone']) ?>">
                </div>

                <div class="field">
                    <label for="email"><?= h(t('Email')) ?></label>
                    <input id="email" name="email" type="email" class="input" maxlength="150" value="<?= h($form['email']) ?>">
                    <?php if (isset($errors['email'])): ?><span class="error-text"><?= h($errors['email']) ?></span><?php endif; ?>
                </div>

                <div class="field span-2">
                    <label for="adresse"><?= h(t('Adresse')) ?></label>
                    <input id="adresse" name="adresse" type="text" class="input" maxlength="255" value="<?= h($form['adresse']) ?>">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-ghost" href="<?= h(url('personnes/index.php')) ?>"><?= h(t('Annuler')) ?></a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
                <?= h($id > 0 ? t('Enregistrer les modifications') : t('Créer la personne')) ?>
            </button>
        </div>
    </div>
</form>

<?php if ($id > 0): ?>
    <div class="card mt-16" style="max-width:820px">
        <div class="card-header">
            <div class="card-title"><?= h(t('Activité')) ?></div>
        </div>
        <div class="card-body">
            <div class="mini-grid">
                <div class="mini-stat">
                    <div class="mini-label"><?= h(t('Documents')) ?></div>
                    <div class="mini-value"><?= $nbDocuments ?></div>
                </div>
                <div class="mini-stat">
                    <div class="mini-label"><?= h(t('Carnets')) ?></div>
                    <div class="mini-value"><?= $nbCarnets ?></div>
                </div>
                <div class="mini-stat">
                    <div class="mini-label"><?= h(t('Montant total')) ?></div>
                    <div class="mini-value"><?= h(money((float) scalar('SELECT COALESCE(SUM(montant), 0) FROM document_paiement WHERE personne_id = ?', [$id], 0))) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
