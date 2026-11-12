<?php
declare(strict_types=1);

/**
 * Configuration du module « Gestion Chèques & Effets ».
 *
 * Le module s'appuie sur le socle unifié : connexion, session et
 * authentification sont partagées avec Stock et Crédits.
 */

date_default_timezone_set(getenv('APP_TZ') ?: 'Africa/Casablanca');

$gosRoot = dirname(__DIR__, 3);
require_once $gosRoot . '/shared/bootstrap.php';

define('APP_NAME', 'Gestion Chèques & Effets');
define('APP_VERSION', '1.0');
define('APP_ROOT', dirname(__DIR__));

/* ------------------------------------------------------------------ */
/* URL de base du module                                               */
/* ------------------------------------------------------------------ */
define('BASE_URL', rtrim(sh_module_base('cheques'), '/') . '/');

/* ------------------------------------------------------------------ */
/* Base de données : base unique partagée                              */
/* ------------------------------------------------------------------ */
define('DB_HOST', (string) sh_config('db.host', '127.0.0.1'));
define('DB_PORT', (string) sh_config('db.port', '3306'));
define('DB_NAME', (string) sh_config('db.name', 'gestion_our_stock'));
define('DB_USER', (string) sh_config('db.user', 'root'));
define('DB_PASS', (string) sh_config('db.pass', ''));

/* ------------------------------------------------------------------ */
/* E-mail (SMTP) — laisser vide pour simuler et journaliser les envois  */
/* ------------------------------------------------------------------ */
define('MAIL_HOST', getenv('MAIL_HOST') ?: '');
define('MAIL_PORT', (int) (getenv('MAIL_PORT') ?: 587));
define('MAIL_USER', getenv('MAIL_USER') ?: '');
define('MAIL_PASS', getenv('MAIL_PASS') ?: '');
define('MAIL_SECURE', getenv('MAIL_SECURE') ?: 'tls'); // tls | ssl | none
define('MAIL_FROM', getenv('MAIL_FROM') ?: (MAIL_USER !== '' ? MAIL_USER : 'no-reply@gestioncheques.ma'));
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

/* ------------------------------------------------------------------ */
/* SMS Twilio — laisser vide pour simuler                              */
/* ------------------------------------------------------------------ */
define('TWILIO_SID', getenv('TWILIO_SID') ?: '');
define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
define('TWILIO_FROM', getenv('TWILIO_FROM') ?: '');

/* ------------------------------------------------------------------ */
/* Divers                                                              */
/* ------------------------------------------------------------------ */
define('CRON_TOKEN', getenv('CRON_TOKEN') ?: 'change-me-cron-token');
define('APP_DEBUG', (bool) sh_config('app.debug', false));
// La session est celle du socle (gos_sid).
define('SESSION_NAME', 'gos_sid');

/* ------------------------------------------------------------------ */
/* Sécurité de session et de connexion                                 */
/* ------------------------------------------------------------------ */
define('SESSION_IDLE_TIMEOUT', max(300, (int) (getenv('SESSION_IDLE_TIMEOUT') ?: 1800)));
define('SESSION_ABSOLUTE_TIMEOUT', max(3600, (int) (getenv('SESSION_ABSOLUTE_TIMEOUT') ?: 28800)));
define('SESSION_REGENERATE_INTERVAL', max(300, (int) (getenv('SESSION_REGENERATE_INTERVAL') ?: 900)));
define('LOGIN_MAX_ATTEMPTS', max(3, (int) (getenv('LOGIN_MAX_ATTEMPTS') ?: 5)));
define('LOGIN_WINDOW_SECONDS', max(60, (int) (getenv('LOGIN_WINDOW_SECONDS') ?: 900)));
define('LOGIN_LOCK_SECONDS', max(60, (int) (getenv('LOGIN_LOCK_SECONDS') ?: 900)));

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
