<?php
declare(strict_types=1);

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Sms.php';

/**
 * Configuration, envoi et journalisation des rappels d'échéance.
 */
final class AlerteService
{
    public static function get_config(): array
    {
        return self::normalize(self::ensure_config());
    }

    /**
     * Valide et enregistre la configuration.
     *
     * @return array{ok: bool, error: ?string, config: array}
     */
    public static function save_config(array $input): array
    {
        $config = self::ensure_config();

        $active = !empty($input['active']);
        $frequence = (string) ($input['frequence'] ?? 'QUOTIDIEN');
        if (!in_array($frequence, ['QUOTIDIEN', 'HEBDOMADAIRE'], true)) {
            $frequence = 'QUOTIDIEN';
        }
        $canal = (string) ($input['canal'] ?? 'EMAIL');
        if (!in_array($canal, ['EMAIL', 'SMS', 'LES_DEUX'], true)) {
            $canal = 'EMAIL';
        }

        $heure = trim((string) ($input['heure_envoi'] ?? '08:00'));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $heure)) {
            return ['ok' => false, 'error' => t("Heure d'envoi invalide : utilisez le format HH:mm."), 'config' => self::normalize($config)];
        }

        $joursAvant = (int) ($input['jours_avant'] ?? 1);
        $joursAvant = max(1, min(7, $joursAvant));
        if ($frequence === 'QUOTIDIEN') {
            $joursAvant = 1;
        }

        $jourSemaine = (int) ($input['jour_semaine'] ?? 1);
        if ($jourSemaine < 1 || $jourSemaine > 7) {
            $jourSemaine = 1;
        }

        $emails = self::clean_list((string) ($input['emails'] ?? ''));
        $telephones = self::clean_list((string) ($input['telephones'] ?? ''));

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => t('Adresse email invalide :') . ' ' . $email, 'config' => self::normalize($config)];
            }
        }
        foreach ($telephones as $telephone) {
            if (mb_strlen(preg_replace('/\D+/', '', $telephone) ?? '') < 6) {
                return ['ok' => false, 'error' => t('Numéro de téléphone invalide :') . ' ' . $telephone, 'config' => self::normalize($config)];
            }
        }

        if ($active) {
            if (!$emails && !$telephones) {
                return ['ok' => false, 'error' => t('Ajoutez au moins un destinataire (email ou téléphone).'), 'config' => self::normalize($config)];
            }
            if (($canal === 'EMAIL' || $canal === 'LES_DEUX') && !$emails) {
                return ['ok' => false, 'error' => t('Le canal email est sélectionné : ajoutez au moins une adresse email.'), 'config' => self::normalize($config)];
            }
            if (($canal === 'SMS' || $canal === 'LES_DEUX') && !$telephones) {
                return ['ok' => false, 'error' => t('Le canal SMS est sélectionné : ajoutez au moins un numéro de téléphone.'), 'config' => self::normalize($config)];
            }
        }

        q(
            'UPDATE alerte_config
                SET active = ?, frequence = ?, canal = ?, jours_avant = ?, heure_envoi = ?,
                    jour_semaine = ?, emails = ?, telephones = ?, updated_at = NOW()
              WHERE id = 1',
            [
                $active ? 1 : 0,
                $frequence,
                $canal,
                $joursAvant,
                $heure . ':00',
                $jourSemaine,
                $emails ? implode(',', $emails) : null,
                $telephones ? implode(',', $telephones) : null,
            ]
        );

        audit('MODIFICATION', 'ALERTE', 1, 'Configuration des rappels mise à jour');

        return ['ok' => true, 'error' => null, 'config' => self::get_config()];
    }

    /** @return list<array<string, mixed>> */
    public static function logs(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return fetch_all('SELECT * FROM alerte_log ORDER BY envoye_at DESC, id DESC LIMIT ' . $limit);
    }

    /**
     * Envoie un message de test sur les canaux configurés.
     *
     * @return array{ok: bool, error: ?string, emails: int, sms: int, echecs: int, simule: bool, message: string}
     */
    public static function send_test(): array
    {
        $config = self::get_config();
        $emails = $config['emails'];
        $telephones = $config['telephones'];

        if (!$emails && !$telephones) {
            return ['ok' => false, 'error' => t('Ajoutez au moins un destinataire (email ou téléphone).'), 'emails' => 0, 'sms' => 0, 'echecs' => 0, 'simule' => false, 'message' => ''];
        }

        $sujet = t('Test des rappels') . ' — ' . APP_NAME;
        $message = t('Bonjour,') . "\n\n"
            . t("Ceci est un message de test envoyé depuis l'application de gestion des chèques et effets.") . "\n\n"
            . t('Si vous recevez ce message, la configuration des rappels est opérationnelle.') . "\n\n"
            . '— ' . APP_NAME;

        $emailsSent = 0;
        $smsSent = 0;
        $echecs = 0;
        $simule = false;

        if ($config['canal'] === 'EMAIL' || $config['canal'] === 'LES_DEUX') {
            foreach ($emails as $email) {
                $result = Mailer::send($email, $sujet, $message);
                self::log_send('EMAIL', $email, $sujet, $message, null, null, $result);
                if ($result['success']) {
                    $emailsSent++;
                    $simule = $simule || $result['simulated'];
                } else {
                    $echecs++;
                }
            }
        }

        if ($config['canal'] === 'SMS' || $config['canal'] === 'LES_DEUX') {
            $smsMessage = t('Test des rappels') . ' — ' . APP_NAME . '. ' . t('Configuration opérationnelle.');
            foreach ($telephones as $telephone) {
                $result = Sms::send($telephone, $smsMessage);
                self::log_send('SMS', $telephone, $sujet, $smsMessage, null, null, $result);
                if ($result['success']) {
                    $smsSent++;
                    $simule = $simule || $result['simulated'];
                } else {
                    $echecs++;
                }
            }
        }

        return [
            'ok' => $echecs === 0,
            'error' => $echecs > 0 ? t('Certains envois ont échoué. Consultez l’historique des envois.') : null,
            'emails' => $emailsSent,
            'sms' => $smsSent,
            'echecs' => $echecs,
            'simule' => $simule,
            'message' => t('Message de test traité.'),
        ];
    }

    /**
     * Déclenchement manuel : envoie immédiatement les rappels dus.
     *
     * @return array{documents: int, emails: int, sms: int, echecs: int, simule: bool, message: string}
     */
    public static function run_now(): array
    {
        $config = self::ensure_config();
        $result = self::send_reminders($config);
        q('UPDATE alerte_config SET derniere_execution = NOW() WHERE id = 1');
        return $result;
    }

    /**
     * Appelé par le planificateur (cron). Ne fait rien si la configuration
     * est inactive, si l'heure n'est pas atteinte ou si le rappel a déjà été
     * envoyé pour la période en cours.
     *
     * @return array{ok: bool, skipped: bool, message: string, documents?: int, emails?: int, sms?: int, echecs?: int}
     */
    public static function run_scheduled(): array
    {
        $config = self::ensure_config();
        if (!self::should_run($config)) {
            return ['ok' => true, 'skipped' => true, 'message' => t('Aucun rappel à envoyer pour le moment.')];
        }

        try {
            $result = self::send_reminders($config);
        } catch (Throwable $e) {
            error_log('Erreur rappels : ' . $e->getMessage());
            $result = ['documents' => 0, 'emails' => 0, 'sms' => 0, 'echecs' => 1, 'simule' => false, 'message' => $e->getMessage()];
        } finally {
            q('UPDATE alerte_config SET derniere_execution = NOW() WHERE id = 1');
        }

        return ['ok' => true, 'skipped' => false] + $result;
    }

    public static function should_run(array $config, ?DateTimeImmutable $now = null): bool
    {
        if (!bit_true($config['active'] ?? false)) {
            return false;
        }
        $now = $now ?? new DateTimeImmutable();
        $heure = substr((string) ($config['heure_envoi'] ?? '08:00'), 0, 5);
        if ($now->format('H:i') < $heure) {
            return false;
        }

        $last = !empty($config['derniere_execution']) ? new DateTimeImmutable((string) $config['derniere_execution']) : null;
        if ($last === null) {
            return true;
        }

        if (($config['frequence'] ?? 'QUOTIDIEN') === 'QUOTIDIEN') {
            return $last->format('Y-m-d') < $now->format('Y-m-d');
        }

        $targetDay = (int) ($config['jour_semaine'] ?? 1);
        if ((int) $now->format('N') !== $targetDay) {
            return false;
        }
        return $last < $now->modify('-6 days');
    }

    /**
     * @return array{documents: int, emails: int, sms: int, echecs: int, simule: bool, message: string}
     */
    private static function send_reminders(array $config): array
    {
        $horizon = ($config['frequence'] ?? 'QUOTIDIEN') === 'HEBDOMADAIRE'
            ? max(1, min(7, (int) ($config['jours_avant'] ?? 1)))
            : 1;

        $from = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $to = (new DateTimeImmutable($from))->modify('+' . ($horizon - 1) . ' days')->format('Y-m-d');

        $documents = fetch_all(
            'SELECT d.*, c.libelle AS carnet_libelle, c.code AS carnet_code,
                    b.nom AS beneficiaire_nom, p.nom AS personne_nom
               FROM document_paiement d
               JOIN carnet c ON c.id = d.carnet_id
               JOIN beneficiaire b ON b.id = d.beneficiaire_id
               LEFT JOIN personne p ON p.id = d.personne_id
              WHERE d.regle = 0 AND d.rejete = 0
                AND d.date_echeance BETWEEN ? AND ?
              ORDER BY d.date_echeance ASC, c.libelle ASC, d.numero ASC',
            [$from, $to]
        );

        $emails = self::split_list((string) ($config['emails'] ?? ''));
        $telephones = self::split_list((string) ($config['telephones'] ?? ''));
        $canal = (string) ($config['canal'] ?? 'EMAIL');

        $emailsSent = 0;
        $smsSent = 0;
        $echecs = 0;
        $simule = false;

        foreach ($documents as $doc) {
            $type = ($doc['type'] ?? '') === 'EFFET' ? t('Effet') : t('Chèque');
            $sujet = t("Rappel d'échéance") . ' — ' . $type . ' n° ' . $doc['numero'];
            $body = self::build_reminder_body($doc);

            if ($canal === 'EMAIL' || $canal === 'LES_DEUX') {
                foreach ($emails as $email) {
                    $result = Mailer::send($email, $sujet, $body);
                    self::log_send('EMAIL', $email, $sujet, $body, (int) $doc['id'], (string) $doc['numero'], $result);
                    if ($result['success']) {
                        $emailsSent++;
                        $simule = $simule || $result['simulated'];
                    } else {
                        $echecs++;
                    }
                }
            }

            if ($canal === 'SMS' || $canal === 'LES_DEUX') {
                $smsBody = t('Rappel') . ' : ' . mb_strtolower($type) . ' n° ' . $doc['numero']
                    . ' — ' . money($doc['montant']) . ' — ' . t('échéance') . ' ' . date_fr((string) $doc['date_echeance']) . '.';
                foreach ($telephones as $telephone) {
                    $result = Sms::send($telephone, $smsBody);
                    self::log_send('SMS', $telephone, $sujet, $smsBody, (int) $doc['id'], (string) $doc['numero'], $result);
                    if ($result['success']) {
                        $smsSent++;
                        $simule = $simule || $result['simulated'];
                    } else {
                        $echecs++;
                    }
                }
            }
        }

        return [
            'documents' => count($documents),
            'emails' => $emailsSent,
            'sms' => $smsSent,
            'echecs' => $echecs,
            'simule' => $simule,
            'message' => !$documents
                ? t('Aucune échéance à rappeler pour la période.')
                : count($documents) . ' ' . t('document(s) concerné(s).'),
        ];
    }

    private static function build_reminder_body(array $doc): string
    {
        $lines = [];
        $lines[] = t('Bonjour,');
        $lines[] = '';
        $lines[] = t('Rappel : un document de paiement arrive à échéance.');
        $lines[] = '';
        $lines[] = t('Type') . ' : ' . (($doc['type'] ?? '') === 'EFFET' ? t('Effet') : t('Chèque'));
        if (!empty($doc['carnet_libelle'])) {
            $lines[] = t('Carnet / registre') . ' : ' . carnet_label(['libelle' => $doc['carnet_libelle'], 'code' => $doc['carnet_code'] ?? null]);
        }
        $lines[] = t('Numéro') . ' : ' . $doc['numero'];
        $lines[] = t('Montant') . ' : ' . number_format((float) $doc['montant'], 2, ',', ' ') . ' DH';
        $lines[] = t("Date d'échéance") . ' : ' . date_fr((string) $doc['date_echeance']);
        $lines[] = t('Bénéficiaire') . ' : ' . ($doc['beneficiaire_nom'] ?? '—');
        if (!empty($doc['personne_nom'])) {
            $lines[] = t('Personne responsable') . ' : ' . $doc['personne_nom'];
        }
        if (!empty($doc['banque'])) {
            $lines[] = t('Banque') . ' : ' . $doc['banque'];
        }
        $lines[] = '';
        $lines[] = t('Merci de vérifier le règlement.');
        $lines[] = '';
        $lines[] = '— ' . APP_NAME;

        return implode("\n", $lines);
    }

    private static function log_send(
        string $canal,
        string $destinataire,
        ?string $sujet,
        ?string $message,
        ?int $documentId,
        ?string $documentNumero,
        array $result
    ): void {
        q(
            'INSERT INTO alerte_log
                (envoye_at, canal, destinataire, sujet, message, document_id, document_numero, succes, simule, erreur)
             VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $canal,
                mb_substr($destinataire, 0, 150),
                $sujet !== null ? mb_substr($sujet, 0, 255) : null,
                $message !== null ? mb_substr($message, 0, 2000) : null,
                $documentId,
                $documentNumero !== null ? mb_substr($documentNumero, 0, 50) : null,
                !empty($result['success']) ? 1 : 0,
                !empty($result['simulated']) ? 1 : 0,
                !empty($result['error']) ? mb_substr((string) $result['error'], 0, 1000) : null,
            ]
        );
    }

    private static function ensure_config(): array
    {
        $row = fetch_one('SELECT * FROM alerte_config WHERE id = 1');
        if ($row) {
            return $row;
        }

        q(
            "INSERT INTO alerte_config
                (id, active, frequence, canal, jours_avant, heure_envoi, jour_semaine, emails, telephones, derniere_execution, updated_at)
             VALUES (1, 0, 'QUOTIDIEN', 'EMAIL', 1, '08:00:00', 1, NULL, NULL, NULL, NOW())"
        );

        return fetch_one('SELECT * FROM alerte_config WHERE id = 1') ?? [];
    }

    private static function normalize(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 1),
            'active' => bit_true($row['active'] ?? false),
            'frequence' => ($row['frequence'] ?? 'QUOTIDIEN') === 'HEBDOMADAIRE' ? 'HEBDOMADAIRE' : 'QUOTIDIEN',
            'canal' => in_array(($row['canal'] ?? 'EMAIL'), ['EMAIL', 'SMS', 'LES_DEUX'], true) ? (string) $row['canal'] : 'EMAIL',
            'jours_avant' => max(1, min(7, (int) ($row['jours_avant'] ?? 1))),
            'heure_envoi' => substr((string) ($row['heure_envoi'] ?? '08:00:00'), 0, 5),
            'jour_semaine' => max(1, min(7, (int) ($row['jour_semaine'] ?? 1))),
            'emails' => self::split_list((string) ($row['emails'] ?? '')),
            'telephones' => self::split_list((string) ($row['telephones'] ?? '')),
            'derniere_execution' => $row['derniere_execution'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @return list<string> */
    private static function split_list(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }
        $parts = preg_split('/[,;\n\r]+/', $value) ?: [];
        $result = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($part, $result, true)) {
                $result[] = $part;
            }
        }
        return $result;
    }

    /** @return list<string> */
    private static function clean_list(string $value): array
    {
        return self::split_list($value);
    }
}
