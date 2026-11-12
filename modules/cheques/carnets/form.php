<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Nouveau carnet';
$active = 'carnets';

$id = int_param('id');
$item = null;
if ($id > 0) {
    $item = fetch_one('SELECT * FROM carnet WHERE id = ?', [$id]);
    if (!$item) {
        flash('error', t('Carnet introuvable.'));
        redirect('carnets/index.php');
    }
    $title = 'Modifier le carnet';
}

$errors = form_errors();
clear_errors();
$old = $_SESSION['old'] ?? null;
clear_old();

$defaults = [
    'type'       => $item['type'] ?? 'CHEQUE',
    'libelle'    => $item['libelle'] ?? '',
    'code'       => isset($item['code']) ? (string) $item['code'] : '',
    'banque'     => $item['banque'] ?? '',
    'personne_id' => isset($item['personne_id']) ? (string) $item['personne_id'] : '',
];
$form = is_array($old) ? array_merge($defaults, $old) : $defaults;

$personnes = fetch_all('SELECT * FROM personne ORDER BY FIELD(role, "PROPRIETAIRE_SOCIETE", "GERANT", "EMPLOYE"), nom');
$groupes = ['PROPRIETAIRE_SOCIETE' => [], 'GERANT' => [], 'EMPLOYE' => []];
foreach ($personnes as $p) {
    $groupes[$p['role']][] = $p;
}

$nbDocuments = $id > 0 ? (int) scalar('SELECT COUNT(*) FROM document_paiement WHERE carnet_id = ?', [$id], 0) : 0;

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <a class="back-link" href="<?= h(url('carnets/index.php')) ?>">← <?= h(t('Retour à la liste')) ?></a>
        <h1 class="page-title"><?= h($id > 0 ? t('Modifier le carnet') : t('Nouveau carnet')) ?></h1>
        <p class="page-sub"><?= h(t('Un carnet de chèques ou un registre d’effets, rattaché à un propriétaire.')) ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert-error"><span class="alert-icon">!</span><?= h(t('Veuillez corriger les erreurs ci-dessous.')) ?></div>
<?php endif; ?>

<form method="post" action="<?= h(url('carnets/save.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="card" style="max-width:820px">
        <div class="form-section">
            <div class="form-section-title"><?= h(t('Carnet / registre')) ?></div>
            <div class="form-grid">
                <div class="field span-2">
                    <label><?= h(t('Type de carnet')) ?> <span class="req">*</span></label>
                    <input type="hidden" name="type" id="typeInput" value="<?= h($form['type']) ?>">
                    <div class="segmented">
                        <button type="button" class="segment cheque<?= $form['type'] === 'CHEQUE' ? ' active' : '' ?>" data-type="CHEQUE">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M7 14h4"/>
                            </svg>
                            <?= h(t('Chéquier')) ?>
                        </button>
                        <button type="button" class="segment effet<?= $form['type'] === 'EFFET' ? ' active' : '' ?>" data-type="EFFET">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h6M9 15h6M9 18h3"/>
                            </svg>
                            <?= h(t('Registre d’effets')) ?>
                        </button>
                    </div>
                    <?php if (isset($errors['type'])): ?><span class="error-text"><?= h($errors['type']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="libelle"><?= h(t('Référence')) ?> <span class="req">*</span></label>
                    <input id="libelle" name="libelle" type="text" class="input" maxlength="100" required
                           value="<?= h($form['libelle']) ?>" placeholder="<?= h(t('Ex. TEAM ENERGIE COMMERCIALE 2100')) ?>">
                    <?php if (isset($errors['libelle'])): ?><span class="error-text"><?= h($errors['libelle']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="code"><?= h(t('Code')) ?></label>
                    <input id="code" name="code" type="number" min="0" max="999999" class="input" value="<?= h($form['code']) ?>" placeholder="<?= h(t('Ex. 2100')) ?>">
                    <span class="hint"><?= h(t('Numéro court utilisé pour identifier le carnet (optionnel).')) ?></span>
                    <?php if (isset($errors['code'])): ?><span class="error-text"><?= h($errors['code']) ?></span><?php endif; ?>
                </div>

                <div class="field span-2">
                    <label for="banque"><?= h(t('Banque')) ?></label>
                    <input id="banque" name="banque" type="text" class="input" maxlength="100"
                           value="<?= h($form['banque']) ?>" placeholder="<?= h(t('Ex. BANQUE POPULAIRE')) ?>">
                </div>

                <div class="field span-2">
                    <label for="personneId"><?= h(t('Propriétaire')) ?> <span class="req">*</span></label>
                    <div class="carnet-select-row">
                        <select id="personneId" name="personne_id" class="select" required>
                            <option value=""><?= h(t('— Sélectionner le propriétaire —')) ?></option>
                            <?php foreach ($groupes as $roleKey => $group): ?>
                                <?php if (!$group) { continue; } ?>
                                <optgroup label="<?= h($roleKey === 'PROPRIETAIRE_SOCIETE' ? t('Propriétaire de la société') : role_label($roleKey)) ?>">
                                    <?php foreach ($group as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>"<?= (string) $form['personne_id'] === (string) $p['id'] ? ' selected' : '' ?>>
                                            <?= h($p['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-ghost btn-sm" data-quick-create="personne" data-target="#personneId">+ <?= h(t('Nouveau')) ?></button>
                    </div>
                    <?php if (isset($errors['personne_id'])): ?><span class="error-text"><?= h($errors['personne_id']) ?></span><?php endif; ?>
                    <span class="hint"><?= h(t('Le propriétaire est la personne à qui appartient ce carnet.')) ?></span>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn btn-ghost" href="<?= h(url('carnets/index.php')) ?>"><?= h(t('Annuler')) ?></a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
                <?= h($id > 0 ? t('Enregistrer les modifications') : t('Créer le carnet')) ?>
            </button>
        </div>
    </div>
</form>

<?php if ($id > 0): ?>
    <div class="card mt-16" style="max-width:820px">
        <div class="card-header">
            <div class="card-title"><?= h(t('Documents du carnet')) ?></div>
            <a class="link-btn" href="<?= h(query_url('documents/index.php', ['carnet' => $id])) ?>"><?= h(t('Voir les documents')) ?> →</a>
        </div>
        <div class="card-body">
            <div class="mini-grid">
                <div class="mini-stat">
                    <div class="mini-label"><?= h(t('Documents')) ?></div>
                    <div class="mini-value"><?= $nbDocuments ?></div>
                </div>
                <div class="mini-stat">
                    <div class="mini-label"><?= h(t('Montant total')) ?></div>
                    <div class="mini-value"><?= h(money((float) scalar('SELECT COALESCE(SUM(montant), 0) FROM document_paiement WHERE carnet_id = ?', [$id], 0))) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        (function ($) {
            $('.segment').on('click', function () {
                var type = $(this).attr('data-type');
                $('.segment').removeClass('active');
                $(this).addClass('active');
                $('#typeInput').val(type);
            });
        })(jQuery);
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
