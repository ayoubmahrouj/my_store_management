# Gestion Chèques & Effets — version PHP

Application de gestion des chèques et effets, réécrite en **PHP natif + Bootstrap + jQuery + CSS**, sans Spring Boot ni Angular.

> Installation détaillée :
> - avec **WAMP** : [`INSTALLATION.md`](INSTALLATION.md)
> - avec **XAMPP** : [`INSTALLATION_XAMPP.md`](INSTALLATION_XAMPP.md)

## Accès

- URL locale : <http://127.0.0.1:8001/>
- Identifiant : `admin`
- Mot de passe initial : `admin123` — **à changer immédiatement** dans `Paramètres`.

## Fonctionnalités

- Tableau de bord, statistiques et graphiques
- Totaux mensuels triés par montant, encours et alertes de charge pour planifier les nouveaux chèques/effets
- Calendrier de distribution : totaux par mois et par semaine, alertes de charge et simulateur de nouvelle échéance
- Documents (chèques / effets) : création, modification, suppression, filtres, tri, pagination
- Bénéficiaires, personnes responsables, carnets / registres
- Règlement / rejet, calcul du retard et des échéances
- Export PDF, CSV et Excel (XLSX), fiche PDF avec montant en lettres
- Rapports PDF centralisés : synthèse, documents, bénéficiaires, personnes, carnets, statistiques et calendrier
- Alertes email / SMS, journal des envois et planification par cron
- Historique / audit, paramètres société, changement de mot de passe
- Interface française par défaut, arabe avec RTL à l'exécution
- Design responsive (mobile, tablette, ordinateur)

## Rapports PDF

Le module [`rapports/`](rapports/) centralise la génération des rapports PDF, sans dépendance externe :

- **Synthèse globale** : indicateurs clés, répartition par type, totaux mensuels, seuils d'alerte et classements.
- **Documents** : liste détaillée filtrée (recherche, type, statut, bénéficiaire, personne, carnet, banque, période, échéance, montants) avec totaux.
- **Bénéficiaires**, **personnes responsables** et **carnets** : coordonnées, volumes, montants, encours et retards.
- **Statistiques** : analyse par période (émission, échéance ou règlement), totaux mensuels, bénéficiaires, banques et carnets.
- **Calendrier** : synthèse hebdomadaire, détail par jour et détail des documents du mois, avec seuils de charge.

Chaque rapport contient un en-tête société, les filtres appliqués, des cartes d'indicateurs, des tableaux paginés avec en-têtes répétés, une ligne de totaux, un bloc de total général et des notes de lecture. Les liens « Exporter PDF » des pages métier ouvrent directement le rapport correspondant en conservant les filtres actifs.

L'interface est accessible depuis le menu **Rapports PDF** (`rapports/index.php`). L'endpoint protégé `rapports/pdf.php` nécessite une session authentifiée et journalise l'export dans l'audit.

> Les PDF sont générés en **français** : le générateur natif utilise les polices Helvetica/WinAnsi et ne prend pas encore en charge l'arabe ou le façonnage Unicode.

## Prérequis

- PHP 8.2+ avec `pdo_mysql`, `mbstring`, `zip`, `curl`, `openssl`
- MySQL 8+
- Base de données existante : `gestion_cheques`

La connexion est configurée dans `config/config.php` et peut être surchargée par les variables d'environnement `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

## Base de données

Le dépôt contient la **structure** dans [`database/schema.sql`](database/schema.sql). Les données réelles (documents, clients, coordonnées) ne sont pas versionnées. Pour migrer une base existante, faire un export SQL local et l'importer sur le nouveau PC. Voir [`database/README.md`](database/README.md).

## Lancer en local

Depuis la racine du projet :

```powershell
php -S 127.0.0.1:8001 router.php
```

Le fichier `router.php` bloque les fichiers internes et sensibles quand le serveur PHP intégré est utilisé.

Ou via Apache/WAMP : placer le dossier dans le `DocumentRoot`. `BASE_URL` est détecté automatiquement (racine ou sous-dossier). Le fichier `.htaccess` protège les dossiers `config`, `includes`, `lib`, `storage`, `database`, les fichiers cachés (`.git`, `.env`) et les fichiers `.sql`, `.log`, `.json`, etc.

## Sécurité

- Toutes les pages et API privées exigent une session authentifiée. Un accès direct par URL est redirigé vers la connexion.
- Les dossiers internes (`config`, `includes`, `lib`, `storage`, `database`) et les fichiers sensibles ne sont pas accessibles par URL.
- Session : cookie `HttpOnly` + `SameSite=Lax`, renouvellement de l'identifiant, expiration après **30 minutes d'inactivité** et **8 heures maximum**.
- Connexion : verrouillage temporaire après **5 échecs** pendant **15 minutes** (paramétrable).
- Tous les formulaires POST sont protégés par un jeton CSRF.
- En-têtes de sécurité : CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store`.
- Les mots de passe sont hachés avec `password_hash()` ; le nouveau mot de passe doit contenir au moins 8 caractères, une lettre et un chiffre.
- En production, utiliser **HTTPS** et changer le mot de passe `admin123`.
- Variables utiles : `SESSION_IDLE_TIMEOUT`, `SESSION_ABSOLUTE_TIMEOUT`, `SESSION_REGENERATE_INTERVAL`, `LOGIN_MAX_ATTEMPTS`, `LOGIN_WINDOW_SECONDS`, `LOGIN_LOCK_SECONDS`, `CRON_TOKEN`.

## Alertes

Sans configuration SMTP / Twilio, les envois sont **simulés** et tracés dans l'historique. Pour activer les envois réels, définir les variables d'environnement :

```text
MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_SECURE (ssl|tls), MAIL_FROM, MAIL_FROM_NAME
TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM
```

Planification : exécuter `php alertes/cron.php` chaque minute (Planificateur de tâches Windows ou crontab). En mode web, utiliser un en-tête HTTP `X-Cron-Token: CRON_TOKEN` ou `Authorization: Bearer CRON_TOKEN`. Le jeton doit contenir au moins 32 caractères et ne doit jamais être placé dans l'URL.
