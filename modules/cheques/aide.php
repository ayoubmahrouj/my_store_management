<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_login();

$title = 'Guide & aide';
$active = 'aide';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Guide des chèques & effets')) ?></h1>
        <p class="page-sub"><?= h(t("Tout ce qu'il faut savoir pour enregistrer, suivre et contrôler vos documents de paiement.")) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= h(url('documents/form.php')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            <?= h(t('Nouveau document')) ?>
        </a>
    </div>
</div>

<div class="guide-layout">
    <div class="guide-main">
        <section class="card guide-card intro-card">
            <h2><?= h(t('En 30 secondes')) ?></h2>
            <div class="intro-grid">
                <div class="intro-box">
                    <span class="intro-number">1</span>
                    <div>
                        <strong><?= h(t('Un bénéficiaire / tiers')) ?></strong>
                        <p><?= h(t("La personne ou la société qui vous remet le chèque ou l'effet (votre client).")) ?></p>
                    </div>
                </div>
                <div class="intro-box">
                    <span class="intro-number">2</span>
                    <div>
                        <strong><?= h(t('Un document')) ?></strong>
                        <p><?= h(t("Chèque ou effet, avec un numéro, un montant, une date d'émission et une échéance.")) ?></p>
                    </div>
                </div>
                <div class="intro-box">
                    <span class="intro-number">3</span>
                    <div>
                        <strong><?= h(t('Un suivi complet')) ?></strong>
                        <p><?= h(t('Le tableau de bord vous alerte sur les échéances proches, les documents en retard et les règlements à encaisser.')) ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="card guide-card">
            <h2><?= h(t('1. Chèque ou effet : quelle différence ?')) ?></h2>
            <div class="compare-grid">
                <div class="compare-box cheque">
                    <div class="compare-head"><span class="badge badge-cheque"><?= h(t('Chèque')) ?></span></div>
                    <p><?= h(t("Un ordre de paiement écrit par le client à destination de sa banque. La banque débite le compte du client et paie le bénéficiaire, généralement à présentation ou à la date d'échéance indiquée.")) ?></p>
                    <ul>
                        <li><?= h(t('Émis par un client ou une société')) ?></li>
                        <li><?= h(t('Débité sur le compte bancaire du client')) ?></li>
                        <li><?= h(t('Numéro imprimé sur le chéquier')) ?></li>
                        <li><?= h(t('La banque du client est utile à renseigner')) ?></li>
                    </ul>
                </div>
                <div class="compare-box effet">
                    <div class="compare-head"><span class="badge badge-effet"><?= h(t('Effet')) ?></span></div>
                    <p><?= h(t("Un effet de commerce (lettre de change ou billet à ordre) : le client s'engage à payer une somme à une date déterminée. Il peut être encaissé à l'échéance ou remis à l'escompte.")) ?></p>
                    <ul>
                        <li><?= h(t('Engagement de paiement à terme')) ?></li>
                        <li><?= h(t('Peut circuler par endossement')) ?></li>
                        <li><?= h(t("Numéro propre à l'effet")) ?></li>
                        <li><?= h(t("La date d'échéance est primordiale")) ?></li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="card guide-card">
            <h2><?= h(t('2. Les acteurs du document')) ?></h2>
            <div class="actor-grid">
                <div class="actor">
                    <span class="actor-badge"><?= h(t('Le client / tireur')) ?></span>
                    <p><?= h(t("La personne ou la société qui signe et vous remet le document. Dans l'application, c'est le bénéficiaire / tiers sélectionné dans le formulaire.")) ?></p>
                </div>
                <div class="actor">
                    <span class="actor-badge"><?= h(t('La personne responsable')) ?></span>
                    <p><?= h(t('Le propriétaire de la société, le gérant ou l\'employé qui suit le document en interne. Chaque document est rattaché à une personne responsable.')) ?></p>
                </div>
                <div class="actor">
                    <span class="actor-badge"><?= h(t('La banque')) ?></span>
                    <p><?= h(t("L'établissement qui détient le compte du client. Le champ Banque permet de la retrouver rapidement (Attijariwafa, BMCE, CIH…).")) ?></p>
                </div>
            </div>
            <div class="guide-callout">
                <strong><?= h(t("Vocabulaire de l'application :")) ?></strong>
                <?= h(t("le champ « Bénéficiaire / Tiers » désigne le client qui vous remet le document (le tireur). Le champ « Personne responsable » désigne la personne de votre société qui suit ce document en interne.")) ?>
            </div>
        </section>

        <section class="card guide-card">
            <h2><?= h(t("3. Les champs de l'application")) ?></h2>
            <div class="table-wrap">
                <table class="guide-table">
                    <thead>
                    <tr>
                        <th><?= h(t('Champ')) ?></th>
                        <th><?= h(t("Ce qu'il faut saisir")) ?></th>
                        <th><?= h(t('Exemple')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td><strong><?= h(t('Type')) ?></strong></td>
                        <td><?= h(t('Chèque ou effet.')) ?></td>
                        <td><?= h(t('Chèque')) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Numéro')) ?></strong></td>
                        <td><?= h(t('Numéro imprimé sur le document. Il est unique au sein d\'un même carnet / registre : un doublon est refusé.')) ?></td>
                        <td class="mono">4521987</td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Bénéficiaire / Tiers')) ?></strong></td>
                        <td><?= h(t('La personne ou la société qui vous a remis le document. Sélectionnez-la dans la liste avec recherche, ou créez-la avec le bouton « Nouveau ».')) ?></td>
                        <td><?= h(t('Atlas Voyages SARL')) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Personne responsable')) ?></strong></td>
                        <td><?= h(t('Le propriétaire de la société, le gérant ou l\'employé qui suit le document en interne.')) ?></td>
                        <td><?= h(t('Youssef El Amrani')) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Montant')) ?></strong></td>
                        <td><?= h(t('La somme en dirhams, avec les centimes.')) ?></td>
                        <td class="mono"><?= h(money(18500)) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t("Date d'émission")) ?></strong></td>
                        <td><?= h(t('La date à laquelle le document a été signé ou remis (format jj/mm/aaaa).')) ?></td>
                        <td class="mono">01/10/2026</td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t("Date d'échéance")) ?></strong></td>
                        <td><?= h(t('La date à laquelle le montant doit être payé (format jj/mm/aaaa). Elle doit être égale ou postérieure à la date d\'émission.')) ?></td>
                        <td class="mono">15/11/2026</td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Banque')) ?></strong></td>
                        <td><?= h(t('La banque du client / du tireur. Facultatif, mais recommandé.')) ?></td>
                        <td><?= h(t('Attijariwafa Bank')) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Carnet / registre')) ?></strong></td>
                        <td><?= h(t("Le carnet de chèques ou le registre d'effets d'où provient le document. Sélectionnez-le dans la liste déroulante (filtrée selon le type de document) ou créez-le avec le bouton « + Nouveau ». Chaque document doit être rattaché à un carnet.")) ?></td>
                        <td><?= h(t('Carnet chèques 2026')) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Réglé')) ?></strong></td>
                        <td><?= h(t('Par défaut, un nouveau document est non réglé. Cochez cette case lorsque le paiement est effectivement encaissé. La date de règlement est alors enregistrée automatiquement.')) ?></td>
                        <td><?= h(t('Non réglé')) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Date de règlement')) ?></strong></td>
                        <td><?= h(t("Date à laquelle le paiement a été encaissé (format jj/mm/aaaa). Elle est utilisée dans les statistiques par mois de règlement et ne peut pas précéder la date d'émission.")) ?></td>
                        <td class="mono">20/11/2026</td>
                    </tr>
                    <tr>
                        <td><strong><?= h(t('Remarque')) ?></strong></td>
                        <td><?= h(t('Une note interne libre (acompte, dossier, numéro de facture…).')) ?></td>
                        <td><?= h(t('Acompte dossier n° 12')) ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card guide-card">
            <h2><?= h(t('4. Statuts et alertes')) ?></h2>
            <div class="status-grid">
                <div class="status-box">
                    <span class="badge badge-late"><?= h(t('En retard')) ?></span>
                    <p><?= h(t("Document non réglé dont la date d'échéance est déjà dépassée.")) ?></p>
                </div>
                <div class="status-box">
                    <span class="badge badge-soon"><?= h(t('À échéance')) ?></span>
                    <p><?= h(t("Document non réglé dont l'échéance arrive dans les 7 ou 30 prochains jours.")) ?></p>
                </div>
                <div class="status-box">
                    <span class="badge badge-valid"><?= h(t('Réglé')) ?></span>
                    <p><?= h(t('Le paiement a été encaissé. La date de règlement est enregistrée et le document sort des relances.')) ?></p>
                </div>
                <div class="status-box">
                    <span class="badge badge-neutral"><?= h(t('Non réglé')) ?></span>
                    <p><?= h(t("Document dont le paiement n'a pas encore été encaissé. C'est le statut par défaut à la création.")) ?></p>
                </div>
                <div class="status-box">
                    <span class="badge badge-invalid"><?= h(t('Rejeté')) ?></span>
                    <p><?= h(t('Document impayé ou refusé par la banque. Un document rejeté ne peut pas être réglé en même temps.')) ?></p>
                </div>
                <div class="status-box">
                    <span class="badge badge-cheque"><?= h(t('Réglé / Rejeté')) ?></span>
                    <p><?= h(t('Les statuts « réglé » et « rejeté » sont mutuellement exclusifs : activer l\'un désactive l\'autre.')) ?></p>
                </div>
            </div>
            <p class="guide-note"><?= h(t('Les jours restants sont calculés automatiquement : positifs avant l\'échéance, à zéro le jour même, négatifs après l\'échéance.')) ?></p>
        </section>

        <section class="card guide-card">
            <h2><?= h(t('5. Workflow recommandé')) ?></h2>
            <ol class="steps">
                <li><strong><?= h(t('Créer le bénéficiaire / tiers')) ?></strong> <?= h(t("s'il n'existe pas encore : bouton « Nouveau » dans le formulaire, ou page « Bénéficiaires ».")) ?></li>
                <li><strong><?= h(t('Créer la personne responsable')) ?></strong> <?= h(t("s'il n'existe pas encore : bouton « + Nouveau » dans le formulaire, ou page « Personnes responsables ».")) ?></li>
                <li><strong><?= h(t('Créer le carnet / registre')) ?></strong> <?= h(t("s'il n'existe pas encore : bouton « + Nouveau » dans le formulaire, ou page « Carnets & registres ».")) ?></li>
                <li><strong><?= h(t('Enregistrer le document')) ?></strong> <?= h(t('type, carnet, numéro, bénéficiaire, montant, personne responsable, dates et banque.')) ?></li>
                <li><strong><?= h(t('Contrôler le récapitulatif')) ?></strong> <?= h(t("à droite avant d'enregistrer, surtout la date d'échéance et le carnet.")) ?></li>
                <li><strong><?= h(t('Suivre')) ?></strong> <?= h(t('les échéances depuis le tableau de bord et la liste filtrée.')) ?></li>
                <li><strong><?= h(t('Marquer réglé')) ?></strong> <?= h(t("dès l'encaissement : cochez « Réglé » dans la liste ou dans le formulaire, et vérifiez la date de règlement.")) ?></li>
                <li><strong><?= h(t('Analyser')) ?></strong> <?= h(t('les montants payés / non payés, par mois, par carnet ou par bénéficiaire depuis la page « Statistiques ».')) ?></li>
                <li><strong><?= h(t('Exporter en PDF')) ?></strong> <?= h(t('fiche du document ou listing filtré, avec le montant en lettres.')) ?></li>
            </ol>
        </section>

        <section class="card guide-card">
            <h2><?= h(t('6. Règles métier à retenir')) ?></h2>
            <ul class="rules-list">
                <li><?= h(t('Le numéro est unique au sein d\'un même carnet / registre.')) ?></li>
                <li><?= h(t("La date d'échéance ne peut pas précéder la date d'émission.")) ?></li>
                <li><?= h(t('Le montant doit être strictement supérieur à 0.')) ?></li>
                <li><?= h(t('Un bénéficiaire lié à des documents ne peut pas être supprimé.')) ?></li>
                <li><?= h(t('Une personne responsable liée à des documents ne peut pas être supprimée.')) ?></li>
                <li><?= h(t('Chaque document doit être rattaché à un carnet dont le type correspond (chèque ou effet).')) ?></li>
                <li><?= h(t('Un carnet lié à des documents ne peut pas être supprimé.')) ?></li>
                <li><?= h(t('Un document est non réglé par défaut ; la date de règlement est automatique à l\'encaissement.')) ?></li>
                <li><?= h(t('Les PDF sont générés à la volée et ne sont pas stockés sur le disque.')) ?></li>
            </ul>
        </section>

        <section class="card guide-card">
            <h2><?= h(t('7. Questions fréquentes')) ?></h2>
            <details class="faq">
                <summary><?= h(t('Quelle est la différence entre « Bénéficiaire / Tiers » et « Personne responsable » ?')) ?></summary>
                <p><?= h(t("Le champ « Bénéficiaire / Tiers » désigne la personne ou la société qui vous remet le document (votre client). Le champ « Personne responsable » désigne la personne de votre société (propriétaire, gérant ou employé) qui suit ce document en interne.")) ?></p>
            </details>
            <details class="faq">
                <summary><?= h(t("Que se passe-t-il si j'enregistre un numéro déjà existant ?")) ?></summary>
                <p><?= h(t("L'application refuse le doublon si le numéro existe déjà dans le même carnet / registre. Vous pouvez réutiliser le même numéro dans un autre carnet.")) ?></p>
            </details>
            <details class="faq">
                <summary><?= h(t("Pourquoi un document n'apparaît-il pas « en retard » ?")) ?></summary>
                <p><?= h(t("Le statut « en retard » s'applique uniquement aux documents non réglés dont la date d'échéance est dépassée. Marquez le document comme réglé dès l'encaissement pour le retirer des relances.")) ?></p>
            </details>
            <details class="faq">
                <summary><?= h(t("Puis-je modifier un document après l'avoir créé ?")) ?></summary>
                <p><?= h(t('Oui. Depuis la liste, cliquez sur l\'icône de modification. Le formulaire se préremplit avec les valeurs existantes. Le PDF sera régénéré avec les nouvelles informations.')) ?></p>
            </details>
            <details class="faq">
                <summary><?= h(t('Comment retrouver rapidement un document ?')) ?></summary>
                <p><?= h(t('Utilisez la recherche (numéro, bénéficiaire, personne, banque, carnet, remarque) et les filtres : type, règlement, bénéficiaire, carnet, période d\'émission, période d\'échéance et montant minimum / maximum.')) ?></p>
            </details>
            <details class="faq">
                <summary><?= h(t('À quoi sert le carnet / registre ?')) ?></summary>
                <p><?= h(t("Il indique l'origine physique du document : le carnet de chèques ou le registre d'effets dans lequel il a été puisé. Vous pouvez ainsi savoir de quel carnet provient chaque chèque ou effet, suivre la consommation d'un carnet et filtrer les statistiques par carnet.")) ?></p>
            </details>
            <details class="faq">
                <summary><?= h(t('Comment marquer un document comme payé ?')) ?></summary>
                <p><?= h(t('Dans la liste, utilisez l\'interrupteur de la colonne Réglé. Dans le formulaire, cochez « Document réglé (payé) » : la date de règlement est préremplie avec la date du jour et reste modifiable. Le document est alors compté dans les montants réglés et retiré des relances.')) ?></p>
            </details>
            <details class="faq">
                <summary><?= h(t('Que trouve-t-on dans la page Statistiques ?')) ?></summary>
                <p><?= h(t("Les totaux payés / non payés, la répartition chèques / effets, les montants en retard, l'évolution mensuelle (par émission, échéance ou règlement), ainsi que les répartitions par carnet, par bénéficiaire et par banque. Tous les filtres de la page s'appliquent à l'ensemble des indicateurs.")) ?></p>
            </details>
        </section>
    </div>

    <aside class="guide-side">
        <div class="card side-card">
            <div class="side-title"><?= h(t('À retenir')) ?></div>
            <ul class="checklist">
                <li><?= h(t('Un numéro est unique dans un carnet.')) ?></li>
                <li><?= h(t("L'échéance ne précède jamais l'émission.")) ?></li>
                <li><?= h(t('« Réglé » suit l\'encaissement effectif.')) ?></li>
                <li><?= h(t('Chaque document appartient à un carnet et à une personne responsable.')) ?></li>
                <li><?= h(t('Le PDF reprend le montant en lettres.')) ?></li>
            </ul>
        </div>

        <div class="card side-card">
            <div class="side-title"><?= h(t('Raccourcis')) ?></div>
            <a href="<?= h(url('documents/form.php')) ?>"><?= h(t('Nouveau document')) ?></a>
            <a href="<?= h(url('documents/index.php')) ?>"><?= h(t('Liste des documents')) ?></a>
            <a href="<?= h(url('beneficiaires/index.php')) ?>"><?= h(t('Gérer les bénéficiaires')) ?></a>
            <a href="<?= h(url('personnes/index.php')) ?>"><?= h(t('Personnes responsables')) ?></a>
            <a href="<?= h(url('carnets/index.php')) ?>"><?= h(t('Gérer les carnets')) ?></a>
            <a href="<?= h(url('statistiques/index.php')) ?>"><?= h(t('Statistiques détaillées')) ?></a>
            <a href="<?= h(url('calendrier/index.php')) ?>"><?= h(t('Calendrier de distribution')) ?></a>
            <a href="<?= h(url('index.php')) ?>"><?= h(t('Tableau de bord')) ?></a>
        </div>

        <div class="card side-card side-tip">
            <div class="side-title"><?= h(t('Conseil')) ?></div>
            <p><?= h(t('Renseignez toujours la banque et la personne responsable : ces informations apparaissent sur la fiche PDF et facilitent le rapprochement bancaire.')) ?></p>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
