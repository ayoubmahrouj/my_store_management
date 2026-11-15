# Déploiement — XAMPP puis cPanel

Ce guide couvre l'installation locale (XAMPP/WAMP), la reprise des données des
trois anciens projets, puis la mise en ligne sur un hébergement cPanel.

> Règle d'or : **les trois dossiers d'origine ne sont jamais modifiés**.
> La migration ne fait que lire leurs bases.

---

## 1. Déploiement local (XAMPP / WAMP)

### 1.1 Copier l'application

- XAMPP : `C:\xampp\htdocs\gestion_unifiee`
- WAMP : `C:\wamp64\www\gestion_unifiee`

### 1.2 Configurer MySQL (optionnel)

Si MySQL n'utilise pas `root` sans mot de passe, copiez
`deploy/config.local.example.php` vers `shared/config.local.php` et renseignez
`db.user` / `db.pass`.

Pour les photos de bons et de chèques (6 Mo max par image), vérifiez dans
`php.ini` : `upload_max_filesize = 8M` et `post_max_size = 10M`, puis
redémarrez Apache. Sur cPanel, ces valeurs se règlent dans
**MultiPHP INI Editor**.

### 1.3 Installer la base

Ouvrez un terminal dans le dossier `gestion_unifiee` :

```bash
php database/install.php --fresh
```

L'installeur :

- crée la base `gestion_our_stock` (utf8mb4) ;
- crée toutes les tables, index et vues de compatibilité ;
- insère les 5 rôles et les référentiels minimaux ;
- crée le compte `admin` / `Admin@2026` (modifiable via `ADMIN_LOGIN` /
  `ADMIN_PASSWORD`).

**Sans accès à la ligne de commande** (hébergement mutualisé), importez
directement le script SQL, qui fait exactement la même chose :

- phpMyAdmin : onglet **Importer** → `database/create_database.sql` → **Exécuter** ;
- ou en terminal : `mysql -u root -p < database/create_database.sql`.

Le script est idempotent : le réimporter ne crée aucun doublon et n'écrase pas
les valeurs déjà personnalisées. Il est généré depuis `schema.sql` — après une
modification du schéma, relancez `php database/build_create_sql.php`.

### 1.4 Importer les anciennes données

Les trois anciennes bases doivent être présentes dans le même serveur MySQL
(c'est le cas sur votre poste XAMPP). Puis :

```bash
php database/migrate_legacy.php --force
```

Bases sources reconnues automatiquement :

| Module | Base attendue |
| --- | --- |
| Stock | `suivi_stock` |
| Crédits | `gestion_credits_php` (sinon `gestion_credits`) |
| Chèques | `gestion_cheques` |

Si vos bases portent d'autres noms, utilisez les variables d'environnement :

```bash
set LEGACY_STOCK_DB=mon_suivi
set LEGACY_CREDITS_DB=mes_credits
set LEGACY_CHEQUES_DB=mes_cheques
php database/migrate_legacy.php --force
```

Sous Linux/macOS :

```bash
LEGACY_STOCK_DB=mon_suivi LEGACY_CREDITS_DB=mes_credits \
LEGACY_CHEQUES_DB=mes_cheques php database/migrate_legacy.php --force
```

> `--force` remplace les données de la base cible. Ne l'utilisez pas sur une
> base déjà en production sans sauvegarde.

### 1.4 ter Fusion additive (sans rien supprimer)

Si la base unifiée contient **déjà** des données (par exemple un premier import
`gestionmystock`) et que vous voulez seulement y **ajouter** ce qui manque depuis
les trois anciennes bases, utilisez :

```bash
php database/merge_legacy_dumps.php --dry-run   # aperçu, aucune écriture
php database/merge_legacy_dumps.php             # applique
```

Cette commande n'efface rien : elle ajoute uniquement les enregistrements absents
(clients, produits, marques, chèques, ventes/crédits du module crédits). Elle est
idempotente — une seconde exécution affiche « Rien à ajouter ». Les mêmes variables
`LEGACY_*_DB` s'appliquent.

### 1.4 bis Importer l'ancien POS « gestionmystock »

Si vos données de ventes / achats / clients proviennent de l'application
`gestionmystock` (tables `vente`, `achat`, `produit`, `client`, …), importez-les
avec :

```bash
php database/import_gestionmystock.php
php database/backfill_stock_movements.php
```

`backfill_stock_movements.php` reconstruit le grand livre des mouvements de stock
à partir des entrées d'achat importées (idempotent, transactionnel).

### 1.5 Vérifier

```bash
php database/upgrade.php
php database/backfill_stock_movements.php
php database/doctor.php
```

`upgrade.php` est idempotent : il ajoute la table `credit_bons` et les colonnes
d'images des chèques/effets sur une base existante, sans toucher aux données.
Relancez-le après chaque mise à jour de l'application.

Le diagnostic contrôle la connexion, les tables, les vues, les comptes actifs et
les dossiers inscriptibles. Toutes les lignes doivent être au vert.

### 1.6 Se connecter

<http://localhost/gestion_unifiee/login.php>

| Identifiant | Mot de passe |
| --- | --- |
| `admin` | `Admin@2026` |

Changez immédiatement le mot de passe (module Stock → Utilisateurs, ou gestion
des utilisateurs du module Crédits).

---

## 2. Préparer le paquet pour cPanel

### 2.1 Sauvegarder la base locale

```bash
mysqldump -u root -p --default-character-set=utf8mb4 --single-transaction \
  gestion_our_stock > gestion_our_stock.sql
```

Ou depuis phpMyAdmin : base `gestion_our_stock` → **Exporter** → SQL.

### 2.2 Créer l'archive

Archivez le dossier `gestion_unifiee` **sans** :

- `storage/logs/*.log`
- `storage/cache/*`
- `storage/exports/*`
- `shared/config.local.php` (sera recréé sur le serveur)

Gardez la structure des dossiers (`storage/` doit exister, même vide).

---

## 3. Déploiement cPanel

### 3.1 Créer la base et l'utilisateur MySQL

cPanel → **Bases de données MySQL** :

1. Créer une base, par exemple `moncompte_gestion`.
2. Créer un utilisateur, par exemple `moncompte_gos` avec un mot de passe fort.
3. Ajouter l'utilisateur à la base avec **tous les privilèges**.

cPanel préfixe automatiquement les noms avec votre compte : notez les noms
complets affichés.

### 3.2 Choisir la version de PHP

cPanel → **MultiPHP Manager** : sélectionnez **PHP 8.1 ou supérieur** pour le
domaine. Vérifiez que les extensions `pdo_mysql`, `mbstring` et `json` sont
actives (cPanel → **Select PHP Version** → Extensions).

### 3.3 Envoyer les fichiers

Deux possibilités :

| Emplacement | URL |
| --- | --- |
| `public_html/` (racine du domaine) | `https://exemple.com/` |
| `public_html/gestion/` (sous-dossier) | `https://exemple.com/gestion/` |

Envoyez l'archive via **Gestionnaire de fichiers** puis **Extraire**, ou par
FTP/SFTP. Le chemin de base (`app.base`) est détecté automatiquement : aucune
configuration supplémentaire n'est nécessaire pour un sous-dossier.

### 3.4 Créer `shared/config.local.php`

Copiez `deploy/config.local.example.php` en `shared/config.local.php` et
renseignez les identifiants cPanel :

```php
<?php
return [
    'app' => [
        'env'   => 'production',
        'debug' => false,
        'url'   => 'https://exemple.com',   // ou https://exemple.com/gestion
    ],
    'db' => [
        'host' => 'localhost',
        'name' => 'moncompte_gestion',
        'user' => 'moncompte_gos',
        'pass' => 'mot-de-passe-fort',
    ],
];
```

Ne mettez **jamais** ce fichier dans un dépôt public. Il est déjà interdit
d'accès par `.htaccess`.

### 3.5 Importer la base

Deux méthodes.

**A. Migration faite en local (recommandé)**

1. phpMyAdmin cPanel → base `moncompte_gestion` → **Importer**.
2. Choisir `gestion_our_stock.sql`, encodage `utf-8`, puis exécuter.

**B. Reprise directe des anciennes bases sur le serveur**

1. Importez les anciens dumps dans des bases cPanel :
   `moncompte_suivi_stock`, `moncompte_gestion_credits`, `moncompte_gestion_cheques`.
2. Installez la base cible (voir 3.6), puis lancez la migration avec les noms
   préfixés :

```bash
LEGACY_STOCK_DB=moncompte_suivi_stock \
LEGACY_CREDITS_DB=moncompte_gestion_credits \
LEGACY_CHEQUES_DB=moncompte_gestion_cheques \
php database/migrate_legacy.php --force
```

### 3.6 Lancer l'installeur sur le serveur (si base vide)

L'installeur et la migration refusent de s'exécuter depuis un navigateur : il
faut la ligne de commande.

**Option 1 — Terminal cPanel** (si disponible) :

```bash
cd ~/public_html/gestion
php database/install.php
php database/migrate_legacy.php --force   # si vous reprenez les anciennes bases
php database/upgrade.php
php database/doctor.php
```

**Option 2 — Tâche cron unique** (si pas de Terminal) :

cPanel → **Tâches Cron** → ajoutez une tâche « une fois » :

```bash
/usr/local/bin/php /home/moncompte/public_html/gestion/database/install.php
```

Attendez l'exécution, vérifiez l'e-mail de sortie, puis **supprimez la tâche**.
Répétez de la même façon pour `migrate_legacy.php --force` si nécessaire, puis
`upgrade.php`.

**Option 3 — Import manuel phpMyAdmin** :

1. Importez `database/schema.sql` dans la base cPanel.
2. Générez un hash de mot de passe :

   ```bash
   php -r "echo password_hash('VotreMotDePasseFort', PASSWORD_DEFAULT), PHP_EOL;"
   ```

3. Exécutez dans phpMyAdmin :

   ```sql
   INSERT INTO roles (code, label) VALUES
     ('admin','Administrateur'), ('responsable','Responsable'),
     ('magasinier','Magasinier'), ('vendeur','Vendeur'), ('auditeur','Auditeur')
   ON DUPLICATE KEY UPDATE label = VALUES(label);

   INSERT INTO users (name, login, email, password_hash, role_id, modules, active)
   SELECT 'Administrateur', 'admin', NULL, '<HASH_GENERE>', id, 'stock,credits,cheques', 1
   FROM roles WHERE code = 'admin';
   ```

### 3.7 Permissions

Via **Gestionnaire de fichiers** → clic droit → **Autorisations** :

| Élément | Permission |
| --- | --- |
| `storage/` et ses sous-dossiers | `755` (ou `775` si le serveur l'exige) |
| `storage/logs/`, `storage/exports/`, `storage/uploads/`, `storage/cache/` | `755` |
| `shared/config.local.php` | `600` ou `640` |
| Autres fichiers PHP | `644` |
| Dossiers | `755` |

Le propriétaire doit être votre compte cPanel (pas `root`).

### 3.8 Vérifier Apache

`.htaccess` nécessite `AllowOverride All` (valeur par défaut chez la plupart des
hébergeurs cPanel). Il gère :

- la réécriture des URLs du module Stock (`/modules/stock/...`) ;
- le blocage de `shared/`, `database/`, `storage/` ;
- le blocage des fichiers `.sql`, `.log`, `.ini`, `.env`, etc. ;
- les en-têtes de sécurité HTTP.

Si le module Stock renvoie une 404 sur `/modules/stock/dashboard`, contactez
l'hébergeur pour activer `mod_rewrite` / `AllowOverride`.

### 3.9 Activer HTTPS

1. cPanel → **SSL/TLS Status** → exécutez **AutoSSL**.
2. Forcez HTTPS : cPanel → **Domains** → activer **Force HTTPS Redirect**, ou
   ajoutez dans `.htaccess` (à la racine) :

```apache
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

Le socle ajoute automatiquement HSTS lorsque la requête est en HTTPS.

### 3.10 Première connexion en production

1. Ouvrir `https://exemple.com/gestion/login.php`.
2. Se connecter avec `admin` / `Admin@2026`.
3. **Changer le mot de passe immédiatement.**
4. Créer les comptes réels et limiter leurs modules (`stock,credits,cheques`).
5. Vérifier que `APP_DEBUG` / `app.debug` vaut `false`.

---

## 4. Sauvegardes

### Script intégré (recommandé)

```bash
php database/backup.php                    # base + téléversements
php database/backup.php --list             # lister
php database/backup.php --restore fichier.sql --yes
php database/backup.php --prune 30         # purge > 30 jours
```

Le script localise `mysqldump` automatiquement (ou via `MYSQLDUMP_BIN`), transmet
le mot de passe par un fichier temporaire (jamais en ligne de commande) et écrit
dans `storage/backups/` (bloqué par `.htaccess`). Rétention réglable avec
`BACKUP_KEEP_DAYS`.

Automatisation (cron, chaque nuit à 02h00) :

```bash
0 2 * * * /usr/bin/php /home/moncompte/public_html/gestion/database/backup.php
```

### Base (manuel)

```bash
mysqldump -u moncompte_gos -p --single-transaction --default-character-set=utf8mb4 \
  moncompte_gestion > sauvegarde_$(date +%F).sql
```

Sur cPanel : **Sauvegardes** → **Télécharger une sauvegarde MySQL**, ou planifiez
une tâche cron :

```bash
0 2 * * * /usr/bin/mysqldump -u moncompte_gos -p'MOT_DE_PASSE' --single-transaction moncompte_gestion | gzip > /home/moncompte/backups/gestion_$(date +\%F).sql.gz
```

### Fichiers

Sauvegardez au minimum `shared/config.local.php`, `storage/uploads/` et
`storage/exports/`. Le reste est du code réinstallable.

---

## 5. Mise à jour de l'application

1. Sauvegarder la base et `shared/config.local.php`.
2. Envoyer les nouveaux fichiers **sans écraser** `shared/config.local.php`,
   `storage/`, ni les éventuels fichiers personnalisés.
3. Exécuter les évolutions de schéma :

```bash
php database/install.php      # sans --fresh : crée seulement ce qui manque
php database/upgrade.php      # ajoute les nouveautés (bons, images)
php database/doctor.php
```

4. Tester : connexion, tableau de bord, un écran par module, un bouton « + »,
   l'ajout d'un bon avec photo et l'ouverture d'une image de chèque.

> N'utilisez jamais `--fresh` en production : cela supprime la base.

---

## 6. Dépannage

| Symptôme | Piste |
| --- | --- |
| Page blanche / HTTP 500 | Lire `storage/logs/php-error.log` ; souvent permissions ou `config.local.php` |
| « Base de données non installée » | Lancer `php database/install.php` ou importer le dump |
| Erreur de connexion MySQL | Vérifier `db.host` (`localhost` chez cPanel), nom préfixé, utilisateur et privilèges |
| 403 sur tout le site | Permissions des dossiers (755) ou `.htaccess` mal interprété |
| 404 sur `/modules/stock/dashboard` | `mod_rewrite` / `AllowOverride All` désactivé |
| Bouton « + » sans effet | Vérifier que `assets/js/quick-create.js` se charge (onglet Réseau), et la session |
| Boucle de connexion | Cookie `gos_sid` bloqué ; vérifier le domaine/chemin du cookie et HTTPS |
| Accents cassés après import | Base et tables en `utf8mb4_unicode_ci`, import en UTF-8 |
| Session expirée trop vite | Ajuster `session.lifetime` dans `config.local.php` |

### Journaux utiles

- `storage/logs/php-error.log` — erreurs PHP du socle et des modules
- `modules/stock/storage/logs/php-error.log` — erreurs du module Stock
- cPanel → **Erreurs** / logs Apache

### Diagnostic intégré

```bash
php database/doctor.php
```

---

## 7. Checklist de mise en ligne

- [ ] PHP 8.1+ et extensions `pdo_mysql`, `mbstring`, `json`
- [ ] Base cPanel créée, utilisateur ajouté avec tous les privilèges
- [ ] `shared/config.local.php` renseigné (hôte, base, utilisateur, mot de passe)
- [ ] `app.debug = false`, `app.env = production`
- [ ] Base importée ou installeur exécuté
- [ ] Migration des anciennes données vérifiée (clients, produits, documents)
- [ ] `storage/` inscriptible (755/775)
- [ ] `mod_rewrite` actif, `.htaccess` lu
- [ ] HTTPS actif + redirection forcée
- [ ] Mot de passe `admin` changé
- [ ] Comptes utilisateurs et modules attribués
- [ ] Sauvegarde base + fichiers planifiée
- [ ] `php database/doctor.php` au vert
- [ ] Parcours testé : connexion, dashboard, 3 modules, bouton « + »
