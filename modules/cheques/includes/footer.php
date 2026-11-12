<?php
/**
 * Pied de page commun du module Chèques.
 *
 * Les messages flash sont affichés par la coquille commune. On conserve
 * ici la modale de création rapide historique du module ainsi que ses
 * scripts spécifiques.
 */
?>
<!-- Création rapide historique (personne / bénéficiaire / carnet) -->
<div class="modal-backdrop" id="quickCreateModal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="qcTitle">
        <div class="modal-header">
            <div class="modal-title" id="qcTitle"><?= h(t('Création rapide')) ?></div>
            <button type="button" class="icon-btn" data-close-modal aria-label="<?= h(t('Fermer')) ?>">×</button>
        </div>
        <form id="qcForm" class="modal-body" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="type" id="qcType" value="">
            <div id="qcFields"></div>
            <div class="error-text" id="qcError" style="display:none"></div>
        </form>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" data-close-modal><?= h(t('Annuler')) ?></button>
            <button type="submit" form="qcForm" class="btn btn-primary" id="qcSubmit"><?= h(t('Créer')) ?></button>
        </div>
    </div>
</div>

<script src="<?= h(asset('vendor/jquery.min.js')) ?>"></script>
<script>
    window.GC = {
        base: <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        lang: <?= json_encode(lang()) ?>,
        i18n: {
            required: <?= json_encode(t('Ce champ est obligatoire.'), JSON_UNESCAPED_UNICODE) ?>,
            error: <?= json_encode(t('Une erreur est survenue.'), JSON_UNESCAPED_UNICODE) ?>,
            create: <?= json_encode(t('Créer'), JSON_UNESCAPED_UNICODE) ?>,
            cancel: <?= json_encode(t('Annuler'), JSON_UNESCAPED_UNICODE) ?>,
            saving: <?= json_encode(t('Enregistrement…'), JSON_UNESCAPED_UNICODE) ?>,
            confirmDelete: <?= json_encode(t('Confirmer la suppression ?'), JSON_UNESCAPED_UNICODE) ?>,
            totalMois: <?= json_encode(t('Total du mois d’échéance'), JSON_UNESCAPED_UNICODE) ?>,
            moyenne: <?= json_encode(t('moyenne mensuelle'), JSON_UNESCAPED_UNICODE) ?>,
            surcharge: <?= json_encode(t('Attention : ce mois dépasse nettement la moyenne.'), JSON_UNESCAPED_UNICODE) ?>
        },
        labels: {
            nom: <?= json_encode(t('Nom'), JSON_UNESCAPED_UNICODE) ?>,
            role: <?= json_encode(t('Rôle'), JSON_UNESCAPED_UNICODE) ?>,
            telephone: <?= json_encode(t('Téléphone'), JSON_UNESCAPED_UNICODE) ?>,
            email: <?= json_encode(t('Email'), JSON_UNESCAPED_UNICODE) ?>,
            adresse: <?= json_encode(t('Adresse'), JSON_UNESCAPED_UNICODE) ?>,
            type: <?= json_encode(t('Type'), JSON_UNESCAPED_UNICODE) ?>,
            reference: <?= json_encode(t('Référence'), JSON_UNESCAPED_UNICODE) ?>,
            code: <?= json_encode(t('Code'), JSON_UNESCAPED_UNICODE) ?>,
            banque: <?= json_encode(t('Banque'), JSON_UNESCAPED_UNICODE) ?>,
            proprietaire: <?= json_encode(t('Propriétaire'), JSON_UNESCAPED_UNICODE) ?>,
            beneficiaire: <?= json_encode(t('Bénéficiaire'), JSON_UNESCAPED_UNICODE) ?>,
            personne: <?= json_encode(t('Personne'), JSON_UNESCAPED_UNICODE) ?>,
            societe: <?= json_encode(t('Société'), JSON_UNESCAPED_UNICODE) ?>,
            cheque: <?= json_encode(t('Chèque'), JSON_UNESCAPED_UNICODE) ?>,
            effet: <?= json_encode(t('Effet'), JSON_UNESCAPED_UNICODE) ?>,
            gerant: <?= json_encode(t('Gérant'), JSON_UNESCAPED_UNICODE) ?>,
            employe: <?= json_encode(t('Employé'), JSON_UNESCAPED_UNICODE) ?>,
            nouveauPersonne: <?= json_encode(t('Nouvelle personne responsable'), JSON_UNESCAPED_UNICODE) ?>,
            nouveauBeneficiaire: <?= json_encode(t('Nouveau bénéficiaire'), JSON_UNESCAPED_UNICODE) ?>,
            nouveauCarnet: <?= json_encode(t('Nouveau carnet / registre'), JSON_UNESCAPED_UNICODE) ?>,
            totalMois: <?= json_encode(t('Total du mois d’échéance'), JSON_UNESCAPED_UNICODE) ?>,
            moyenne: <?= json_encode(t('moyenne mensuelle'), JSON_UNESCAPED_UNICODE) ?>,
            surcharge: <?= json_encode(t('Attention : ce mois dépasse nettement la moyenne.'), JSON_UNESCAPED_UNICODE) ?>
        }
    };
</script>
<script src="<?= h(asset('js/app.js')) ?>"></script>
<?php if (!empty($extraScripts)) {
    echo $extraScripts;
} ?>
<?php sh_layout_end(); ?>
