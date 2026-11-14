<?php
declare(strict_types=1);

/**
 * Traductions arabes — lot « Utilisateurs + Auth + Erreurs + Middlewares ».
 * La clé est le libellé français exact tel qu'il apparaît dans le code.
 */
return [
    // Vue users/index.php
    'Administration' => 'الإدارة',
    'Utilisateurs & rôles' => 'المستخدمون والأدوار',
    'Gérez les accès, les rôles et le site de rattachement de chaque opérateur.' => 'إدارة الوصول والأدوار وموقع ارتباط كل مستخدم.',
    'Nouvel utilisateur' => 'مستخدم جديد',
    'Comptes' => 'الحسابات',
    ':count utilisateur(s)' => ':count مستخدم',
    'Utilisateur' => 'مستخدم',
    'Login' => 'المعرف',
    'Rôle' => 'الدور',
    'Site' => 'الموقع',
    'Statut' => 'الحالة',
    'Actions' => 'إجراءات',
    'Aucun utilisateur.' => 'لا يوجد مستخدم.',
    'Vous' => 'أنت',
    'Tous les sites' => 'كل المواقع',
    'Actif' => 'نشط',
    'Inactif' => 'غير نشط',
    'Modifier' => 'تعديل',
    'Désactiver cet utilisateur ?' => 'تعطيل هذا المستخدم؟',
    'Réactiver cet utilisateur ?' => 'إعادة تنشيط هذا المستخدم؟',
    'Désactiver' => 'تعطيل',
    'Réactiver' => 'إعادة التنشيط',

    // Vue users/form.php
    'Modifier l’utilisateur' => 'تعديل المستخدم',
    'Le rôle détermine les actions autorisées. Un site de rattachement est optionnel.' => 'يحدد الدور الإجراءات المسموح بها. موقع الارتباط اختياري.',
    'Retour' => 'رجوع',
    'L’utilisateur n’a pas pu être enregistré.' => 'تعذر حفظ المستخدم.',
    'Identité & accès' => 'الهوية والوصول',
    'Informations du compte' => 'معلومات الحساب',
    'Édition' => 'تعديل',
    'Création' => 'إنشاء',
    'Nom complet' => 'الاسم الكامل',
    'E-mail' => 'البريد الإلكتروني',
    '— Sélectionner —' => '— اختر —',
    'Site de rattachement' => 'موقع الارتباط',
    'Mot de passe' => 'كلمة المرور',
    'Laisser vide pour conserver' => 'اتركه فارغًا للإبقاء',
    '8 caractères minimum' => '8 أحرف على الأقل',
    'Renseignez uniquement pour changer le mot de passe.' => 'املأه فقط لتغيير كلمة المرور.',
    'Compte actif' => 'حساب نشط',
    'Annuler' => 'إلغاء',
    'Enregistrer les modifications' => 'حفظ التعديلات',
    'Créer l’utilisateur' => 'إنشاء المستخدم',

    // Contrôleur Web UserController.php
    'Utilisateurs' => 'المستخدمون',
    'Utilisateur créé.' => 'تم إنشاء المستخدم.',
    'Utilisateur introuvable' => 'مستخدم غير موجود',
    'Vous ne pouvez pas désactiver votre propre compte.' => 'لا يمكنك تعطيل حسابك الخاص.',
    'Il doit rester au moins un administrateur actif.' => 'يجب أن يبقى مسؤول واحد على الأقل نشطًا.',
    'Utilisateur mis à jour.' => 'تم تحديث المستخدم.',
    'Utilisateur introuvable.' => 'مستخدم غير موجود.',
    'Statut de l’utilisateur modifié.' => 'تم تغيير حالة المستخدم.',
    'Le nom est obligatoire.' => 'الاسم إلزامي.',
    'Le login est obligatoire.' => 'المعرف إلزامي.',
    'L’adresse e-mail est invalide.' => 'البريد الإلكتروني غير صالح.',
    'Sélectionnez un rôle valide.' => 'اختر دورًا صالحًا.',
    'Sélectionnez un site actif.' => 'اختر موقعًا نشطًا.',
    'Le mot de passe est obligatoire.' => 'كلمة المرور إلزامية.',
    'Le mot de passe doit contenir au moins 8 caractères.' => 'يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل.',

    // Contrôleur API AuthApiController.php
    'Identifiants requis.' => 'بيانات الدخول مطلوبة.',
    'Le login et le mot de passe sont obligatoires.' => 'المعرف وكلمة المرور إلزاميان.',
    'Non authentifié.' => 'لم تتم المصادقة.',
    'Déconnecté.' => 'تم تسجيل الخروج.',

    // Middlewares
    'Authentification requise.' => 'المصادقة مطلوبة.',
    'Session expirée' => 'انتهت الجلسة',
    'Rechargez la page et réessayez.' => 'أعد تحميل الصفحة وحاول مرة أخرى.',
    'Retour à la connexion' => 'العودة إلى تسجيل الدخول',
    'Veuillez vous connecter pour continuer.' => 'يرجى تسجيل الدخول للمتابعة.',
    'Accès refusé' => 'تم رفض الوصول',
];
