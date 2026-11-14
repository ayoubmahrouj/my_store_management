# Importer les anciennes bases dans `gestion_our_stock`

Guide complet pour faire entrer **toutes** les données des anciens projets dans
la base unique de l'application :

| Ancien projet | Base(s) possible(s) | Module cible |
| --- | --- | --- |
| Suivi de stock | `suivi_stock` | Stock |
| Gestion des crédits | `gestion_credits`, `gestion_credits_php` | Crédits & ventes |
| Chèques & effets | `gestion_cheques`, `gestion_cheques_effets` | Chèques & effets |
| Ancien POS (optionnel) | `gestionmystock` | Ventes / clients / achats |

> **Règle d'or :** les anciennes bases sont **lues seulement**. Aucun script ne
> fait d'`UPDATE`/`DELETE`/`DROP` sur `suivi_stock`, `gestion_credits` ou
> `gestion_cheques`. Faites quand même une sauvegarde avant la première fois.

---

## 1. Ce qu'il faut récupérer de chaque ancienne base

### 1.1 `suivi_stock` → module Stock

| Ancienne table | Nouvelle table | Remarque |
| --- | --- | --- |
| `roles` | `roles` | code + libellé |
| `users` | `users` | login, hash du mot de passe, rôle, site |
| `locations` | `locations` | dépôts / points de vente |
| `brands` | `brands` | dédoublonnées par nom |
| `categories` | `categories` | dédoublonnées par nom |
| `units` | `units` | code + libellé |
| `suppliers` | `suppliers` | **table séparée** des clients |
| `products` | `products` | catalogue partagé (voir § 3) |
| `stock_documents` | `stock_documents` | entrées, sorties, transferts |
| `stock_document_lines` | `stock_document_lines` | lignes |
| `stock_levels` | `stock_levels` | quantités par site |
| `stock_movements` | `stock_movements` | reconstruits aussi par `backfill_stock_movements.php` |
| `inventory_sessions` | `inventory_sessions` | inventaires |
| `inventory_lines` | `inventory_lines` | lignes d'inventaire |
| `settings` | `settings` | clé / valeur |
| `audit_logs` | `audit_logs` | journal |
| `document_sequences` | `document_sequences` | numérotation |
| `login_attempts` | — | journal technique, ignoré |

### 1.2 `gestion_credits` (ou `gestion_credits_php`) → module Crédits

| Ancienne table | Nouvelle table | Remarque |
| --- | --- | --- |
| `users` | `users` | `admin` → `admin`, les autres → `vendeur` |
| `brands` | `brands` | dédoublonnées par nom |
| `categories` | `categories` | si la table existe |
| `clients` | `clients` | **jamais** dans `beneficiaire` |
| `products` | `products` | **partagés** avec le Stock (voir § 3) |
| `sales` | `sales` | `type`/`payment_method`, `paid`/`paid_amount` |
| `sale_items` | `sale_items` | `qty`/`quantity` |
| `credits` | `credits` | `active` → `open`, `settled` → `paid` |
| `payments` | `payments` | `paid_at` → `date`, `received_by` → `user_id` |
| `payment_allocations` | `payment_allocations` | passe par `installments` |
| `installments` | — | sert seulement à relier les affectations |
| `settings` | `settings` | |
| `reminders` | — | pas de table cible (relances) |
| `cache`, `jobs`, `sessions`, `migrations`, `password_reset_tokens`, `personal_access_tokens` | — | tables techniques Laravel, ignorées |

### 1.3 `gestion_cheques` (ou `gestion_cheques_effets`) → module Chèques

| Ancienne table | Nouvelle table | Remarque |
| --- | --- | --- |
| `utilisateur` | `users` | |
| `beneficiaire` | `beneficiaire` | **table séparée** (pas `clients`) |
| `personne` | `personne` | **table séparée** (gestionnaires internes) |
| `carnet` | `cheques_carnets` | |
| `document_paiement` | `cheques_documents` | |
| `alerte_config` | `cheques_alert_config` | |
| `alerte_log` | `cheques_alert_log` | |
| `parametres_societe` | `settings` (`company_*`) | nom, adresse, téléphone, e-mail |
| `audit_log` | `audit_logs` | |

### 1.4 `gestionmystock` (ancien POS, optionnel) → ventes / achats

| Ancienne table | Nouvelle table |
| --- | --- |
| `client` | `clients` |
| `fournisseur` | `suppliers` |
| `produit` | `products` |
| `marqueproduit` | `brands` |
| `famille`, `sousfamille` | `categories` |
| `unitemesure` | `units` |
| `vente`, `venteitem` | `sales`, `sale_items` |
| `paiementclient` | `payments` |
| `achat`, `achatitem` | `stock_documents` (entrées) |
| `user` | `users` |
| `societe` | `settings` |

Au premier import, les **soldes** du POS sont repris en *crédits d'ouverture*
(vente synthétique à `total = 0`) pour que l'historique reste équilibré.

---

## 2. Les tiers sont physiquement séparés

Chaque population de tiers a **sa propre table et ses propres identifiants** :

| Population | Table | Origine |
| --- | --- | --- |
| Clients (crédits, POS) | `clients` | `gestion_credits.clients`, `gestionmystock.client` |
| Bénéficiaires de chèques | `beneficiaire` | `gestion_cheques.beneficiaire` |
| Personnes internes | `personne` | `gestion_cheques.personne` |
| Fournisseurs | `suppliers` | `suivi_stock.suppliers`, `gestionmystock.fournisseur` |

Une même personne qui est **à la fois** client et bénéficiaire existe dans les
deux tables, avec deux identifiants indépendants. C'est volontaire : aucune
fiche n'est exposée dans le mauvais écran.

Les documents gardent leurs clés étrangères d'origine :

- `cheques_documents.beneficiaire_id` → `beneficiaire.id`
- `cheques_documents.personne_id` et `cheques_carnets.personne_id` → `personne.id`
- `stock_documents.supplier_id` → `suppliers.id`
- `sales.client_id` et `credits.client_id` → `clients.id`

---

## 3. Les produits sont partagés Stock ↔ Crédits

C'est le point le plus sensible : `suivi_stock.products` et
`gestion_credits.products` décrivent **le même catalogue physique**.

L'import ne crée donc pas deux fiches pour le même article. Il cherche, dans
l'ordre :

1. le **code-barres** identique ;
2. le **SKU** identique ;
3. la **désignation + la marque** identiques (comparaison normalisée :
   minuscules, sans accents, ponctuation et espaces multiples ignorés).

Si la fiche existe déjà (par exemple créée par le Stock), la ligne de vente
Crédits est reliée à **cette** fiche. Si elle n'existe pas, elle est créée une
seule fois. Le Stock reste la source de vérité pour les prix d'achat et les
quantités ; les prix de vente Crédits ne sont pas écrasés.

Exemple concret :

| Source | Désignation | Marque | Résultat |
| --- | --- | --- | --- |
| `suivi_stock.products` | `BATTERIE L2` | BOSCH | fiche créée |
| `gestion_credits.products` | `batterie  l2` | BOSCH | **même fiche** réutilisée |

Le script `database/merge_legacy_dumps.php` utilise exactement la même règle.

---

## 4. Prérequis

- PHP **8.1+** avec `pdo_mysql`, `mbstring`, `json`, `openssl`
- MySQL 5.7+ ou MariaDB 10.4+
- Les anciennes bases et la base cible sur **le même serveur MySQL/MariaDB**
  (les scripts lisent les sources avec la connexion de l'application)
- Un compte MySQL qui peut **lire** les anciennes bases et **écrire** dans la
  base cible

> ⚠️ **Important** : si vos anciennes bases sont sur un autre serveur ou un autre
> port (par exemple MySQL 3306 et la cible sur MariaDB 3307), importez d'abord
> les anciennes bases sur le serveur de la cible (voir § 6.1). Sinon
> `migrate_legacy.php` ne les verra pas.

---

## 5. Méthode A — Import complet (recommandée)

Elle vide la base cible puis réimporte **Stock + Crédits + Chèques** (et le POS
si demandé). C'est la méthode utilisée par `MIGRER_DONNEES.bat`.

### 5.1 Configurer la connexion

Créer `shared/config.local.php` (non versionné) à partir de
`deploy/config.local.example.php`, ou renseigner les variables d'environnement
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.

### 5.2 Lancer les commandes

Depuis le dossier `gestion_unifiee` :

```bash
php database/install.php --fresh
php database/migrate_legacy.php --force
php database/import_gestionmystock.php --db=gestionmystock   # optionnel
php database/backfill_stock_movements.php
php database/doctor.php
```

Ou, sous Windows, double-cliquer sur **`MIGRER_DONNEES.bat`** : il sauvegarde
la base, la recrée, importe les trois anciennes bases, le POS, reconstruit les
mouvements et lance le diagnostic.

### 5.3 Bases avec préfixe d'hébergeur (cPanel)

Si les bases s'appellent `moncompte_suivi_stock`, etc. :

```bash
LEGACY_STOCK_DB=moncompte_suivi_stock \
LEGACY_CREDITS_DB=moncompte_gestion_credits \
LEGACY_CHEQUES_DB=moncompte_gestion_cheques \
php database/migrate_legacy.php --force

php database/import_gestionmystock.php --db=moncompte_gestionmystock
```

Sous Windows (`cmd`) :

```bat
set LEGACY_STOCK_DB=moncompte_suivi_stock
set LEGACY_CREDITS_DB=moncompte_gestion_credits
set LEGACY_CHEQUES_DB=moncompte_gestion_cheques
php database\migrate_legacy.php --force
```

Noms reconnus automatiquement :

- Stock : `suivi_stock`
- Crédits : `gestion_credits_php`, puis `gestion_credits`
- Chèques : `gestion_cheques`, puis `gestion_cheques_effets`

### 5.4 Ce que fait `--force`

`migrate_legacy.php --force` **vide** les tables cibles puis réimporte tout.
Il ne touche jamais aux anciennes bases. Sans `--force`, le script s'arrête dès
que la base cible contient déjà des utilisateurs.

Tables vidées : ventes, lignes, crédits, paiements, affectations, documents et
mouvements de stock, inventaires, chèques, paramètres, produits, marques,
catégories, unités, clients, **bénéficiaires**, **personnes**, **fournisseurs**,
utilisateurs, sites et rôles.

---

## 6. Méthode B — À partir de fichiers `.sql` (phpMyAdmin)

Vous n'avez que des exports `.sql` (par exemple les dumps de l'hébergeur) et
pas les bases en ligne.

### 6.1 Remettre les anciennes bases sur le serveur cible

1. Ouvrir phpMyAdmin sur le serveur de l'application.
2. Créer une base par ancien projet, avec **le nom attendu** :
   `suivi_stock`, `gestion_credits`, `gestion_cheques` (ou
   `gestion_credits_php`, `gestion_cheques_effets`).
3. Onglet **Importer** → choisir le dump → **Exécuter**.
4. Vérifier que les tables sont bien là.

> Les dumps `gestion_credits_php` de l'hébergeur contiennent parfois
> `utf8mb4_0900_ai_ci` (MySQL 8). MariaDB refuse cette collation. Remplacez-la
> par `utf8mb4_unicode_ci` dans le fichier avant l'import, ou importez sur MySQL.

### 6.2 Puis appliquer la méthode A

```bash
php database/install.php --fresh
php database/migrate_legacy.php --force
php database/backfill_stock_movements.php
php database/doctor.php
```

### 6.3 Variante : fusionner dans une base **déjà remplie**

Si vous ne voulez **rien supprimer** :

```bash
php database/merge_legacy_dumps.php --dry-run   # aperçu, aucune écriture
php database/merge_legacy_dumps.php             # écrit les ajouts
```

Cette commande est **additive et idempotente** : elle ajoute uniquement ce qui
manque, rapproche les produits par code-barres / SKU / désignation + marque,
les clients par nom, les ventes par client + date + total, les crédits par
vente et les paiements par client + date + montant + mode. Les bénéficiaires
vont dans `beneficiaire`, jamais dans `clients`.

### 6.4 Variante : scripts SQL autonomes (Chèques et Crédits)

Pour injecter les données de deux anciens modules directement dans une base
existante, sans passer par les bases sources :

| Fichier | Contenu |
| --- | --- |
| `storage/backups/MIGRATION_CHEQUES_vers_my_store_management.sql` | bénéficiaires, personnes, carnets, documents, alertes |
| `storage/backups/MIGRATION_CREDITS_vers_my_store_management.sql` | clients, produits, ventes, crédits, paiements |
| `storage/backups/MIGRATION_COMPLETE_vers_my_store_management.sql` | les deux, en un seul import |

Mode d'emploi : phpMyAdmin → sélectionner la base de l'application →
**Importer** → choisir le fichier → **Exécuter**.

Ces scripts :

- ne créent pas de base et ne font pas de `USE` : ils s'appliquent à la base
  sélectionnée ;
- sont **idempotents** (relançables sans doublons) ;
- chargent les données dans des tables temporaires `zzimp_*`, puis les
  convertissent vers le schéma cible et suppriment le staging ;
- ne touchent pas aux tables Stock, Clients, Fournisseurs ni aux données déjà
  présentes (sauf ajout des enregistrements manquants).

> Ces fichiers contiennent de **vraies données clients** : ils ne sont pas
> versionnés dans Git. Conservez-les hors du dépôt.

---

## 7. Vérifier après l'import

### 7.1 Diagnostic automatique

```bash
php database/doctor.php
```

Attendu : `Diagnostic terminé : tout est opérationnel.`

### 7.2 Comptages

```sql
SELECT 'clients' AS table_name, COUNT(*) AS total FROM clients
UNION ALL SELECT 'beneficiaire', COUNT(*) FROM beneficiaire
UNION ALL SELECT 'personne',     COUNT(*) FROM personne
UNION ALL SELECT 'suppliers',    COUNT(*) FROM suppliers
UNION ALL SELECT 'products',     COUNT(*) FROM products
UNION ALL SELECT 'sales',        COUNT(*) FROM sales
UNION ALL SELECT 'sale_items',   COUNT(*) FROM sale_items
UNION ALL SELECT 'credits',      COUNT(*) FROM credits
UNION ALL SELECT 'payments',     COUNT(*) FROM payments
UNION ALL SELECT 'cheques_documents', COUNT(*) FROM cheques_documents;
```

### 7.3 Aucune référence orpheline

```sql
SELECT 'sales.client_id' AS controle, COUNT(*) AS orphelins
FROM sales s LEFT JOIN clients c ON c.id = s.client_id
WHERE s.client_id IS NOT NULL AND c.id IS NULL
UNION ALL
SELECT 'sale_items.product_id', COUNT(*)
FROM sale_items i LEFT JOIN products p ON p.id = i.product_id
WHERE i.product_id IS NOT NULL AND p.id IS NULL
UNION ALL
SELECT 'credits.sale_id', COUNT(*)
FROM credits c LEFT JOIN sales s ON s.id = c.sale_id WHERE s.id IS NULL
UNION ALL
SELECT 'credits.client_id', COUNT(*)
FROM credits c LEFT JOIN clients x ON x.id = c.client_id WHERE x.id IS NULL
UNION ALL
SELECT 'cheques_documents.beneficiaire_id', COUNT(*)
FROM cheques_documents d LEFT JOIN beneficiaire b ON b.id = d.beneficiaire_id
WHERE b.id IS NULL
UNION ALL
SELECT 'stock_documents.supplier_id', COUNT(*)
FROM stock_documents d LEFT JOIN suppliers s ON s.id = d.supplier_id
WHERE d.supplier_id IS NOT NULL AND s.id IS NULL;
```

Attendu : **0** partout.

### 7.4 Aucun doublon Stock ↔ Crédits

```sql
SELECT designation, brand_id, COUNT(*) AS n
FROM products
GROUP BY designation, brand_id
HAVING COUNT(*) > 1
ORDER BY n DESC;
```

Les lignes retournées doivent être uniquement des articles **réellement
distincts** du catalogue POS (même libellé, référence différente), jamais un
article du Stock dupliqué par les Crédits.

### 7.5 Total des ventes

```sql
SELECT COUNT(*) AS ventes, SUM(total) AS chiffre_affaires FROM sales;
SELECT COUNT(*) AS credits_ouverts, SUM(remaining) AS reste
FROM credits WHERE status = 'open';
```

---

## 8. Dépannage

| Problème | Cause | Solution |
| --- | --- | --- |
| `Stock : absente` | base sur un autre serveur/port | importer le dump sur le serveur de la cible (§ 6.1) ou renseigner `LEGACY_STOCK_DB` |
| `Crédits : absente` | nom différent | `LEGACY_CREDITS_DB=monprefix_gestion_credits` |
| `Chèques : absente` | base nommée `gestion_cheques_effets` | reconnue automatiquement, sinon `LEGACY_CHEQUES_DB=...` |
| `#1273 Unknown collation utf8mb4_0900_ai_ci` | dump MySQL 8 importé sur MariaDB | remplacer par `utf8mb4_unicode_ci` |
| `La base cible contient déjà N utilisateur(s)` | `--force` oublié | `php database/migrate_legacy.php --force` |
| Doublons de produits | ancienne version du script | utiliser la version actuelle (`product_key_text`) et relancer `install --fresh` + `migrate --force` |
| Bénéficiaires visibles dans Clients | ancienne version de `merge_legacy_dumps.php` | mettre à jour le script : ils vont dans `beneficiaire` |
| Mot de passe admin inconnu | réinstallation | `admin` / `Admin@2026`, puis le changer |

---

## 9. Checklist finale

- [ ] Sauvegarde de la base cible faite (le `.bat` la fait automatiquement).
- [ ] Anciennes bases accessibles depuis le serveur de la cible.
- [ ] `php database/install.php --fresh` OK.
- [ ] `php database/migrate_legacy.php --force` OK (Stock + Crédits + Chèques).
- [ ] `php database/import_gestionmystock.php --db=...` OK (si POS).
- [ ] `php database/backfill_stock_movements.php` OK.
- [ ] `php database/doctor.php` : tout opérationnel.
- [ ] Comptages et contrôles d'orphelins : 0 anomalie.
- [ ] Bénéficiaires dans `beneficiaire`, personnes dans `personne`, clients dans
      `clients`, fournisseurs dans `suppliers`.
- [ ] Aucun doublon Stock ↔ Crédits.
- [ ] Mot de passe `admin` changé.

---

## 10. Résumé des commandes

```bash
# Import complet (vide puis réimporte)
php database/install.php --fresh
php database/migrate_legacy.php --force
php database/import_gestionmystock.php --db=gestionmystock
php database/backfill_stock_movements.php
php database/doctor.php

# Fusion additive dans une base existante
php database/merge_legacy_dumps.php --dry-run
php database/merge_legacy_dumps.php
```
