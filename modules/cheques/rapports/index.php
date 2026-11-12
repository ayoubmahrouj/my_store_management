<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();

require_once __DIR__ . '/../lib/Reports.php';

$title = 'Rapports PDF';
$active = 'rapports';

$catalog = report_catalog();
$reportKey = report_type_from_input($_GET);
$filters = report_normalize_filters($_GET);
$preview = report_preview($reportKey, $filters);

$beneficiaires = fetch_all('SELECT id, nom FROM beneficiaire ORDER BY nom');
$personnes = fetch_all('SELECT id, nom FROM personne ORDER BY nom');
$carnets = fetch_all('SELECT id, libelle, code FROM carnet ORDER BY libelle');

$pdfQuery = $_GET;
$pdfQuery['rapport'] = $reportKey;
$pdfUrl = query_url('rapports/pdf.php', $pdfQuery);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Rapports PDF')) ?></h1>
        <p><?= h(t('Générez un PDF détaillé avec totaux pour chaque liste, période ou filtre.')) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= h($pdfUrl) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 21h16"/></svg>
            <?= h(t('Télécharger le PDF')) ?>
        </a>
    </div>
</div>

<div class="report-layout">
    <aside class="card report-catalog">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Modèles de rapports')) ?></div>
                <div class="card-sub"><?= h(t('Choisissez le rapport à générer.')) ?></div>
            </div>
        </div>
        <div class="report-catalog-list">
            <?php foreach ($catalog as $key => $meta): ?>
                <a class="report-card<?= $key === $reportKey ? ' active' : '' ?>"
                   href="<?= h(query_url('rapports/index.php', ['rapport' => $key])) ?>">
                    <span class="report-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $meta['icon'] ?></svg>
                    </span>
                    <span class="report-card-body">
                        <span class="report-card-title"><?= h(t($meta['label'])) ?></span>
                        <span class="report-card-text"><?= h(t($meta['description'])) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <section class="card report-builder">
        <div class="card-header">
            <div>
                <div class="card-title"><?= h(t('Paramètres du rapport')) ?></div>
                <div class="card-sub"><?= h(t('Ajustez les filtres puis prévisualisez ou téléchargez le PDF.')) ?></div>
            </div>
            <span class="badge badge-neutral"><?= h(t('PDF')) ?></span>
        </div>
        <div class="card-body">
            <form id="reportForm" method="get" action="<?= h(url('rapports/index.php')) ?>" class="report-form">
                <div class="report-form-grid">
                    <div class="field">
                        <label for="reportType"><?= h(t('Type de rapport')) ?></label>
                        <select class="select" id="reportType" name="rapport">
                            <?php foreach ($catalog as $key => $meta): ?>
                                <option value="<?= h($key) ?>"<?= $key === $reportKey ? ' selected' : '' ?>><?= h(t($meta['label'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese beneficiaires personnes carnets">
                        <label for="reportQ"><?= h(t('Recherche')) ?></label>
                        <input class="input" id="reportQ" name="q" value="<?= h($filters['q']) ?>" placeholder="<?= h(t('Nom, numéro, banque…')) ?>">
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese carnets">
                        <label for="reportDocType"><?= h(t('Type de document')) ?></label>
                        <select class="select" id="reportDocType" name="type">
                            <option value=""><?= h(t('Tous les types')) ?></option>
                            <option value="CHEQUE"<?= $filters['type'] === 'CHEQUE' ? ' selected' : '' ?>><?= h(t('Chèques')) ?></option>
                            <option value="EFFET"<?= $filters['type'] === 'EFFET' ? ' selected' : '' ?>><?= h(t('Effets')) ?></option>
                        </select>
                    </div>

                    <div class="field" data-report-for="beneficiaires">
                        <label for="reportBenefType"><?= h(t('Type de bénéficiaire')) ?></label>
                        <select class="select" id="reportBenefType" name="type_beneficiaire">
                            <option value=""><?= h(t('Tous les types')) ?></option>
                            <option value="SOCIETE"<?= $filters['type_beneficiaire'] === 'SOCIETE' ? ' selected' : '' ?>><?= h(t('Société')) ?></option>
                            <option value="PERSONNE"<?= $filters['type_beneficiaire'] === 'PERSONNE' ? ' selected' : '' ?>><?= h(t('Personne')) ?></option>
                        </select>
                    </div>

                    <div class="field" data-report-for="personnes">
                        <label for="reportRole"><?= h(t('Rôle')) ?></label>
                        <select class="select" id="reportRole" name="role">
                            <option value=""><?= h(t('Tous les rôles')) ?></option>
                            <option value="PROPRIETAIRE_SOCIETE"<?= $filters['role'] === 'PROPRIETAIRE_SOCIETE' ? ' selected' : '' ?>><?= h(t('Propriétaire')) ?></option>
                            <option value="GERANT"<?= $filters['role'] === 'GERANT' ? ' selected' : '' ?>><?= h(t('Gérant')) ?></option>
                            <option value="EMPLOYE"<?= $filters['role'] === 'EMPLOYE' ? ' selected' : '' ?>><?= h(t('Employé')) ?></option>
                        </select>
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese">
                        <label for="reportStatut"><?= h(t('Statut')) ?></label>
                        <select class="select" id="reportStatut" name="statut">
                            <option value=""><?= h(t('Tous les statuts')) ?></option>
                            <option value="true"<?= $filters['statut'] === 'true' ? ' selected' : '' ?>><?= h(t('Réglés')) ?></option>
                            <option value="false"<?= $filters['statut'] === 'false' ? ' selected' : '' ?>><?= h(t('Non réglés')) ?></option>
                            <option value="rejete"<?= $filters['statut'] === 'rejete' ? ' selected' : '' ?>><?= h(t('Rejetés')) ?></option>
                        </select>
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese beneficiaires personnes carnets">
                        <label for="reportDateFrom"><?= h(t('Du')) ?></label>
                        <input class="input" type="date" id="reportDateFrom" name="date_from" value="<?= h($filters['date_from']) ?>">
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese beneficiaires personnes carnets">
                        <label for="reportDateTo"><?= h(t('Au')) ?></label>
                        <input class="input" type="date" id="reportDateTo" name="date_to" value="<?= h($filters['date_to']) ?>">
                    </div>

                    <div class="field" data-report-for="statistiques synthese">
                        <label for="reportDateBasis"><?= h(t('Période basée sur')) ?></label>
                        <select class="select" id="reportDateBasis" name="date_basis">
                            <option value="ECHEANCE"<?= $filters['date_basis'] === 'ECHEANCE' ? ' selected' : '' ?>><?= h(t("Date d'échéance")) ?></option>
                            <option value="EMISSION"<?= $filters['date_basis'] === 'EMISSION' ? ' selected' : '' ?>><?= h(t("Date d'émission")) ?></option>
                            <option value="REGLEMENT"<?= $filters['date_basis'] === 'REGLEMENT' ? ' selected' : '' ?>><?= h(t('Date de règlement')) ?></option>
                        </select>
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese">
                        <label for="reportBeneficiaire"><?= h(t('Bénéficiaire')) ?></label>
                        <select class="select" id="reportBeneficiaire" name="beneficiaire" data-quick="client">
                            <option value="0"><?= h(t('Tous les bénéficiaires')) ?></option>
                            <?php foreach ($beneficiaires as $beneficiaire): ?>
                                <option value="<?= (int) $beneficiaire['id'] ?>"<?= (int) $filters['beneficiaire'] === (int) $beneficiaire['id'] ? ' selected' : '' ?>><?= h($beneficiaire['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese">
                        <label for="reportPersonne"><?= h(t('Personne responsable')) ?></label>
                        <select class="select" id="reportPersonne" name="personne" data-quick="personne">
                            <option value="0"><?= h(t('Toutes les personnes')) ?></option>
                            <?php foreach ($personnes as $personne): ?>
                                <option value="<?= (int) $personne['id'] ?>"<?= (int) $filters['personne'] === (int) $personne['id'] ? ' selected' : '' ?>><?= h($personne['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese">
                        <label for="reportCarnet"><?= h(t('Carnet')) ?></label>
                        <select class="select" id="reportCarnet" name="carnet" data-quick="carnet">
                            <option value="0"><?= h(t('Tous les carnets')) ?></option>
                            <?php foreach ($carnets as $carnet): ?>
                                <option value="<?= (int) $carnet['id'] ?>"<?= (int) $filters['carnet'] === (int) $carnet['id'] ? ' selected' : '' ?>><?= h(carnet_label($carnet)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" data-report-for="documents statistiques synthese">
                        <label for="reportBanque"><?= h(t('Banque')) ?></label>
                        <input class="input" id="reportBanque" name="banque" value="<?= h($filters['banque']) ?>" placeholder="<?= h(t('Nom de la banque')) ?>">
                    </div>

                    <div class="field" data-report-for="documents">
                        <label for="reportMoisEcheance"><?= h(t("Mois d'échéance")) ?></label>
                        <input class="input" type="month" id="reportMoisEcheance" name="mois_echeance" value="<?= h($filters['mois_echeance']) ?>">
                    </div>

                    <div class="field" data-report-for="documents">
                        <label for="reportMoisEmission"><?= h(t("Mois d'émission")) ?></label>
                        <input class="input" type="month" id="reportMoisEmission" name="mois_emission" value="<?= h($filters['mois_emission']) ?>">
                    </div>

                    <div class="field" data-report-for="documents">
                        <label for="reportJours"><?= h(t('Échéance dans')) ?></label>
                        <select class="select" id="reportJours" name="jours">
                            <option value="0"><?= h(t('Toutes les échéances')) ?></option>
                            <?php foreach ([7, 15, 30, 60, 90] as $jours): ?>
                                <option value="<?= $jours ?>"<?= (int) $filters['jours'] === $jours ? ' selected' : '' ?>><?= $jours ?> <?= h(t('jours')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" data-report-for="documents">
                        <label for="reportMontantMin"><?= h(t('Montant minimum')) ?></label>
                        <input class="input" type="number" min="0" step="0.01" id="reportMontantMin" name="montant_min" value="<?= $filters['montant_min'] !== null ? h((string) $filters['montant_min']) : '' ?>">
                    </div>

                    <div class="field" data-report-for="documents">
                        <label for="reportMontantMax"><?= h(t('Montant maximum')) ?></label>
                        <input class="input" type="number" min="0" step="0.01" id="reportMontantMax" name="montant_max" value="<?= $filters['montant_max'] !== null ? h((string) $filters['montant_max']) : '' ?>">
                    </div>

                    <div class="field" data-report-for="calendrier">
                        <label for="reportMois"><?= h(t('Mois')) ?></label>
                        <input class="input" type="month" id="reportMois" name="mois" value="<?= h($filters['mois']) ?>">
                    </div>

                    <div class="field" data-report-for="calendrier">
                        <label for="reportSeuilMois"><?= h(t('Seuil mensuel (DH)')) ?></label>
                        <input class="input" type="number" min="0" step="0.01" id="reportSeuilMois" name="seuil_mois" value="<?= $filters['seuil_mois'] !== null ? h((string) $filters['seuil_mois']) : '' ?>" placeholder="<?= h(t('Automatique')) ?>">
                    </div>

                    <div class="field" data-report-for="calendrier">
                        <label for="reportSeuilSemaine"><?= h(t('Seuil hebdomadaire (DH)')) ?></label>
                        <input class="input" type="number" min="0" step="0.01" id="reportSeuilSemaine" name="seuil_semaine" value="<?= $filters['seuil_semaine'] !== null ? h((string) $filters['seuil_semaine']) : '' ?>" placeholder="<?= h(t('Automatique')) ?>">
                    </div>
                </div>

                <div class="report-form-actions">
                    <button type="submit" class="btn btn-primary"><?= h(t('Aperçu')) ?></button>
                    <button type="submit" class="btn btn-gold" formaction="<?= h(url('rapports/pdf.php')) ?>">
                        <?= h(t('Télécharger le PDF')) ?>
                    </button>
                    <a class="btn btn-ghost" href="<?= h(url('rapports/index.php')) ?>"><?= h(t('Réinitialiser')) ?></a>
                </div>
            </form>
        </div>
    </section>
</div>

<section class="card report-preview">
    <div class="card-header">
        <div>
            <div class="card-title"><?= h(t('Aperçu')) ?> — <?= h(t($preview['title'])) ?></div>
            <div class="card-sub"><?= h(t($preview['subtitle'])) ?></div>
        </div>
        <span class="badge badge-neutral"><?= (int) $preview['sections'] ?> <?= h(t('section(s)')) ?></span>
    </div>
    <div class="card-body">
        <div class="report-preview-filters"><?= h($preview['filters']) ?></div>
        <?php if ($preview['kpis']): ?>
            <div class="report-kpis">
                <?php foreach ($preview['kpis'] as $kpi): ?>
                    <div class="report-kpi report-kpi-<?= h((string) ($kpi['tone'] ?? 'navy')) ?>">
                        <span class="report-kpi-label"><?= h(t((string) $kpi['label'])) ?></span>
                        <strong><?= h((string) $kpi['value']) ?></strong>
                        <?php if (($kpi['meta'] ?? '') !== ''): ?>
                            <span class="report-kpi-meta"><?= h((string) $kpi['meta']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="report-preview-actions">
            <a class="btn btn-primary" href="<?= h($pdfUrl) ?>">
                <?= h(t('Télécharger le PDF')) ?>
            </a>
            <span class="hint"><?= h(t('Les rapports PDF sont générés en français.')) ?></span>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';
    var select = document.getElementById('reportType');
    if (!select) { return; }
    function refresh() {
        var type = select.value;
        document.querySelectorAll('[data-report-for]').forEach(function (element) {
            var types = (element.getAttribute('data-report-for') || '').split(/\s+/);
            var enabled = types.indexOf(type) !== -1;
            element.hidden = !enabled;
            element.querySelectorAll('input, select, textarea').forEach(function (control) {
                control.disabled = !enabled;
            });
        });
    }
    select.addEventListener('change', refresh);
    refresh();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
