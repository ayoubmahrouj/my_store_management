<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Nouveau bénéficiaire';
$active = 'beneficiaires';

$id = int_param('id');
$item = null;
if ($id > 0) {
    $item = fetch_one('SELECT * FROM beneficiaire WHERE id = ?', [$id]);
    if (!$item) {
        flash('error', t('Bénéficiaire introuvable.'));
        redirect('beneficiaires/index.php');
    }
    $title = 'Modifier le bénéficiaire';
}

$errors = form_errors();
clear_errors();
$old = $_SESSION['old'] ?? null;
clear_old();

$defaults = [
    'nom'       => $item['nom'] ?? '',
    'type'      => $item['type'] ?? 'SOCIETE',
    'ice'       => $item['ice'] ?? '',
    'telephone' => $item['telephone'] ?? '',
    'email'     => $item['email'] ?? '',
    'adresse'   => $item['adresse'] ?? '',
];
$form = is_array($old) ? array_merge($defaults, $old) : $defaults;

$nbDocuments = $id > 0 ? (int) scalar('SELECT COUNT(*) FROM document_paiement WHERE beneficiaire_id = ?', [$id], 0) : 0;

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <a class="back-link" href="<?= h(url('beneficiaires/index.php')) ?>">← <?= h(t('Retour à la liste')) ?></a>
        <h1 class="page-title"><?= h($id > 0 ? t('Modifier le bénéficiaire') : t('Nouveau bénéficiaire')) ?></h1>
        <p class="page-sub"><?= h(t('Le client ou la société qui vous remet les documents de paiement.')) ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert-error"><span class="alert-icon">!</span><?= h(t('Veuillez corriger les erreurs ci-dessous.')) ?></div>
<?php endif; ?>

<form method="post" action="<?= h(url('beneficiaires/save.php')) ?>">
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
                    <label for="type"><?= h(t('Type')) ?> <span class="req">*</span></label>
                    <select id="type" name="type" class="select" required>
                        <option value="SOCIETE"<?= $form['type'] === 'SOCIETE' ? ' selected' : '' ?>><?= h(t('Société')) ?></option>
                        <option value="PERSONNE"<?= $form['type'] === 'PERSONNE' ? ' selected' : '' ?>><?= h(t('Personne')) ?></option>
                    </select>
                </div>

                <div class="field">
                    <label for="ice">ICE</label>
                    <input id="ice" name="ice" type="text" class="input" maxlength="20" value="<?= h($form['ice']) ?>">
                    <span class="hint"><?= h(t('Identifiant Commun de l’Entreprise (sociétés).')) ?></span>
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
            <a class="btn btn-ghost" href="<?= h(url('beneficiaires/index.php')) ?>"><?= h(t('Annuler')) ?></a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
                <?= h($id > 0 ? t('Enregistrer les modifications') : t('Créer le bénéficiaire')) ?>
            </button>
        </div>
    </div>
</form>

<?php if ($id > 0): ?>
    <div class="card mt-16" style="max-width:820px">
        <div class="card-header">
            <div class="card-title"><?= h(t('Documents liés')) ?></div>
            <a class="link-btn" href="<?= h(query_url('documents/index.php', ['beneficiaire' => $id])) ?>"><?= h(t('Voir les documents')) ?> →</a>
        </div>
        <div class="card-body">
            <div class="mini-grid">
                <div class="mini-stat">
                    <div class="mini-label"><?= h(t('Documents')) ?></div>
                    <div class="mini-value"><?= $nbDocuments ?></div>
                </div>
                <div class="mini-stat">
                    <div class="mini-label"><?= h(t('Montant total')) ?></div>
                    <div class="mini-value"><?= h(money((float) scalar('SELECT COALESCE(SUM(montant), 0) FROM document_paiement WHERE beneficiaire_id = ?', [$id], 0))) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
