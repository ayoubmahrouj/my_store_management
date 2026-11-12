<?php
declare(strict_types=1);

/**
 * Envoi de SMS via l'API REST Twilio (cURL), ou simulation si Twilio n'est
 * pas configuré.
 */
final class Sms
{
    /** @return array{success: bool, simulated: bool, error: ?string} */
    public static function send(string $to, string $message): array
    {
        $to = trim($to);
        if ($to === '') {
            return ['success' => false, 'simulated' => false, 'error' => 'Numéro de téléphone vide.'];
        }

        if (TWILIO_SID === '' || TWILIO_TOKEN === '' || TWILIO_FROM === '') {
            @file_put_contents(
                simulation_log_file('sms-simule.log'),
                '[' . date('Y-m-d H:i:s') . '] À: ' . $to . ' | ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL,
                FILE_APPEND
            );
            return ['success' => true, 'simulated' => true, 'error' => null];
        }

        if (!function_exists('curl_init')) {
            return ['success' => false, 'simulated' => false, 'error' => 'Extension cURL indisponible.'];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_SID) . '/Messages.json';
        $payload = http_build_query([
            'From' => TWILIO_FROM,
            'To' => $to,
            'Body' => mb_substr($message, 0, 1500),
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERPWD        => TWILIO_SID . ':' . TWILIO_TOKEN,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'simulated' => false, 'error' => 'Erreur cURL : ' . $error];
        }
        if ($status < 200 || $status >= 300) {
            $decoded = json_decode((string) $response, true);
            $detail = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
            return ['success' => false, 'simulated' => false, 'error' => 'Twilio HTTP ' . $status . ($detail !== '' ? ' : ' . $detail : '')];
        }

        return ['success' => true, 'simulated' => false, 'error' => null];
    }
}
