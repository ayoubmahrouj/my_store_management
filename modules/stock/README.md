# Suivi Stock

Application web de gestion de stock multi-sites : dépôts, points de vente, entrées, sorties, transferts, inventaires, consultation, statistiques et rapports.

Interface française, architecture MVC, API REST, PHP 8, MySQL/MariaDB, jQuery et Bootstrap 5.

## Modules

| Module | Fonctions |
|---|---|
| Tableau de bord | KPI, alertes de seuil, documents en attente, derniers mouvements, checklist de démarrage |
| Consultation | Recherche instantanée par produit, site, catégorie et statut, positionnement multi-sites, export CSV |
| Statistiques | Capital et quantité dans le temps, variation de période, flux, répartition par catégorie, capital par site, top produits |
| Produits | Création, modification, activation/désactivation, recherche, export |
| Référentiels | Marques, catégories, unités, fournisseurs et sites (dépôts / points de vente) |
| Entrées | Achat, stock initial, retour, ajustement positif ; brouillon puis validation |
| Sorties | Vente, perte, casse, retour fournisseur, consommation, ajustement négatif |
| Transferts | Expédition depuis un site source, réception sur un site destination, traçabilité complète |
| Inventaires | Session par site, feuille de comptage, écarts, validation avec mouvements d'ajustement |
| Rapports | Valorisation, répartition par site et catégorie, alertes, journal des mouvements, exports CSV |
| Utilisateurs | Comptes, rôles, site de rattachement, activation/désactivation |
| Paramètres | Identité de l'entreprise, devise, autorisation du stock négatif, diagnostic système |
| API REST v1 | Santé, authentification, produits, stock, documents, mouvements |

Les actions **Nouveau / Nouvelle** ouvrent le formulaire de création dans une fenêtre modale, sans quitter la page de travail. La validation ou l’enregistrement vous ramène automatiquement à la fiche créée.

## Règles de gestion

- Le **CUMP est global par produit**, quelle que soit la répartition du stock entre les sites.
- Le stock physique est suivi **par produit et par site**.
- Une entrée validée recalcule le CUMP global.
- Une sortie validée valorise les lignes au CUMP courant et bloque le stock négatif par défaut.
- Un transfert diminue le stock source à l'expédition, puis augmente le stock destination à la réception.
- Un inventaire valide applique les quantités comptées et crée des mouvements d'ajustement pour les écarts.
- Seuls les brouillons peuvent être annulés ; un document validé reste historisé.
- Chaque opération sensible est journalisée dans `audit_logs`.

## Prérequis

- PHP 8.1+ (testé avec PHP 8.4)
- MySQL 8+ ou MariaDB 10.4+
- Extensions `pdo_mysql`, `mbstring`, `openssl`

## Installation vide (production)

Depuis la racine du projet :

```powershell
php database/install.php --fresh
```

Cette commande :

1. recrée la base `gestion_our_stock` ;
2. crée toutes les tables et vues ;
3. insère uniquement les **rôles**, le **compte administrateur** et les **paramètres essentiels**.

Aucun produit, site, marque, catégorie, unité, fournisseur ou mouvement n'est créé.

> L'ancien installeur autonome de ce module (`stock/database/install.php`) a été archivé dans
> `storage/backups/legacy_module_databases_20261112/stock_database/` (hors dépôt).

## Lancement local

```powershell
php -S 127.0.0.1:8080 router.php
```

Ouvrir : **http://127.0.0.1:8080**

### Identifiants initiaux

- **Login** : `admin`
- **Mot de passe** : `Admin@2026`

**Changez ce mot de passe après la première connexion** dans *Utilisateurs*.

## Premiers pas après une installation vide

1. Ouvrir **Référentiels** et créer au minimum :
   - un site (dépôt ou point de vente) ;
   - une marque ;
   - une catégorie ;
   - une unité.
2. Créer éventuellement un fournisseur.
3. Créer un **produit** avec son CUMP initial, son prix de vente et son seuil d'alerte.
4. Créer une **entrée** de stock initial, puis la valider.
5. Créer un second site et tester un **transfert** (expédition puis réception).
6. Lancer un **inventaire** pour contrôler les écarts.

## Rôles et permissions

| Rôle | Produits | Entrées / transferts | Sorties | Inventaire | Administration |
|---|---|---|---|---|---|
| `admin` | Tout | Tout | Tout | Tout | Oui |
| `responsable` | Tout | Tout | Tout | Tout | Non |
| `magasinier` | Lecture | Tout | Tout | Tout | Non |
| `vendeur` | Lecture | Non | Tout | Lecture | Non |
| `auditeur` | Lecture | Lecture | Lecture | Lecture | Non |

L'administration des utilisateurs et des paramètres est réservée au rôle `admin`.

## API REST

| Méthode | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/health` | État du service (public) |
| POST | `/api/v1/auth/login` | Connexion JSON ou formulaire |
| GET | `/api/v1/auth/me` | Utilisateur courant |
| POST | `/api/v1/auth/logout` | Déconnexion |
| GET | `/api/v1/products` | Liste des produits (`?q=`) |
| GET | `/api/v1/products/{id}` | Fiche produit |
| GET | `/api/v1/stock` | Synthèse et stock par site |
| GET | `/api/v1/documents` | Documents (`?type=`, `?statut=`, `?site=`) |
| GET | `/api/v1/documents/{id}` | Détail d'un document |
| GET | `/api/v1/movements` | Journal des mouvements (`?type=`, `?site=`, `?du=`, `?au=`) |

Exemples :

```powershell
curl.exe -i http://127.0.0.1:8080/api/v1/health

curl.exe -i -X POST http://127.0.0.1:8080/api/v1/auth/login `
  -H "Content-Type: application/json" `
  -d "{\"login\":\"admin\",\"password\":\"Admin@2026\"}"
```

## Configuration

Surcharger par variables d'environnement :

| Variable | Défaut |
|---|---|
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `gestion_our_stock` |
| `DB_USER` | `root` |
| `DB_PASS` | *(vide)* |
| `APP_BASE` | détecté automatiquement |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `0` (ou `1` si `APP_ENV=local`) |
| `APP_URL` | `http://127.0.0.1:8080` |

### Configuration locale par poste

Pour éviter de modifier le fichier suivi par Git, créez un fichier local :

```powershell
Copy-Item config/config.local.example.php config/config.local.php
```

Puis adaptez les identifiants MySQL dans `config/config.local.php`. Ce fichier est ignoré par Git.

⚠️ `config/config.local.php` est prioritaire sur les variables d'environnement. En production, ne le créez pas : utilisez les variables `APP_*` et `DB_*`.

### Diagnostic serveur

```powershell
php database/doctor.php
```

Le diagnostic vérifie PHP, les extensions, les droits d'écriture, la connexion MySQL, les tables, les administrateurs actifs et le mode debug.

## Structure

```
modules/stock/
├── index.php              Front controller (MVC + API)
├── router.php             Routeur du serveur PHP intégré
├── app/
│   ├── Core/              Config, Database, Router, Auth, CSRF, View, Response…
│   ├── Domain/            Entités typées (Product, StockDocument, InventorySession…)
│   ├── Application/       Services métier (StockService, AuthService…)
│   ├── Infrastructure/    Dépôts PDO
│   ├── Http/              Contrôleurs Web/API, middlewares
│   └── Views/             Vues premium
├── assets/                CSS / JS
├── config/                Configuration
├── deploy/                Exemple de configuration Nginx
├── docs/                  Rapport d'audit et documents projet (protégé)
└── storage/               Logs et fichiers d'exécution (protégé)
```

> Le schéma et les scripts d'installation sont **mutualisés** à la racine : `database/`.

## Déploiement serveur

### Apache

Le fichier `.htaccess` protège les dossiers `app`, `config`, `docs` et `storage`, et bloque les fichiers sensibles (`.sql`, `.log`, `.env`, `.md`, etc.). `mod_rewrite` doit être activé.

### Nginx

Nginx ne lit pas `.htaccess`. Utilisez l'exemple :

```text
deploy/nginx.conf.example
```

Adaptez `server_name`, `root` et le socket PHP-FPM, puis vérifiez que les dossiers sensibles renvoient bien une erreur 403 ou 404.

### Mise en production

1. Définir `APP_ENV=production` et `APP_DEBUG=0`.
2. Activer HTTPS et rediriger HTTP vers HTTPS.
3. Utiliser un utilisateur MySQL dédié, avec des droits limités à la base `gestion_our_stock`.
4. Ne pas créer `config/config.local.php` en production.
5. Vérifier les droits d'écriture uniquement sur `storage/`.
6. Lancer `php database/doctor.php`.
7. Mettre en place une sauvegarde quotidienne de la base et tester la restauration.

L'installeur `database/install.php` refuse désormais toute exécution depuis le web : il doit être lancé en ligne de commande.

## Sécurité

- Requêtes préparées PDO et paramètres nommés uniques.
- `password_hash` / `password_verify`.
- Jeton CSRF sur tous les formulaires de mutation.
- Cookies `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS.
- `session.use_strict_mode` activé.
- Contrôle d'accès côté serveur par rôle.
- Limitation des tentatives de connexion.
- Journal d'audit et journal des connexions.
- En-têtes de sécurité : HSTS en HTTPS, CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
- Dossiers sensibles protégés par `.htaccess` (Apache) ou par la configuration Nginx.
- Installeur et diagnostic accessibles uniquement en ligne de commande.

## Vérifications

```powershell
# Syntaxe PHP
Get-ChildItem -Recurse -Filter *.php stock | ForEach-Object { php -l $_.FullName }

# Syntaxe JavaScript
node --check stock/assets/js/app.js
```
