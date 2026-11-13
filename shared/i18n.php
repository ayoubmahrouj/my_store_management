<?php
declare(strict_types=1);

/**
 * Traductions du socle commun : coquille (sidebar, topbar), page d'accueil et
 * page de connexion. Le contenu des modules garde ses propres fichiers de
 * langue (credits/inc/lang.php, cheques/assets/i18n/ar.json).
 *
 * Deux clés de session coexistent pour rester compatible avec l'existant :
 *   - $_SESSION['locale'] : socle + module Crédits
 *   - $_SESSION['lang']   : module Chèques (historique)
 * sh_set_locale() les maintient synchronisées ; sh_locale() accepte l'une
 * ou l'autre en lecture.
 */

function sh_locale(): string
{
    $locale = $_SESSION['locale'] ?? $_SESSION['lang'] ?? 'fr';
    return $locale === 'ar' ? 'ar' : 'fr';
}

/**
 * Fixe la langue courante pour tout le socle et tous les modules.
 */
function sh_set_locale(string $locale): void
{
    $locale = $locale === 'ar' ? 'ar' : 'fr';
    $_SESSION['locale'] = $locale;
    $_SESSION['lang'] = $locale;
}

function sh_is_rtl(): bool
{
    return sh_locale() === 'ar';
}

/** Nom natif d'une langue, pour le sélecteur FR/AR. */
function sh_locale_name(string $locale): string
{
    return $locale === 'ar' ? 'العربية' : 'Français';
}

function sh_t(string $key, array $replace = []): string
{
    static $lines = null;
    if ($lines === null) {
        $lines = [
            'fr' => require __DIR__ . '/lang/fr.php',
            'ar' => require __DIR__ . '/lang/ar.php',
        ];
    }

    $locale = sh_locale();
    $text = $lines[$locale][$key] ?? $lines['fr'][$key] ?? $key;
    foreach ($replace as $search => $value) {
        $text = str_replace(':' . $search, (string) $value, $text);
    }
    return $text;
}
