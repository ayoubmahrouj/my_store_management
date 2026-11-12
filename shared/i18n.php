<?php
declare(strict_types=1);

/**
 * Traductions minimales du socle commun (le contenu des modules garde ses
 * propres fichiers de langue).
 */

function sh_locale(): string
{
    return $_SESSION['locale'] ?? 'fr';
}

function sh_is_rtl(): bool
{
    return sh_locale() === 'ar';
}

function sh_t(string $key, array $replace = []): string
{
    static $lines = null;
    if ($lines === null) {
        $lines = [
            'fr' => [
                'home' => 'Accueil',
                'dashboard' => 'Tableau de bord',
                'stock' => 'Stock',
                'credits' => 'Crédits & Ventes',
                'cheques' => 'Chèques & Effets',
                'logout' => 'Déconnexion',
                'login' => 'Connexion',
                'search' => 'Rechercher',
                'new' => 'Nouveau',
                'cancel' => 'Annuler',
                'save' => 'Enregistrer',
                'theme' => 'Thème',
                'menu' => 'Menu',
                'welcome' => 'Bienvenue',
                'quick_create' => 'Création rapide',
                'quick_create_hint' => 'Créez un nouvel élément sans quitter la page.',
                'loading' => 'Chargement…',
                'error' => 'Une erreur est survenue.',
                'access_denied' => 'Accès refusé.',
            ],
            'ar' => [
                'home' => 'الرئيسية',
                'dashboard' => 'لوحة التحكم',
                'stock' => 'المخزون',
                'credits' => 'الديون والمبيعات',
                'cheques' => 'الشيكات والكمبيالات',
                'logout' => 'تسجيل الخروج',
                'login' => 'تسجيل الدخول',
                'search' => 'بحث',
                'new' => 'جديد',
                'cancel' => 'إلغاء',
                'save' => 'حفظ',
                'theme' => 'المظهر',
                'menu' => 'القائمة',
                'welcome' => 'مرحبا',
                'quick_create' => 'إنشاء سريع',
                'quick_create_hint' => 'أنشئ عنصرا جديدا دون مغادرة الصفحة.',
                'loading' => 'جار التحميل…',
                'error' => 'حدث خطأ.',
                'access_denied' => 'تم رفض الوصول.',
            ],
        ];
    }

    $locale = sh_locale();
    $text = $lines[$locale][$key] ?? $lines['fr'][$key] ?? $key;
    foreach ($replace as $search => $value) {
        $text = str_replace(':' . $search, (string) $value, $text);
    }
    return $text;
}
