<?php
declare(strict_types=1);

/**
 * Client SMTP minimaliste, sans dépendance externe.
 *
 * Retourne toujours un tableau :
 *   ['success' => bool, 'simulated' => bool, 'error' => ?string]
 *
 * Sans MAIL_HOST configuré, l'envoi est simulé (aucune connexion réseau).
 */
final class Mailer
{
    /** @return array{success: bool, simulated: bool, error: ?string} */
    public static function send(string $to, string $subject, string $body): array
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'simulated' => false, 'error' => 'Adresse email invalide : ' . $to];
        }

        if (MAIL_HOST === '') {
            self::logSimulation($to, $subject);
            return ['success' => true, 'simulated' => true, 'error' => null];
        }

        $secure = strtolower(MAIL_SECURE);
        $transport = $secure === 'ssl' ? 'ssl://' : '';
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($transport . MAIL_HOST, MAIL_PORT, $errno, $errstr, 15);
        if (!is_resource($socket)) {
            return ['success' => false, 'simulated' => false, 'error' => 'Connexion SMTP impossible : ' . ($errstr !== '' ? $errstr : ('erreur ' . $errno))];
        }

        stream_set_timeout($socket, 15);
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';

        try {
            self::expect($socket, [220]);

            $ehlo = self::command($socket, 'EHLO ' . $host, [250]);

            if ($secure === 'tls') {
                if (!self::supportsStartTls($ehlo)) {
                    throw new RuntimeException('Le serveur SMTP ne propose pas STARTTLS.');
                }
                self::command($socket, 'STARTTLS', [220]);
                $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (!@stream_socket_enable_crypto($socket, true, $method)) {
                    throw new RuntimeException('Impossible de démarrer le chiffrement TLS.');
                }
                self::command($socket, 'EHLO ' . $host, [250]);
            }

            if (MAIL_USER !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode(MAIL_USER), [334]);
                self::command($socket, base64_encode(MAIL_PASS), [235]);
            }

            $from = MAIL_FROM !== '' ? MAIL_FROM : MAIL_USER;
            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $data = self::buildMessage($from, $to, $subject, $body);
            $data = preg_replace('/^\./m', '..', $data) ?? $data;
            fwrite($socket, $data . "\r\n.\r\n");
            self::expect($socket, [250]);

            self::command($socket, 'QUIT', [221], false);
            fclose($socket);

            return ['success' => true, 'simulated' => false, 'error' => null];
        } catch (Throwable $e) {
            if (is_resource($socket)) {
                @fclose($socket);
            }
            return ['success' => false, 'simulated' => false, 'error' => $e->getMessage()];
        }
    }

    private static function buildMessage(string $from, string $to, string $subject, string $body): string
    {
        $fromName = MAIL_FROM_NAME !== '' ? MAIL_FROM_NAME : APP_NAME;
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        $encodedBody = chunk_split(base64_encode($body), 76, "\r\n");

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $encodedName . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'gestioncheques.local') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: GC-PHP',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;
    }

    /** @return string Réponse complète du EHLO. */
    private static function command($socket, string $command, array $expected, bool $throw = true): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $expected, $throw);
    }

    /** @return string */
    private static function expect($socket, array $expected, bool $throw = true): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        if ($response === '') {
            if ($throw) {
                throw new RuntimeException('Le serveur SMTP a fermé la connexion.');
            }
            return '';
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            if ($throw) {
                throw new RuntimeException('Réponse SMTP inattendue (' . $code . ') : ' . trim($response));
            }
        }

        return $response;
    }

    private static function supportsStartTls(string $ehlo): bool
    {
        return stripos($ehlo, 'STARTTLS') !== false;
    }

    private static function logSimulation(string $to, string $subject): void
    {
        @file_put_contents(
            simulation_log_file('mail-simule.log'),
            '[' . date('Y-m-d H:i:s') . '] À: ' . $to . ' | Sujet: ' . $subject . PHP_EOL,
            FILE_APPEND
        );
    }
}
