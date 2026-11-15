# Gestion Our Stock — application unifiée

Application unique qui réunit les trois anciens projets :

| Module | Ancien projet | Contenu |
| --- | --- | --- |
| **Stock** | `suivi-stock` | Produits, entrées, sorties, transferts, inventaire, rapports |
| **Crédits & ventes** | `gestion_credits` | Clients, ventes, point de vente, crédits, versements, statistiques |
| **Chèques & effets** | `gestion_Cheques_effets` | Carnets, bénéficiaires, documents, échéances, alertes |

Une seule base de données (`gestion_our_stock`), une seule connexion (`login.php`),
une seule interface (thème sombre premium) et un bouton **« + »** de création rapide
à côté des listes déroulantes.

Les trois dossiers d'origine restent intacts : `gestion_unifiee` est autonome.

> **Importer les anciennes données ?** Guide détaillé, table par table :
> **[IMPORT_DONNEES.md](IMPORT_DONNEES.md)** (Stock + Crédits + Chèques + POS).

---

## 1. Prérequis

- PHP **8.1+** (8.2/8.3 recommandé)
- MySQL 5.7+ ou MariaDB 10.4+
- Extensions PHP : `pdo_mysql`, `mbstring`, `json`, `openssl`
- Apache avec `mod_rewrite` et `mod_headers` (ou le serveur intégré PHP en local)

## 2. Installation locale (XAMPP / WAMP)

1. Copier le dossier `gestion_unifiee` dans `htdocs` (XAMPP) ou `www` (WAMP).
2. Démarrer Apache et MySQL.
3. (Optionnel) Créer `shared/config.local.php` à partir de
   `deploy/config.local.example.php` pour fixer les identifiants MySQL.
   Sans ce fichier, les valeurs par défaut sont `root` / mot de passe vide sur
   `127.0.0.1`.
4. Ouvrir un terminal **dans le dossier `gestion_unifiee`** :

```bash
php database/install.php --fresh
php database/migrate_legacy.php --force   # base neuve : réimporte les 3 anciennes bases
php database/import_gestionmystock.php    # importe l'ancien POS gestionmystock
php database/upgrade.php                  # ajoute les nouveautés (bons, images)
php database/doctor.php                   # diagnostic
```

> **Ajouter des données à une base déjà remplie, sans rien supprimer :**
> `php database/merge_legacy_dumps.php` (fusion additive et idempotente, voir § 7).
> Contrairement à `migrate_legacy.php --force` qui **vide puis réimporte**,
> cette commande n'ajoute que les enregistrements absents.

5. Ouvrir <http://localhost/gestion_unifiee/login.php>.

Compte créé par l'installeur :

- Identifiant : `admin`
- Mot de passe : `Admin@2026`

> Changez ce mot de passe dès la première connexion.
> L'installeur lit aussi `ADMIN_LOGIN` / `ADMIN_PASSWORD` (variables
> d'environnement) si vous voulez un autre compte initial.

### Serveur de développement PHP intégré

Depuis `gestion_unifiee` :

```bash
php -S 127.0.0.1:8099 -t . router.php
```

Puis <http://127.0.0.1:8099/login.php>.

## 3. Adresses utiles

| Page | URL |
| --- | --- |
| Connexion unique | `/login.php` |
| Tableau de bord global | `/index.php` |
| Module Stock | `/modules/stock/dashboard` |
| Module Crédits | `/modules/credits/index.php?p=dashboard` |
| Bons société | `/modules/credits/index.php?p=bons` |
| Module Chèques | `/modules/cheques/index.php` |

L'accès aux modules dépend du rôle et de la colonne `users.modules`
(`stock,credits,cheques`). Un administrateur voit tout.

## 4. Création rapide « + »

Les listes déroulantes portant `data-quick="…"` sont automatiquement enrichies
d'un bouton **+** par `assets/js/quick-create.js`. Le formulaire s'ouvre dans une
modale, enregistre via `api/quick_create.php` (CSRF + session) puis ajoute et
sélectionne l'élément sans quitter la page.

Entités gérées : marque, catégorie, unité, fournisseur, site, client, société,
produit, carnet, personne.

Les pages historiques des modules Crédits et Chèques conservent leurs propres
modales de création rapide, branchées sur le même socle de données.

## 4 bis. Bons société et images des documents

- **Bons société** (`Crédits → Bons société`) : chaque bon remis par une société
  est enregistré avec sa date, son montant, sa référence et une photo / un scan.
  Le statut suit le circuit réel : **En attente → Encaissé → Payé**, avec la date
  de réception de l'argent. Un récapitulatif calcule, par société et par mois,
  le total, le montant déjà payé et le reste à recevoir.
- **Chèques & effets** : chaque document peut recevoir une image **recto** et une
  image **verso** (photo ou scan). Les vignettes apparaissent dans la liste et
  s'ouvrent dans une visionneuse plein écran.

Les fichiers sont stockés dans `storage/uploads/{bons|cheques}/AAAA/MM/` avec un
nom aléatoire. Ils ne sont **jamais servis directement** : deux points d'entrée
protégés par la session les affichent (`bon_image.php`, `documents/image.php`).
Formats acceptés : JPG, PNG, WEBP — 6 Mo maximum.

## 4 ter. Données communes — une seule table par entité

Aucune entité n'est dupliquée entre les modules : il existe **une table par
concept**, partagée par Stock, Crédits et Chèques.

| Entité | Table |
|---|---|
| Tiers : clients | `clients` |
| Tiers : bénéficiaires de chèques | `beneficiaire` |
| Tiers : fournisseurs | `suppliers` |
| Tiers : personnes internes | `personne` |
| Produits | `products` |
| Marques | `brands` |
| Catégories | `categories` |
| Unités | `units` |
| Sites / dépôts | `locations` |
| Ventes / lignes | `sales` / `sale_items` |
| Paiements | `payments` / `payment_allocations` |
| Crédits / bons société | `credits` / `credit_bons` |
| Stock | `stock_documents`, `stock_document_lines`, `stock_levels` |

**Chaque population de tiers a sa propre table physique** : `clients`,
`beneficiaire`, `suppliers` et `personne` sont indépendantes, avec leurs
propres identifiants. Aucun client n'est exposé comme bénéficiaire (ou
inversement). Les vues de compatibilité restantes ne concernent que l'ancien
vocabulaire des chèques et des produits : `credits_products` →
`products`+`brands`, `carnet` → `cheques_carnets`, `document_paiement` →
`cheques_documents`, `alerte_config`/`alerte_log` → `cheques_alert_*`,
`audit_log` → `audit_logs`.

**Informations société** : source unique = table `settings`
(clés `company_name`, `company_address`, `company_phone`, `company_email`),
lue et écrite par le socle et le module Chèques. La table historique
`parametres_societe` est conservée uniquement comme cible d'import ; elle n'est
plus utilisée par l'application (`database/upgrade.php` reprend une fois ses
valeurs vers `settings` si besoin).

## 4 quater. Interface — design system et recherche globale

L'interface est commune aux trois modules : même barre supérieure, même barre
latérale, mêmes composants. Deux feuilles de style seulement, chargées dans cet
ordre :

| Fichier | Rôle |
|---|---|
| `assets/css/shell.css` | Jetons de design (`--gos-*`), structure (sidebar, topbar, palette, pager), thèmes sombre/clair |
| `assets/css/premium.css` | Finitions et surcharges des modules (KPI, tableaux, filtres, badges, responsive) |

- **Thème sombre par défaut**, thème clair disponible via le bouton lune/soleil
  (mémorisé dans `localStorage`). Le sélecteur est posé sur `<html>` **et**
  `<body>` : les surcharges de modules ciblent `[data-theme="dark"]`.
- **Navigation** : sidebar 252 px sur ordinateur, barre inférieure à 4 modules
  sous 720 px. Le sélecteur de module (2 × 2) reste visible en haut de la sidebar.
- **Recherche globale (Ctrl / Cmd + K)** : palette de commandes qui interroge
  `api/global_search.php` (JSON, protégé par la session). Résultats groupés
  (produits, clients, bénéficiaires, fournisseurs, chèques, ventes), 6 par groupe,
  préfixe d'abord. Côté navigateur : délai de frappe 140 ms, `AbortController`,
  cache de 40 requêtes, navigation clavier ↑ ↓ Entrée Échap.
- **Listes volumineuses** : pagination **côté serveur** (50 lignes par page) avec
  totaux calculés en SQL sur l'ensemble du filtre — jamais sur la page affichée.
  Helpers `paginate()` / `pager()` dans `modules/credits/inc/helpers.php`. Les
  tableaux concernés portent `data-nopage` pour désactiver l'ancien paginateur
  JavaScript du module.
- **Performance perçue** : aucune animation d'entrée sur le contenu, pas de
  `backdrop-filter` sur les barres, colonnes à largeur fixe (`table-list`) pour
  éviter les débordements, chiffres tabulaires.

## 5. Structure

```
gestion_unifiee/
├── index.php               Tableau de bord global
├── login.php / logout.php  Authentification unique
├── api/quick_create.php    API du bouton « + »
├── api/global_search.php   API de la palette de recherche (Ctrl K)
├── assets/                 Thème partagé (shell.css, premium.css, shell.js,
│                           quick-create.js, command.js)
├── shared/                 Socle commun (config, db, auth, layout, i18n)
├── database/               schema.sql, install, migration, upgrade, diagnostic
├── modules/
│   ├── stock/              Module Stock (MVC)
│   ├── credits/            Module Crédits & ventes
│   └── cheques/            Module Chèques & effets
├── storage/                Logs, exports, uploads, cache (inscriptible)
├── deploy/                 Modèle de configuration serveur
├── router.php              Routeur du serveur PHP intégré (dev uniquement)
└── .htaccess               Sécurité + réécriture Apache
```

## 6. Base de données

- Nom par défaut : `gestion_our_stock`
- Tables partagées : `users`, `roles`, `clients`, `beneficiaire`, `suppliers`,
  `personne`, `products`, `brands`, `categories`, `units`, `locations`,
  `settings`, `audit_logs`…
- Tables spécifiques préfixées : `stock_*`, `sales` / `credits` / `payments`,
  `cheques_*`.
- Vues de compatibilité (`credits_products`, `carnet`, `document_paiement`,
  `alerte_config`, `alerte_log`, `audit_log`) pour que le code historique
  continue de fonctionner sans réécriture lourde.

La migration ne modifie **jamais** les anciennes bases : elle ne fait que lire
`suivi_stock`, `gestion_credits_php` (ou `gestion_credits`) et `gestion_cheques`.

## 7. Scripts en ligne de commande

```bash
php database/install.php --fresh          # recrée la base + données de base
php database/install.php                  # crée les tables manquantes
php database/migrate_legacy.php --force   # VIDE puis réimporte les anciennes données
php database/merge_legacy_dumps.php       # AJOUTE seulement ce qui manque (sans rien vider)
php database/import_gestionmystock.php    # importe l'ancien POS gestionmystock
php database/migrate_split_tiers.php --force # sépare clients / bénéficiaires / personnes / fournisseurs (base existante)
php database/backfill_stock_movements.php # reconstruit le grand livre des mouvements
php database/upgrade.php                  # ajoute les nouveautés sans perte
php database/doctor.php                   # contrôle tables, vues, admin, logs
```

Tous refusent de s'exécuter depuis un navigateur.

### Différence entre `migrate_legacy.php` et `merge_legacy_dumps.php`

| | `migrate_legacy.php --force` | `merge_legacy_dumps.php` |
|---|---|---|
| Effet | **vide** (TRUNCATE) puis réimporte | **ajoute** uniquement les lignes absentes |
| Usage | installation neuve, base vide | base déjà remplie, on complète |
| Doublons | non (base repartie de zéro) | non (clé naturelle + `import_map`) |
| Idempotent | oui (mais réécrit tout) | oui (relance = 0 ajout) |

`merge_legacy_dumps.php` lit les mêmes bases sources :

| Module | Base lue (par défaut) | Variable d'environnement |
|---|---|---|
| Crédits | `gestion_credits` | `LEGACY_CREDITS_DB` |
| Stock | `suivi_stock` | `LEGACY_STOCK_DB` |
| Chèques | `gestion_cheques` | `LEGACY_CHEQUES_DB` |

Options : `--dry-run` (simulation dans une transaction annulée, affiche ce qui
serait ajouté), `--credits-db=…`, `--stock-db=…`, `--cheques-db=…`.

Il fusionne : marques, catégories, unités, emplacements, fournisseurs, clients,
produits, personnes/carnets/documents de chèques, ainsi que les ventes, lignes
et crédits du module crédits. Les utilisateurs legacy ne sont pas importés (le
compte `admin` unifié est conservé) et les échéances legacy (`installments`) ne
sont pas stockées (leur date est reprise dans `credits.due_date`).

```bash
php database/merge_legacy_dumps.php --dry-run   # aperçu, aucune écriture
php database/merge_legacy_dumps.php             # applique
```


### Sauvegarde et restauration

```bash
php database/backup.php                   # sauvegarde la base + les téléversements
php database/backup.php --list            # liste les sauvegardes
php database/backup.php --restore FICHIER --yes   # restaure une sauvegarde
php database/backup.php --prune 30        # purge les sauvegardes > 30 jours
```

Les sauvegardes sont écrites dans `storage/backups/` (inaccessible par le web),
au format `db_AAAAmmjj_HHMMSS.sql`. Pour une sauvegarde automatique nocturne :

```bash
0 2 * * * /usr/bin/php /chemin/vers/gestion/database/backup.php
```

Sous Windows, utilisez le Planificateur de tâches avec le `php.exe` complet.
La durée de rétention se règle via `BACKUP_KEEP_DAYS` (défaut 30 jours).

## 8. Sécurité

- Sessions uniques `gos_sid`, cookie `HttpOnly` + `SameSite=Lax`, régénération à
  la connexion, expiration par inactivité.
- Jetons CSRF sur les formulaires et les appels AJAX.
- Requêtes préparées (PDO) dans tout le socle partagé.
- Limitation des tentatives de connexion (`login_attempts`).
- En-têtes de sécurité (CSP, `X-Frame-Options`, `nosniff`, HSTS en HTTPS).
- Dossiers `shared/`, `database/`, `storage/` interdits par `.htaccess`.
- Mots de passe hachés avec `password_hash()` (compatibilité avec les anciens
  hashs `{bcrypt}` de Spring Boot).

Après installation : changez le mot de passe admin, activez HTTPS et passez
`app.debug` à `false` (valeur par défaut en production).

## 9. Déploiement

Voir **[DEPLOIEMENT.md](DEPLOIEMENT.md)** pour XAMPP puis cPanel :
configuration, permissions, migration, sauvegardes et dépannage.
