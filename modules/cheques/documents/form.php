<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

$title = 'Nouveau document';
$active = 'documents';

$id = int_param('id');
$doc = null;
if ($id > 0) {
    $doc = fetch_one('SELECT * FROM document_paiement WHERE id = ?', [$id]);
    if (!$doc) {
        flash('error', t('Document introuvable.'));
        redirect('documents/index.php');
    }
    $title = 'Modifier le document';
}

$errors = form_errors();
clear_errors();

$carnets = fetch_all('SELECT * FROM carnet ORDER BY libelle');
$beneficiaires = fetch_all('SELECT * FROM beneficiaire ORDER BY nom');
$personnes = fetch_all('SELECT * FROM personne ORDER BY nom');

$personnesParRole = ['PROPRIETAIRE_SOCIETE' => [], 'GERANT' => [], 'EMPLOYE' => []];
foreach ($personnes as $p) {
    $personnesParRole[$p['role']][] = $p;
}

$prefillType = (string) param('type');
$prefillMontant = float_param('montant');
$prefillDateEcheance = (string) param('date_echeance');

$defaults = [
    'type'           => $doc['type'] ?? (in_array($prefillType, ['CHEQUE', 'EFFET'], true) ? $prefillType : 'CHEQUE'),
    'carnet_id'      => $doc['carnet_id'] ?? '',
    'numero'         => $doc['numero'] ?? '',
    'beneficiaire_id' => $doc['beneficiaire_id'] ?? '',
    'personne_id'    => $doc['personne_id'] ?? '',
    'montant'        => isset($doc['montant'])
        ? (string) $doc['montant']
        : ($prefillMontant !== null && $prefillMontant > 0 ? (string) $prefillMontant : ''),
    'banque'         => $doc['banque'] ?? '',
    'date_emission'  => $doc['date_emission'] ?? today_iso(),
    'date_echeance'  => $doc['date_echeance'] ?? (isValidDate($prefillDateEcheance) ? $prefillDateEcheance : echeance_par_defaut(today_iso())),
    'regle'          => isset($doc['regle']) && bit_true($doc['regle']) ? '1' : '0',
    'rejete'         => isset($doc['rejete']) && bit_true($doc['rejete']) ? '1' : '0',
    'date_reglement' => $doc['date_reglement'] ?? '',
    'remarque'       => $doc['remarque'] ?? '',
    'image_path'     => $doc['image_path'] ?? null,
    'image_back_path' => $doc['image_back_path'] ?? null,
];

$old = $_SESSION['old'] ?? null;
clear_old();
$form = is_array($old) ? array_merge($defaults, $old) : $defaults;

$joursCalc = '';
if (!empty($form['date_emission']) && !empty($form['date_echeance'])) {
    $joursCalc = (string) days_until((string) $form['date_echeance']); // diff depuis aujourd'hui, ajusté ci-dessous
    $d1 = new DateTimeImmutable((string) $form['date_emission']);
    $d2 = new DateTimeImmutable((string) $form['date_echeance']);
    $joursCalc = (string) (int) $d1->diff($d2)->format('%r%a');
}

function ferr(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<span class="error-text">' . h($errors[$key]) . '</span>' : '';
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <a class="back-link" href="<?= h(url('documents/index.php')) ?>">← <?= h(t('Retour à la liste')) ?></a>
        <h1 class="page-title"><?= h($id > 0 ? t('Modifier le document') : t('Nouveau document')) ?></h1>
        <p class="page-sub">
            <?= h($id > 0
                ? t('Mettez à jour les informations du chèque ou de l’effet.')
                : t('Enregistrez un nouveau chèque ou effet.')) ?>
        </p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert-error">
        <span class="alert-icon">!</span>
        <?= h(t('Veuillez corriger les erreurs ci-dessous.')) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= h(url('documents/save.php')) ?>" id="documentForm" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" id="documentId" value="<?= (int) $id ?>">

    <div class="form-layout">
        <div class="form-main">
            <div class="card">
                <div class="form-section">
                    <div class="form-section-title"><?= h(t('Document')) ?></div>

                    <div class="field span-2">
                        <label><?= h(t('Type de document')) ?></label>
                        <input type="hidden" name="type" id="typeInput" value="<?= h($form['type']) ?>">
                        <div class="segmented">
                            <button type="button" class="segment cheque<?= $form['type'] === 'CHEQUE' ? ' active' : '' ?>" data-type="CHEQUE">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M7 14h4"/>
                                </svg>
                                <?= h(t('Chèque')) ?>
                            </button>
                            <button type="button" class="segment effet<?= $form['type'] === 'EFFET' ? ' active' : '' ?>" data-type="EFFET">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h6M9 15h6M9 18h3"/>
                                </svg>
                                <?= h(t('Effet')) ?>
                            </button>
                        </div>
                        <span class="hint">
                            <?= h($form['type'] === 'EFFET'
                                ? t('L’effet est une lettre de change / un billet à ordre payable à une date donnée.')
                                : t('Le chèque est un ordre de paiement bancaire remis par le client.')) ?>
                        </span>
                    </div>

                    <div class="form-grid mt-16">
                        <div class="form-group-title"><?= h(t('Provenance')) ?></div>

                        <div class="field">
                            <label for="carnetId"><?= h(t('Carnet / Registre')) ?> <span class="req">*</span></label>
                            <div class="carnet-select-row">
                                <select id="carnetId" name="carnet_id" class="select" required>
                                    <option value=""><?= h(t('— Sélectionner un carnet —')) ?></option>
                                    <?php foreach ($carnets as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>"
                                                data-type="<?= h($c['type']) ?>"
                                                data-banque="<?= h($c['banque'] ?? '') ?>"
                                                data-personne="<?= (int) ($c['personne_id'] ?? 0) ?>"
                                                <?= (string) $form['carnet_id'] === (string) $c['id'] ? 'selected' : '' ?>>
                                            <?= h(carnet_label($c)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-ghost btn-sm" data-quick-create="carnet" data-target="#carnetId">+ <?= h(t('Nouveau')) ?></button>
                            </div>
                            <?= ferr($errors, 'carnet_id') ?>
                            <span class="hint"><?= h(t("Carnet de chèques ou registre d'où provient le document. La liste est filtrée selon le type sélectionné.")) ?></span>
                        </div>

                        <div class="field">
                            <label for="numero"><?= h(t('Numéro')) ?> <span class="req">*</span></label>
                            <input id="numero" name="numero" type="text" class="input" maxlength="50" required
                                   value="<?= h($form['numero']) ?>" placeholder="<?= h(t('Ex. 4521987')) ?>">
                            <?= ferr($errors, 'numero') ?>
                        </div>

                        <div class="form-group-title"><?= h(t('À qui appartient ce document ?')) ?></div>

                        <div class="field span-2">
                            <label for="personneId"><?= h(t('Personne responsable')) ?> <span class="req">*</span></label>
                            <div class="carnet-select-row">
                                <select id="personneId" name="personne_id" class="select" required>
                                    <option value=""><?= h(t('— Sélectionner une personne —')) ?></option>
                                    <?php foreach ($personnesParRole as $role => $group): ?>
                                        <?php if (!$group) { continue; } ?>
                                        <optgroup label="<?= h($role === 'PROPRIETAIRE_SOCIETE' ? t('Propriétaire de la société') : role_label($role)) ?>">
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
                            <?= ferr($errors, 'personne_id') ?>
                            <span class="hint"><?= h(t('Personne interne responsable du document : propriétaire, gérant ou employé.')) ?></span>
                        </div>

                        <div class="form-group-title"><?= h(t('Le client')) ?></div>

                        <div class="field span-2">
                            <label for="beneficiaireId"><?= h(t('Bénéficiaire / Tiers')) ?> <span class="req">*</span></label>
                            <div class="carnet-select-row">
                                <select id="beneficiaireId" name="beneficiaire_id" class="select" required>
                                    <option value=""><?= h(t('— Sélectionner un bénéficiaire —')) ?></option>
                                    <?php foreach ($beneficiaires as $b): ?>
                                        <option value="<?= (int) $b['id'] ?>"<?= (string) $form['beneficiaire_id'] === (string) $b['id'] ? ' selected' : '' ?>>
                                            <?= h($b['nom']) ?> · <?= h(beneficiaire_type_label($b['type'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-ghost btn-sm" data-quick-create="beneficiaire" data-target="#beneficiaireId">+ <?= h(t('Nouveau')) ?></button>
                            </div>
                            <?= ferr($errors, 'beneficiaire_id') ?>
                            <span class="hint"><?= h(t('La personne ou la société qui vous a remis le document (votre client).')) ?></span>
                        </div>

                        <div class="field">
                            <label for="montant"><?= h(t('Montant')) ?> <span class="req">*</span></label>
                            <div class="input-suffix-wrap">
                                <input id="montant" name="montant" type="number" min="0.01" step="0.01" class="input" required
                                       value="<?= h($form['montant']) ?>" placeholder="0,00">
                                <span class="input-suffix">DH</span>
                            </div>
                            <?= ferr($errors, 'montant') ?>
                        </div>

                        <div class="field">
                            <label for="banque"><?= h(t('Banque')) ?></label>
                            <input id="banque" name="banque" type="text" class="input" maxlength="100"
                                   value="<?= h($form['banque']) ?>" placeholder="<?= h(t('Ex. Attijariwafa Bank')) ?>">
                            <span class="hint"><?= h(t('Banque du client / du tireur.')) ?></span>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><?= h(t('Dates & statut')) ?></div>
                    <div class="form-grid">
                        <div class="field">
                            <label for="dateEmission"><?= h(t("Date d'émission")) ?> <span class="req">*</span></label>
                            <input id="dateEmission" name="date_emission" type="date" class="input" required value="<?= h($form['date_emission']) ?>">
                            <?= ferr($errors, 'date_emission') ?>
                            <span class="hint"><?= h(t('Date à laquelle le document a été signé ou remis.')) ?></span>
                        </div>

                        <div class="field">
                            <label for="joursCalc"><?= h(t('Dans combien de jours ?')) ?></label>
                            <input id="joursCalc" type="number" min="0" class="input" value="<?= h($joursCalc) ?>" placeholder="<?= h(t('Ex. 90')) ?>">
                            <span class="hint"><?= h(t('L’échéance est calculée automatiquement : émission + ce nombre de jours.')) ?></span>
                        </div>

                        <div class="field">
                            <label for="dateEcheance"><?= h(t("Date d'échéance")) ?> <span class="req">*</span></label>
                            <input id="dateEcheance" name="date_echeance" type="date" class="input" required value="<?= h($form['date_echeance']) ?>">
                            <?= ferr($errors, 'date_echeance') ?>
                            <span class="hint"><?= h(t('Date à laquelle le montant doit être payé.')) ?></span>
                            <div id="monthTotalInfo"></div>
                        </div>

                        <div class="field span-2">
                            <div class="switch-row">
                                <label class="switch">
                                    <input type="checkbox" id="regleInput" name="regle" value="1" <?= $form['regle'] === '1' ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                </label>
                                <div>
                                    <div class="switch-title"><?= h(t('Document réglé (payé)')) ?></div>
                                    <div class="hint"><?= h(t('Par défaut, un nouveau document est non réglé. Activez quand le paiement est encaissé.')) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="field span-2">
                            <div class="switch-row">
                                <label class="switch">
                                    <input type="checkbox" id="rejeteInput" name="rejete" value="1" <?= $form['rejete'] === '1' ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                </label>
                                <div>
                                    <div class="switch-title"><?= h(t('Document rejeté (impayé)')) ?></div>
                                    <div class="hint"><?= h(t('À activer si le chèque est revenu impayé (sans provision).')) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="field span-2" id="dateReglementField" style="<?= $form['regle'] === '1' ? '' : 'display:none' ?>">
                            <label for="dateReglement"><?= h(t('Date de règlement')) ?></label>
                            <input id="dateReglement" name="date_reglement" type="date" class="input" value="<?= h($form['date_reglement']) ?>">
                            <span class="hint"><?= h(t('Date à laquelle le paiement a été encaissé. Utilisée pour les statistiques par mois.')) ?></span>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><?= h(t('Informations complémentaires')) ?></div>
                    <div class="form-grid">
                        <div class="field span-2">
                            <label for="remarque"><?= h(t('Remarque')) ?></label>
                            <textarea id="remarque" name="remarque" class="textarea" maxlength="500" placeholder="<?= h(t('Note interne…')) ?>"><?= h($form['remarque']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title"><?= h(t('Images du document')) ?></div>
                    <div class="form-grid">
                        <div class="field">
                            <label for="image"><?= h(t('Image recto (photo ou scan)')) ?></label>
                            <?php if ($id > 0 && !empty($form['image_path'])): ?>
                                <div class="image-current">
                                    <a href="<?= h(url('documents/image.php?id=' . $id . '&side=front')) ?>"
                                       data-gos-image data-title="<?= h(t('Image recto (photo ou scan)')) ?>">
                                        <img src="<?= h(url('documents/image.php?id=' . $id . '&side=front')) ?>"
                                             alt="<?= h(t('Image recto (photo ou scan)')) ?>">
                                    </a>
                                    <label class="image-remove">
                                        <input type="checkbox" name="remove_image" value="1">
                                        <?= h(t('Supprimer l’image actuelle')) ?>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <input id="image" name="image" type="file" class="input"
                                   accept="image/jpeg,image/png,image/webp">
                            <?= ferr($errors, 'image') ?>
                            <span class="hint"><?= h(t('JPG, PNG ou WEBP — 6 Mo maximum.')) ?></span>
                        </div>

                        <div class="field">
                            <label for="image_back"><?= h(t('Image verso (facultatif)')) ?></label>
                            <?php if ($id > 0 && !empty($form['image_back_path'])): ?>
                                <div class="image-current">
                                    <a href="<?= h(url('documents/image.php?id=' . $id . '&side=back')) ?>"
                                       data-gos-image data-title="<?= h(t('Image verso (facultatif)')) ?>">
                                        <img src="<?= h(url('documents/image.php?id=' . $id . '&side=back')) ?>"
                                             alt="<?= h(t('Image verso (facultatif)')) ?>">
                                    </a>
                                    <label class="image-remove">
                                        <input type="checkbox" name="remove_image_back" value="1">
                                        <?= h(t('Supprimer l’image actuelle')) ?>
                                    </label>
                                </div>
                            <?php endif; ?>
                            <input id="image_back" name="image_back" type="file" class="input"
                                   accept="image/jpeg,image/png,image/webp">
                            <?= ferr($errors, 'image_back') ?>
                            <span class="hint"><?= h(t('JPG, PNG ou WEBP — 6 Mo maximum.')) ?></span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a class="btn btn-ghost" href="<?= h(url('documents/index.php')) ?>"><?= h(t('Annuler')) ?></a>
                    <button type="submit" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
                        <?= h($id > 0 ? t('Enregistrer les modifications') : t('Créer le document')) ?>
                    </button>
                </div>
            </div>
        </div>

        <aside class="form-side">
            <div class="summary-hero">
                <div class="summary-label"><?= h(t('Aperçu')) ?></div>
                <div class="summary-amount" id="summaryAmount"><?= h(money($form['montant'] !== '' ? $form['montant'] : 0)) ?></div>
                <div class="summary-type">
                    <span class="badge <?= $form['type'] === 'EFFET' ? 'badge-effet' : 'badge-cheque' ?>" id="summaryType"><?= h(type_label($form['type'])) ?></span>
                </div>

                <div class="summary-list">
                    <div class="summary-row"><span><?= h(t('Carnet / Registre')) ?></span><span id="sumCarnet">—</span></div>
                    <div class="summary-row"><span><?= h(t('Numéro')) ?></span><span id="sumNumero"><?= h($form['numero'] ?: '—') ?></span></div>
                    <div class="summary-row"><span><?= h(t('Bénéficiaire')) ?></span><span id="sumBenef">—</span></div>
                    <div class="summary-row"><span><?= h(t('Personne responsable')) ?></span><span id="sumPersonne">—</span></div>
                    <div class="summary-row"><span><?= h(t('Émission')) ?></span><span id="sumEmission"><?= h(date_fr($form['date_emission'])) ?></span></div>
                    <div class="summary-row"><span><?= h(t('Échéance')) ?></span><span id="sumEcheance"><?= h(date_fr($form['date_echeance'])) ?></span></div>
                    <div class="summary-row"><span><?= h(t('Règlement')) ?></span><span id="sumReglement"><?= h($form['regle'] === '1' ? t('Réglé') : t('Non réglé')) ?></span></div>
                </div>
            </div>

            <div class="card side-note">
                <div class="side-note-title"><?= h(t('Bénéficiaire et personne responsable')) ?></div>
                <p>
                    <strong><?= h(t('Bénéficiaire / Tiers')) ?></strong> : <?= h(t('la personne ou la société qui vous a remis le document (votre client).')) ?><br>
                    <strong><?= h(t('Personne responsable')) ?></strong> : <?= h(t("le propriétaire, le gérant ou l'employé qui suit ce document en interne.")) ?>
                </p>
            </div>

            <div class="card side-note">
                <div class="side-note-title"><?= h(t('Bon à savoir')) ?></div>
                <p><?= h(t("Une fiche PDF reprenant toutes ces informations est générée automatiquement. Le montant est également converti en toutes lettres pour l'impression.")) ?></p>
                <a class="side-link" href="<?= h(url('aide.php')) ?>"><?= h(t('Ouvrir le guide complet')) ?> →</a>
            </div>
        </aside>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
(function ($) {
    function fmtDate(iso) {
        if (!iso) { return '—'; }
        var p = iso.split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso;
    }
    function fmtMoney(n) {
        return (Number(n) || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' DH';
    }

    function syncType(type) {
        $('#typeInput').val(type).trigger('change');
        $('.segment').removeClass('active');
        $('.segment[data-type="' + type + '"]').addClass('active');
        $('#summaryType').text(type === 'EFFET' ? <?= json_encode(t('Effet')) ?> : <?= json_encode(t('Chèque')) ?>)
            .removeClass('badge-cheque badge-effet')
            .addClass(type === 'EFFET' ? 'badge-effet' : 'badge-cheque');
    }
    $('.segment').on('click', function () { syncType($(this).attr('data-type')); });

    function syncSummary() {
        $('#summaryAmount').text(fmtMoney($('#montant').val()));
        $('#sumCarnet').text($('#carnetId option:selected').text().replace(/^—.*—$/, '—').trim() || '—');
        $('#sumNumero').text($('#numero').val() || '—');
        $('#sumBenef').text($('#beneficiaireId option:selected').val() ? $('#beneficiaireId option:selected').text().split(' · ')[0] : '—');
        $('#sumPersonne').text($('#personneId option:selected').val() ? $('#personneId option:selected').text().trim() : '—');
        $('#sumEmission').text(fmtDate($('#dateEmission').val()));
        $('#sumEcheance').text(fmtDate($('#dateEcheance').val()));
        var regle = $('#regleInput').is(':checked');
        var rejete = $('#rejeteInput').is(':checked');
        $('#sumReglement').text(rejete ? <?= json_encode(t('Rejeté')) ?> : (regle ? <?= json_encode(t('Réglé')) ?> : <?= json_encode(t('Non réglé')) ?>));
    }

    $('#montant, #numero, #dateEmission, #dateEcheance, #carnetId, #beneficiaireId, #personneId, #regleInput, #rejeteInput')
        .on('input change', syncSummary);

    $('#regleInput').on('change', function () {
        if ($(this).is(':checked')) { $('#rejeteInput').prop('checked', false); }
        $('#dateReglementField').toggle($(this).is(':checked'));
        if ($(this).is(':checked') && !$('#dateReglement').val()) {
            $('#dateReglement').val(new Date().toISOString().slice(0, 10));
        }
        syncSummary();
    });
    $('#rejeteInput').on('change', function () {
        if ($(this).is(':checked')) {
            $('#regleInput').prop('checked', false);
            $('#dateReglementField').hide();
        }
        syncSummary();
    });

    syncType($('#typeInput').val());
    syncSummary();
})(jQuery);
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
