# Audit de performance & qualité — `gestion_unifiee`

**Date :** 12/11/2026
**Périmètre :** application unifiée (Stock, Crédits, Chèques/Effets), base `gestion_our_stock`.
**Objectif :** application légère, sans redondance, de niveau professionnel.

---

## 1. Résumé

L'application fonctionnait mais plusieurs pages envoyaient **plusieurs mégaoctets de HTML**
au navigateur (jusqu'à ~6 Mo), principalement parce qu'elles affichaient **toutes** les lignes
d'un coup (produits, documents, référentiels) et parce que certaines pages **dupliquaient** les
mêmes données (liste de produits en `<datalist>` **et** en JSON JavaScript).

Actions menées :

- **Pagination serveur** (50 lignes/page) sur toutes les grandes listes.
- **Suppression des doublons** de données (datalist/selects de produits).
- **Allègement des assets** (Bootstrap et jQuery inutiles retirés).
- **Correction de 3 bugs** dont une erreur HTTP 500 et deux exports silencieusement tronqués.

Résultat global : **les pages passent de ~15 Mo cumulés à ~1,3 Mo cumulés** (mesures ci-dessous),
soit une division par ~10 à 40 selon la page. Aucune perte de fonctionnalité.

---

## 2. Mesures avant / après

Toutes les mesures sont le **poids HTML réel** (octets téléchargés, serveur de dev `php -S`,
connecté en `admin`). « Après » = après pagination + suppression des doublons.

| Page | Avant | Après | Gain |
|---|---:|---:|---:|
| Stock · Produits | 5 972,5 Ko | **164,9 Ko** | ×36 |
| Stock · Consultation | 4 015,7 Ko | **161,6 Ko** | ×25 |
| Stock · Rapports | 2 414,3 Ko | **208,9 Ko** | ×12 |
| Stock · Entrées | 1 482,6 Ko | **104,4 Ko** | ×14 |
| Stock · Sorties | 558,6 Ko | **21,0 Ko** | ×27 |
| Stock · Transferts | 558,5 Ko | **20,9 Ko** | ×27 |
| Stock · Référentiels | 496,9 Ko | **132,1 Ko** | ×3,8 |
| Crédits · POS | 677,5 Ko | **332,1 Ko** | ×2 |
| Crédits · Clients | 647,3 Ko | **95,3 Ko** | ×6,8 |

> Le POS reste la page la plus lourde (332 Ko) car elle embarque légitimement les 3 247 produits
> pour l'auto-complétion hors-ligne. C'est le seul endroit où charger les produits en une fois est
> justifié (usage caisse, coupures réseau). Voir §6.

**Assets (téléchargements réseau) :**

- Chèques : Bootstrap CSS + JS retirés (−312 Ko, requêtes 11 → 9). `jQuery` conservé (205 usages réels).
- Stock : jQuery CDN retiré du gabarit (aucun usage réel côté Stock/Shared).

---

## 3. Corrections de bugs

| # | Bug | Impact | Correctif |
|---|---|---|---|
| 1 | `credits/?p=clients&owed=1` renvoyait **HTTP 500** | Page « clients débiteurs » inaccessible | L'alias `balance` était utilisé dans le `WHERE` ; requête réécrite avec sous-table dérivée |
| 2 | Export Consultation tronqué à **2 000 lignes** (`LIMIT 2000` codé en dur) | Export incomplet silencieux (3 231 lignes en base) | `LIMIT` retiré, l'export prend tout |
| 3 | Export Documents tronqué à **1 000 lignes** | Idem | `LIMIT 1000` remplacé par une pagination explicite (liste) sans plafond pour l'export |

---

## 4. Redondances supprimées

1. **POS — liste produits en double.** Les 3 247 produits étaient envoyés **deux fois** :
   une fois en `<datalist>` (3 247 `<option>`), une fois en tableau JSON pour le JavaScript.
   → `<datalist>` supprimé ; auto-complétion reconstruite à partir du **tableau JSON existant**
   (une seule source de vérité). Le POS passe de 3 648 à 401 `<option>`.
2. **Filtre « Produit » des documents** (Entrées/Sorties/Transferts) : `<select>` de **3 247 options**
   rechargé à chaque affichage, alors que la recherche `q` filtrait **déjà** par SKU/désignation produit.
   → remplacé par un champ texte « SKU ou nom du produit » (filtre serveur `product_q`).
   Supprime ~550 Ko **par page** (3 pages).
3. **Assets dupliqués** : Bootstrap (CSS/JS) et jQuery retirés là où ils n'étaient pas utilisés.

---

## 5. Pagination serveur ajoutée (50/page)

- Stock : **Produits**, **Consultation**, **Entrées**, **Sorties**, **Transferts**, **Rapports**
  (tableau « Stock valorisé » **et** journal des mouvements), **Référentiels** (marques, catégories,
  fournisseurs — 3 pagers indépendants).
- Crédits : **Clients**.

Le composant de pagination est **mutualisé** : `modules/stock/app/Views/partials/pager.php`
(un seul gabarit réutilisé, styles `.table-pager` déjà présents dans le thème).
Les filtres, le tri et l'export restent actifs ; l'export renvoie **toujours l'intégralité** des
lignes filtrées (indépendant de la page affichée).

---

## 6. Points ouverts (à valider)

### 6.1 POS — 332 Ko
Le POS charge les 3 247 produits côté client pour l'auto-complétion immédiate (hors-ligne).
Options possibles :
- **Statut quo** (recommandé) : usage caisse, latence nulle, ~40 Ko gzip.
- Auto-complétion serveur (`/api/v1/products?q=`) : page plus légère mais dépend du réseau à chaque frappe.

### 6.2 Dossiers `database/` au niveau des modules (héritage)
`modules/stock/database/`, `modules/credits/database/`, `modules/cheques/database/`
sont des **schémas hérités** des anciens modules autonomes. Aucun code ne les référence ;
le schéma actif est `database/schema.sql` (racine). Seuls les `.md` des modules les citent encore.
→ **Rien n'a été supprimé** (fichiers préservés). Recommandation : archiver vers
`storage/backups/legacy_module_databases_20261112/` et mettre à jour les 3 `.md`.

### 6.3 Hébergement cPanel
Activer **mod_deflate/gzip** (HTML/JSON/CSS/JS) : les pages ci-dessus tombent à ~20–50 Ko sur le réseau.
Un `.htaccess` avec `AddOutputFilterByType DEFLATE` peut être ajouté.

---

## 7. Garanties

- Aucune fonctionnalité supprimée ; tous les filtres, tris et exports conservés.
- `database/upgrade.php` : OK. `database/doctor.php` : **tout est opérationnel** (32 tables, 8 vues, admin actif).
- Test de fumée complet : Stock (23 pages), Crédits (9 pages), Chèques (6 pages), Dashboard unifié, API v1 → **200 partout**, aucune erreur PHP.
- Toutes les pages modifiées passent `php -l`.
