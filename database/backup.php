<?php
declare(strict_types=1);

/**
 * Sauvegarde et restauration de la base de données (et des fichiers téléversés).
 *
 *   php database/backup.php               # sauvegarde la base + les téléversements
 *   php database/backup.php --list        # liste les sauvegardes disponibles
 *   php database/backup.php --restore FILE [--yes]   # restaure une sauvegarde
 *   php database/backup.php --prune DAYS  # supprime les sauvegardes > DAYS jours
 *
 * Automatisation (cron) — un dump par nuit à 02h00 :
 *   0 2 * * * /usr/bin/php /home/compte/public_html/gestion/database/backup.php
 *
 * Sur Windows (Planificateur de tâches), même commande avec le php.exe complet.
 *
 * Sécurité : le mot de passe MySQL n'apparaît jamais dans la ligne de commande
 * (il est transmis via un fichier de configuration temporaire supprimé aussitôt).
 * Les sauvegardes sont stockées dans storage/backups/, inaccessible par le web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Sauvegarde accessible uniquement en ligne de commande.');
}

define('GOS_ROOT', dirname(__DIR__));
$GLOBALS['GOS_CONFIG'] = require GOS_ROOT . '/shared/config.php';
define('GOS_BASE', (string) ($GLOBALS['GOS_CONFIG']['app']['base'] ?? ''));
define('GOS_APP_NAME', (string) ($GLOBALS['GOS_CONFIG']['app']['name'] ?? 'Gestion Our Stock'));
define('GOS_VERSION', (string) ($GLOBALS['GOS_CONFIG']['app']['version'] ?? '1.0'));

require GOS_ROOT . '/shared/helpers.php';
require GOS_ROOT . '/shared/db.php';

function out(string $message): void
{
    echo $message . "\n";
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, 'Erreur : ' . $message . "\n");
    exit($code);
}

/* -------------------------------------------------------------------------
| Options
|-------------------------------------------------------------------------*/

$argv = $_SERVER['argv'] ?? [];
$args = array_slice($argv, 1);

$action = 'backup';
$restoreFile = null;
$yes = false;
$pruneDays = null;

for ($i = 0; $i < count($args); $i++) {
    $a = $args[$i];
    if ($a === '--list') {
        $action = 'list';
    } elseif ($a === '--restore') {
        $action = 'restore';
        $restoreFile = $args[++$i] ?? null;
    } elseif ($a === '--prune') {
        $pruneDays = (int) ($args[++$i] ?? 30);
    } elseif ($a === '--yes' || $a === '-y') {
        $yes = true;
    } elseif ($a === '--help' || $a === '-h') {
        echo "Usage:\n"
            . "  php database/backup.php                 Sauvegarde base + téléversements\n"
            . "  php database/backup.php --list          Liste les sauvegardes\n"
            . "  php database/backup.php --restore FILE --yes   Restaure une sauvegarde\n"
            . "  php database/backup.php --prune DAYS    Purge les sauvegardes de plus de DAYS jours\n";
        exit(0);
    } else {
        fail('Option inconnue : ' . $a);
    }
}

/* -------------------------------------------------------------------------
| Chemins et configuration
|-------------------------------------------------------------------------*/

$backupDir = getenv('BACKUP_DIR') ?: (GOS_ROOT . '/storage/backups');
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}
// Test d'écriture réel (plus fiable que is_writable() selon les plateformes).
$probe = $backupDir . '/.probe_' . uniqid();
$canWrite = @file_put_contents($probe, 'x') !== false;
if ($canWrite) {
    @unlink($probe);
}
if (!is_dir($backupDir) || !$canWrite) {
    fail("Le dossier de sauvegardes n'est pas inscriptible : {$backupDir}");
}

// Bloque l'accès web au dossier de sauvegardes.
$deny = $backupDir . '/.htaccess';
if (!is_file($deny)) {
    @file_put_contents(
        $deny,
        "# Sauvegardes : accès direct interdit.\n"
        . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
    );
}

$db = sh_config('db');

/* -------------------------------------------------------------------------
| Localisation de mysqldump / mysql
|-------------------------------------------------------------------------*/

function find_bin(string $name): string
{
    // 1. Surcharge explicite par variable d'environnement.
    $env = getenv(strtoupper($name) . '_BIN');
    if ($env !== false && is_file($env)) {
        return $env;
    }

    $names = DIRECTORY_SEPARATOR === '\\' ? [$name . '.exe', $name] : [$name];

    // 2. Recherche dans les dossiers de PATH.
    $dirs = explode(PATH_SEPARATOR, (string) getenv('PATH'));
    foreach ($dirs as $dir) {
        if ($dir === '') {
            continue;
        }
        foreach ($names as $n) {
            $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $n;
            if (is_file($candidate)) {
                return $candidate;
            }
        }
    }

    // 3. Emplacements d'installation courants.
    $candidates = [];
    if (DIRECTORY_SEPARATOR === '\\') {
        $candidates = [
            'C:\\wamp64\\bin\\mysql\\*\\bin\\' . $name . '.exe',
            'C:\\wamp\\bin\\mysql\\*\\bin\\' . $name . '.exe',
            'C:\\xampp\\mysql\\bin\\' . $name . '.exe',
            'C:\\Program Files\\MySQL\\*\\bin\\' . $name . '.exe',
            'C:\\Program Files\\MariaDB *\\bin\\' . $name . '.exe',
        ];
    } else {
        $candidates = [
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/usr/local/mysql/bin/' . $name,
            '/opt/lampp/bin/' . $name,
        ];
    }
    foreach ($candidates as $c) {
        $matches = glob($c);
        if (is_array($matches) && count($matches) > 0 && is_file($matches[0])) {
            return $matches[0];
        }
    }

    fail("Exécutable {$name} introuvable. Précisez " . strtoupper($name) . '_BIN.');
}

function write_mysql_defaults(string $dir, array $db): string
{
    $file = $dir . '/.mysql.cnf.tmp';
    $content = "[client]\n"
        . 'host=' . ($db['host'] ?? '127.0.0.1') . "\n"
        . 'port=' . ($db['port'] ?? '3306') . "\n"
        . 'user=' . ($db['user'] ?? 'root') . "\n"
        . 'password=' . ($db['pass'] ?? '') . "\n";
    @file_put_contents($file, $content);
    @chmod($file, 0600);
    return $file;
}

/* -------------------------------------------------------------------------
| Actions
|-------------------------------------------------------------------------*/

if ($action === 'list') {
    $files = glob($backupDir . '/db_*.sql') ?: [];
    sort($files);
    if ($files === []) {
        out('Aucune sauvegarde.');
        exit(0);
    }
    foreach ($files as $f) {
        $size = filesize($f) ?: 0;
        out(sprintf(
            '%s  %8.1f Mo  %s',
            date('Y-m-d H:i', (int) filemtime($f)),
            $size / 1048576,
            basename($f)
        ));
    }
    exit(0);
}

if ($action === 'prune') {
    $days = $pruneDays ?? 30;
    $cutoff = time() - ($days * 86400);
    $removed = 0;
    foreach (glob($backupDir . '/db_*.sql') ?: [] as $f) {
        if (filemtime($f) < $cutoff) {
            @unlink($f);
            $removed++;
        }
    }
    out("Purge terminée : {$removed} sauvegarde(s) supprimée(s) (plus de {$days} jours).");
    exit(0);
}

if ($action === 'restore') {
    if ($restoreFile === null || !is_file($restoreFile)) {
        fail('Fichier de sauvegarde introuvable : ' . (string) $restoreFile);
    }
    $name = (string) sh_config('db.name', 'gestion_our_stock');
    out("Restauration de « {$restoreFile} » vers la base « {$name} ».");
    if (!$yes) {
        out('Cette opération REMPLACE la base actuelle. Relancez avec --yes pour confirmer.');
        exit(0);
    }

    $mysql = find_bin('mysql');
    $cnf = write_mysql_defaults($backupDir, $db);
    $cmd = '"' . $mysql . '" --defaults-extra-file="' . $cnf . '" --default-character-set=utf8mb4 "' . $name . '" < "' . $restoreFile . '"';
    passthru($cmd, $code);
    @unlink($cnf);
    if ($code !== 0) {
        fail("La restauration a échoué (code {$code}).");
    }
    out('Restauration terminée.');
    exit(0);
}

/* -------------------------------------------------------------------------
| Sauvegarde (défaut)
|-------------------------------------------------------------------------*/

$mysqldump = find_bin('mysqldump');
$name = (string) sh_config('db.name', 'gestion_our_stock');
$stamp = date('Ymd_His');
$dumpFile = $backupDir . '/db_' . $stamp . '.sql';

$cnf = write_mysql_defaults($backupDir, $db);
$cmd = '"' . $mysqldump . '" --defaults-extra-file="' . $cnf . '"'
    . ' --single-transaction --default-character-set=utf8mb4'
    . ' --routines --events --skip-lock-tables'
    . ' --result-file="' . $dumpFile . '" "' . $name . '"';

passthru($cmd, $code);
@unlink($cnf);

if ($code !== 0 || !is_file($dumpFile) || filesize($dumpFile) === 0) {
    @unlink($dumpFile);
    fail('La sauvegarde a échoué. Vérifiez mysqldump et les identifiants MySQL.');
}

$sizeMb = round((filesize($dumpFile) ?: 0) / 1048576, 1);
out("Base sauvegardée : {$dumpFile} ({$sizeMb} Mo)");

// Sauvegarde des téléversements (photos de bons et de chèques).
$uploads = GOS_ROOT . '/storage/uploads';
if (is_dir($uploads)) {
    $zip = $backupDir . '/uploads_' . $stamp . '.zip';
    if (class_exists('ZipArchive')) {
        $za = new ZipArchive();
        if ($za->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $local = substr($file->getPathname(), strlen($uploads) + 1);
                $za->addFile($file->getPathname(), 'uploads/' . $local);
            }
            $za->close();
            $zMb = round((filesize($zip) ?: 0) / 1048576, 1);
            out("Téléversements sauvegardés : {$zip} ({$zMb} Mo)");
        }
    } else {
        out('(Extension ZipArchive absente : téléversements non archivés.)');
    }
}

// Purge automatique (défaut 30 jours, surcharge BACKUP_KEEP_DAYS).
$keep = (int) (getenv('BACKUP_KEEP_DAYS') ?: 30);
$cutoff = time() - ($keep * 86400);
foreach (glob($backupDir . '/db_*.sql') ?: [] as $f) {
    if (filemtime($f) < $cutoff) {
        @unlink($f);
    }
}

out('Sauvegarde terminée.');
