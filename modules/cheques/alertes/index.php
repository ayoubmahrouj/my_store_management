<?php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';
require_login();
require_once __DIR__ . '/../lib/AlerteService.php';

$title = 'Alertes & rappels';
$active = 'alertes';

$config = AlerteService::get_config();
$logs = AlerteService::logs(100);
$personnes = fetch_all('SELECT id, nom, email, telephone, role FROM personne ORDER BY nom');

$jours = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$canalLabels = ['EMAIL' => 'Email', 'SMS' => 'SMS', 'LES_DEUX' => 'Email + SMS'];

// État des fournisseurs (sans exposer les secrets).
$smtpConfigured = MAIL_HOST !== '' && MAIL_USER !== '';
$smsConfigured = TWILIO_SID !== '' && TWILIO_TOKEN !== '' && TWILIO_FROM !== '';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= h(t('Alertes & rappels')) ?></h1>
        <p class="page-sub"><?= h(t('Recevez un rappel par email ou SMS avant chaque échéance.')) ?></p>
    </div>
    <div class="page-actions">
        <button type="submit" form="alerteForm" formaction="<?= h(url('alertes/test.php')) ?>" class="btn btn-ghost">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                <path d="M4 5h16v14H4z"/><path d="M4 7l8 6 8-6"/>
            </svg>
            <?= h(t('Envoyer un test')) ?>
        </button>
        <button type="submit" form="alerteForm" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
            <?= h(t('Enregistrer')) ?>
        </button>
    </div>
</div>

<form id="alerteForm" method="post" action="<?= h(url('alertes/save.php')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="alert-layout">
        <div class="card alert-main">
            <div class="form-section">
                <div class="form-section-title"><?= h(t('Planification')) ?></div>

                <div class="switch-row alert-switch">
                    <label class="switch">
                        <input type="checkbox" name="active" value="1"<?= $config['active'] ? ' checked' : '' ?>>
                        <span class="switch-track"></span>
                    </label>
                    <div>
                        <div class="switch-title"><?= h(t('Activer les rappels automatiques')) ?></div>
                        <div class="hint"><?= h(t('Le serveur vérifie chaque minute si un rappel doit être envoyé.')) ?></div>
                    </div>
                </div>

                <div class="form-grid mt-16">
                    <div class="field span-2">
                        <label><?= h(t('Fréquence')) ?></label>
                        <input type="hidden" name="frequence" id="frequenceInput" value="<?= h($config['frequence']) ?>">
                        <div class="segmented" id="frequenceSegmented">
                            <button type="button" class="segment<?= $config['frequence'] === 'QUOTIDIEN' ? ' active' : '' ?>" data-value="QUOTIDIEN"><?= h(t('Quotidien')) ?></button>
                            <button type="button" class="segment<?= $config['frequence'] === 'HEBDOMADAIRE' ? ' active' : '' ?>" data-value="HEBDOMADAIRE"><?= h(t('Hebdomadaire')) ?></button>
                        </div>
                        <span class="hint"><?= h(t('Quotidien : échéances de demain. Hebdomadaire : échéances des 7 prochains jours.')) ?></span>
                    </div>

                    <div class="field">
                        <label for="heureEnvoi"><?= h(t("Heure d'envoi")) ?></label>
                        <input id="heureEnvoi" name="heure_envoi" type="time" class="input" step="60"
                               value="<?= h($config['heure_envoi']) ?>" required>
                        <span class="hint"><?= h(t('Format HH:mm, heure locale du serveur.')) ?></span>
                    </div>

                    <div class="field" id="weeklyDayField">
                        <label for="jourSemaine"><?= h(t('Jour de la semaine')) ?></label>
                        <select id="jourSemaine" name="jour_semaine" class="select">
                            <?php foreach ($jours as $num => $label): ?>
                                <option value="<?= $num ?>"<?= $config['jour_semaine'] === $num ? ' selected' : '' ?>><?= h(t($label)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field" id="weeklyHorizonField">
                        <label for="joursAvant"><?= h(t('Horizon (jours)')) ?></label>
                        <select id="joursAvant" name="jours_avant" class="select">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <option value="<?= $i ?>"<?= $config['jours_avant'] === $i ? ' selected' : '' ?>><?= $i ?> <?= h(t('jour(s)')) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="field" id="dailyHorizonField">
                        <label><?= h(t('Horizon')) ?></label>
                        <div class="readonly-pill"><?= h(t('Demain (J+1)')) ?></div>
                    </div>

                    <div class="field span-2">
                        <label><?= h(t("Canaux d'envoi")) ?></label>
                        <input type="hidden" name="canal" id="canalInput" value="<?= h($config['canal']) ?>">
                        <div class="segmented three" id="canalSegmented">
                            <button type="button" class="segment<?= $config['canal'] === 'EMAIL' ? ' active' : '' ?>" data-value="EMAIL"><?= h(t('Email')) ?></button>
                            <button type="button" class="segment<?= $config['canal'] === 'SMS' ? ' active' : '' ?>" data-value="SMS"><?= h(t('SMS')) ?></button>
                            <button type="button" class="segment<?= $config['canal'] === 'LES_DEUX' ? ' active' : '' ?>" data-value="LES_DEUX"><?= h(t('Email + SMS')) ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title"><?= h(t('Destinataires')) ?></div>

                <div class="form-grid">
                    <div class="field span-2">
                        <label for="emailsText"><?= h(t('Adresses email')) ?></label>
                        <input id="emailsText" name="emails" type="text" class="input"
                               value="<?= h(implode(', ', $config['emails'])) ?>"
                               placeholder="compta@exemple.ma, direction@exemple.ma">
                        <span class="hint"><?= h(t('Séparez plusieurs adresses par des virgules.')) ?></span>
                        <div class="chips" id="emailChips"></div>
                    </div>

                    <div class="field span-2">
                        <label for="telephonesText"><?= h(t('Numéros de téléphone (SMS)')) ?></label>
                        <input id="telephonesText" name="telephones" type="text" class="input"
                               value="<?= h(implode(', ', $config['telephones'])) ?>"
                               placeholder="+212 6 00 00 00 00, +212 6 11 11 11 11">
                        <span class="hint"><?= h(t('Format international recommandé, séparés par des virgules.')) ?></span>
                        <div class="chips" id="phoneChips"></div>
                    </div>

                    <div class="field span-2">
                        <label for="personnePick"><?= h(t('Ajouter depuis une personne')) ?></label>
                        <div class="carnet-select-row">
                            <select id="personnePick" class="select" data-quick="personne">
                                <option value=""><?= h(t('— Choisir une personne —')) ?></option>
                                <?php foreach ($personnes as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"
                                            data-email="<?= h($p['email'] ?? '') ?>"
                                            data-telephone="<?= h($p['telephone'] ?? '') ?>">
                                        <?= h($p['nom']) ?><?= !empty($p['email']) ? ' · ' . h($p['email']) : '' ?><?= !empty($p['telephone']) ? ' · ' . h($p['telephone']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-ghost btn-sm" id="addPersonneBtn"><?= h(t('+ Ajouter')) ?></button>
                        </div>
                        <span class="hint"><?= h(t("Ajoute l'email et le téléphone de la personne sélectionnée aux destinataires.")) ?></span>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" formaction="<?= h(url('alertes/run.php')) ?>" class="btn btn-ghost">
                    <?= h(t('Lancer les rappels maintenant')) ?>
                </button>
                <button type="submit" class="btn btn-primary"><?= h(t('Enregistrer la configuration')) ?></button>
            </div>
        </div>

        <aside class="alert-side">
            <div class="card side-card">
                <div class="side-title"><?= h(t('État actuel')) ?></div>
                <div class="status-line">
                    <span class="status-dot<?= $config['active'] ? ' on' : '' ?>"></span>
                    <?= h($config['active'] ? t('Rappels activés') : t('Rappels désactivés')) ?>
                </div>
                <div class="summary-list">
                    <div class="summary-row">
                        <span><?= h(t('Fréquence')) ?></span>
                        <span><?= h($config['frequence'] === 'HEBDOMADAIRE' ? t('Hebdomadaire') : t('Quotidien')) ?></span>
                    </div>
                    <div class="summary-row">
                        <span><?= h(t('Canal')) ?></span>
                        <span><?= h(t($canalLabels[$config['canal']] ?? $config['canal'])) ?></span>
                    </div>
                    <div class="summary-row">
                        <span><?= h(t('Heure')) ?></span>
                        <span class="mono"><?= h($config['heure_envoi']) ?></span>
                    </div>
                    <div class="summary-row">
                        <span><?= h(t('Dernière exécution')) ?></span>
                        <span><?= h($config['derniere_execution'] ? datetime_fr((string) $config['derniere_execution']) : t('Jamais')) ?></span>
                    </div>
                </div>
            </div>

            <div class="card side-card side-tip">
                <div class="side-title"><?= h(t('Configuration des fournisseurs')) ?></div>
                <p><?= h(t("Sans identifiants SMTP ou Twilio, les envois sont simulés et tracés dans l'historique. Configurez les variables d'environnement du serveur pour activer les envois réels.")) ?></p>
                <ul class="mini-list">
                    <li>
                        SMTP :
                        <span class="badge <?= $smtpConfigured ? 'badge-valid' : 'badge-neutral' ?>">
                            <?= h($smtpConfigured ? t('Configuré') : t('Simulation')) ?>
                        </span>
                    </li>
                    <li>
                        Twilio :
                        <span class="badge <?= $smsConfigured ? 'badge-valid' : 'badge-neutral' ?>">
                            <?= h($smsConfigured ? t('Configuré') : t('Simulation')) ?>
                        </span>
                    </li>
                    <li><code>MAIL_HOST</code>, <code>MAIL_PORT</code>, <code>MAIL_USER</code>, <code>MAIL_PASS</code></li>
                    <li><code>TWILIO_SID</code>, <code>TWILIO_TOKEN</code>, <code>TWILIO_FROM</code></li>
                </ul>
            </div>

            <div class="card side-card side-tip">
                <div class="side-title"><?= h(t('Planification serveur')) ?></div>
                <p><?= h(t('Exécutez le cron chaque minute (ou au moins une fois par heure) pour que les rappels partent automatiquement.')) ?></p>
                <ul class="mini-list">
                    <li><code>php alertes/cron.php</code></li>
                    <li><?= h(t('Windows : Planificateur de tâches')) ?></li>
                    <li><?= h(t('Linux : crontab')) ?> <code>* * * * *</code></li>
                </ul>
            </div>
        </aside>
    </div>
</form>

<div class="card logs-card">
    <div class="logs-head">
        <div>
            <div class="form-section-title"><?= h(t('Historique des envois')) ?></div>
            <p class="hint"><?= h(t('Les 100 derniers envois, tests compris.')) ?></p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= h(url('alertes/index.php')) ?>"><?= h(t('Rafraîchir')) ?></a>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th><?= h(t('Date')) ?></th>
                <th><?= h(t('Canal')) ?></th>
                <th><?= h(t('Destinataire')) ?></th>
                <th><?= h(t('Document')) ?></th>
                <th><?= h(t('Statut')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state compact">
                            <div class="empty-title"><?= h(t('Aucun envoi pour le moment')) ?></div>
                            <div class="empty-text"><?= h(t("L'historique apparaîtra après le premier rappel ou test.")) ?></div>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="nowrap mono"><?= h(datetime_fr((string) $log['envoye_at'])) ?></td>
                        <td><span class="badge badge-neutral"><?= h(t($canalLabels[$log['canal']] ?? (string) $log['canal'])) ?></span></td>
                        <td class="mono"><?= h($log['destinataire']) ?></td>
                        <td>
                            <?php if (!empty($log['document_numero'])): ?>
                                <div class="cell-main">N° <?= h($log['document_numero']) ?></div>
                            <?php else: ?>
                                <span class="muted"><?= h(t('Test')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (bit_true($log['succes'])): ?>
                                <span class="badge badge-valid">
                                    <?= h(t('Envoyé')) ?><?= bit_true($log['simule']) ? ' (' . h(t('simulé')) . ')' : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-late" title="<?= h($log['erreur'] ?? '') ?>"><?= h(t('Échec')) ?></span>
                                <?php if (!empty($log['erreur'])): ?>
                                    <div class="error-text mt-8"><?= h(str_limit((string) $log['erreur'], 90)) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
    (function ($) {
        function updateFrequency() {
            var weekly = $('#frequenceInput').val() === 'HEBDOMADAIRE';
            $('#weeklyDayField, #weeklyHorizonField').toggle(weekly);
            $('#dailyHorizonField').toggle(!weekly);
        }

        $('#frequenceSegmented').on('click', '.segment', function () {
            var value = $(this).data('value');
            $('#frequenceInput').val(value);
            $('#frequenceSegmented .segment').removeClass('active');
            $(this).addClass('active');
            if (value === 'QUOTIDIEN') {
                $('#joursAvant').val('1');
            } else if ($('#joursAvant').val() === '1') {
                $('#joursAvant').val('7');
            }
            updateFrequency();
        });

        $('#canalSegmented').on('click', '.segment', function () {
            $('#canalInput').val($(this).data('value'));
            $('#canalSegmented .segment').removeClass('active');
            $(this).addClass('active');
        });

        function splitList(value) {
            var seen = [];
            (value || '').split(/[,;\n\r]+/).forEach(function (item) {
                item = item.trim();
                if (item && seen.indexOf(item) === -1) { seen.push(item); }
            });
            return seen;
        }

        function renderChips(inputSelector, chipsSelector) {
            var $input = $(inputSelector);
            var $chips = $(chipsSelector);
            var list = splitList($input.val());
            $chips.empty();
            list.forEach(function (value, index) {
                var $chip = $('<span class="chip"></span>').text(value + ' ');
                var $remove = $('<button type="button" class="chip-remove" aria-label="Retirer">×</button>');
                $remove.on('click', function () {
                    var items = splitList($input.val());
                    items.splice(index, 1);
                    $input.val(items.join(', '));
                    renderChips(inputSelector, chipsSelector);
                });
                $chip.append($remove);
                $chips.append($chip);
            });
        }

        function refreshChips() {
            renderChips('#emailsText', '#emailChips');
            renderChips('#telephonesText', '#phoneChips');
        }

        $('#emailsText, #telephonesText').on('input change', refreshChips);

        function appendUnique(inputSelector, value) {
            if (!value) { return; }
            var $input = $(inputSelector);
            var items = splitList($input.val());
            if (items.map(function (v) { return v.toLowerCase(); }).indexOf(value.toLowerCase()) === -1) {
                items.push(value);
                $input.val(items.join(', '));
            }
        }

        $('#addPersonneBtn').on('click', function () {
            var $opt = $('#personnePick option:selected');
            if (!$opt.val()) { return; }
            var email = $opt.data('email') || '';
            var telephone = $opt.data('telephone') || '';
            if (!email && !telephone) {
                if (window.GCToast) { window.GCToast('error', <?= json_encode(t("Cette personne n'a ni email ni téléphone."), JSON_UNESCAPED_UNICODE) ?>); }
                return;
            }
            appendUnique('#emailsText', email);
            appendUnique('#telephonesText', telephone);
            refreshChips();
            $('#personnePick').val('');
        });

        updateFrequency();
        refreshChips();
    })(jQuery);
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
