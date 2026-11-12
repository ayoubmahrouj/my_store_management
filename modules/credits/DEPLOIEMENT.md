# Déploiement — Gestion Crédits (PHP natif + MySQL)

## Accès

- Local : http://localhost/gestion-credits/
- Réseau : http://192.168.2.16/gestion-credits/
- Identifiant : `admin` / `admin123` (à changer immédiatement dans **Paramètres → Mon compte**)

## Base de données

- Moteur : MySQL / MariaDB (XAMPP), port 3306
- Base : **`gestion_credits_php`** (base dédiée à cette application PHP)
- Utilisateur : `root`, mot de passe vide (configuration XAMPP par défaut)
- La base `gestion_credits` (schéma Laravel) n'est **pas** utilisée par cette application et n'a pas été modifiée.

Configuration : `inc/config.php`

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'gestion_credits_php');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DEBUG', false);
```

## Scripts SQL

L'application unifiée utilise un **schéma unique** : [`../../database/schema.sql`](../../database/schema.sql) (racine du projet).

L'ancien schéma autonome de ce module (`schema_mysql.sql`, `copie_depuis_gestion_credits.sql`,
`migration_brand_sale_items.sql`) a été archivé dans
`storage/backups/legacy_module_databases_20261112/credits_database/` (hors dépôt).

Import de la base unifiée :

```powershell
Get-Content database\schema.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root
```

Le dossier `database/` est bloqué par `.htaccess` (HTTP 403).

## Sauvegarde

- Depuis l'application : **Paramètres → Sauvegarde → Créer** (fichier `.sql` dans `data/backups/`).
- Restauration manuelle : `mysql -u root gestion_credits_php < fichier.sql`.

## Sécurité

- `DEBUG = false` (aucune erreur PHP affichée).
- Dossiers `inc/`, `pages/`, `data/` interdits en HTTP.
- Fichiers cachés, `.sql`, `.log`, `.md`, `.json`, etc. interdits en HTTP.
- Accès limité au réseau local : ne pas exposer Apache sur Internet.
- Recommandé : changer `admin123` et définir un mot de passe pour MySQL `root`.

## Remarques

- L'ancien fichier `data/gestion.sqlite` (version SQLite) n'est plus utilisé ; il peut être conservé comme archive.
- L'ancien dossier `C:\xampp\htdocs\gestion_credits` n'a pas été modifié.
