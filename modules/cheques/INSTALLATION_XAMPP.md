# Installation avec XAMPP — Windows

Guide pas à pas pour installer **Gestion Chèques & Effets** avec **XAMPP** et y accéder en local, puis depuis un téléphone ou un autre PC du réseau local.

---

## 1. Télécharger et installer XAMPP

1. Télécharger XAMPP pour Windows : <https://www.apachefriends.org/fr/index.html>
2. Choisir une version avec **PHP 8.2 ou supérieur**.
3. Installer dans le chemin par défaut :

```text
C:\xampp
```

> Éviter `C:\Program Files`, qui peut poser des problèmes de permissions.

4. Lancer **XAMPP Control Panel** en administrateur.
5. Démarrer les modules :
   - **Apache**
   - **MySQL**

Les deux doivent être au vert.

---

## 2. Vérifier les ports (important)

### Apache — port 80 occupé

Si Apache ne démarre pas :

1. Dans XAMPP : **Config** → **Apache (httpd.conf)**.
2. Chercher :

```apache
Listen 80
```

3. Remplacer par :

```apache
Listen 8080
```

4. Chercher aussi :

```apache
ServerName localhost:80
```

5. Remplacer par :

```apache
ServerName localhost:8080
```

6. Enregistrer, puis redémarrer Apache.

L’adresse devient alors :

```text
http://localhost:8080/CHEQUE_EFFET/
```

### MySQL — port 3306 occupé

Si MySQL ne démarre pas :

1. XAMPP : **Config** → **my.ini**.
2. Dans `[mysqld]`, remplacer :

```ini
port=3306
```

par :

```ini
port=3307
```

3. Vérifier aussi la section `[client]` et mettre `port=3307`.
4. Redémarrer MySQL.
5. Dans l’application, mettre `DB_PORT` à `3307` (voir étape 5).

> Ne pas lancer WAMP et XAMPP en même temps : ils utilisent les mêmes ports.

---

## 3. Copier l’application

1. Décompresser `CHEQUE_EFFET_deploy.zip`.
2. Copier le dossier `CHEQUE_EFFET` dans :

```text
C:\xampp\htdocs\CHEQUE_EFFET
```

Le résultat doit être :

```text
C:\xampp\htdocs\CHEQUE_EFFET\index.php
C:\xampp\htdocs\CHEQUE_EFFET\login.php
C:\xampp\htdocs\CHEQUE_EFFET\config\
```

> Ne pas créer `C:\xampp\htdocs\CHEQUE_EFFET\CHEQUE_EFFET`.

---

## 4. Importer la base de données

Le fichier `gestion_cheques.sql` crée la base, les tables et toutes les données existantes.

### Méthode A — phpMyAdmin

1. Ouvrir :

```text
http://localhost/phpmyadmin/
```

ou, si Apache est sur 8080 :

```text
http://localhost:8080/phpmyadmin/
```

2. Se connecter avec :
   - utilisateur : `root`
   - mot de passe : vide (par défaut sous XAMPP)
3. Onglet **Importer**.
4. Choisir `gestion_cheques.sql`.
5. Cliquer sur **Exécuter**.

### Méthode B — ligne de commande

Ouvrir `cmd` et lancer :

```bat
"C:\xampp\mysql\bin\mysql.exe" -u root < "C:\chemin\vers\gestion_cheques.sql"
```

> Le dump a été rendu compatible avec MySQL et MariaDB (XAMPP utilise MariaDB). Il contient `DROP DATABASE IF EXISTS gestion_cheques` : à utiliser sur un nouveau PC, pas sur une base existante à conserver.

---

## 5. Configurer la connexion MySQL

Ouvrir :

```text
C:\xampp\htdocs\CHEQUE_EFFET\config\config.php
```

Vérifier :

```php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gestion_cheques');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');
```

Avec XAMPP par défaut :

- `DB_USER` = `root`
- `DB_PASS` = vide

Si vous avez défini un mot de passe MySQL :

```php
define('DB_PASS', 'VOTRE_MOT_DE_PASSE');
```

Si vous avez changé le port MySQL en 3307 :

```php
define('DB_PORT', getenv('DB_PORT') ?: '3307');
```

---

## 6. Activer les extensions PHP

Ouvrir :

```text
C:\xampp\php\php.ini
```

Vérifier que ces lignes sont **sans point-virgule** au début :

```ini
extension=pdo_mysql
extension=mbstring
extension=zip
extension=curl
extension=openssl
extension=fileinfo
extension=gd
extension=intl
```

Sous XAMPP, `zip` est souvent désactivé : c’est indispensable pour l’export **Excel / XLSX**.

Après modification :

1. Enregistrer `php.ini`.
2. Redémarrer Apache depuis XAMPP.

---

## 7. Ouvrir l’application

Dans le navigateur :

```text
http://localhost/CHEQUE_EFFET/
```

ou si Apache est sur le port 8080 :

```text
http://localhost:8080/CHEQUE_EFFET/
```

Connexion :

```text
Utilisateur : admin
Mot de passe : admin123
```

Après la première connexion :

1. aller dans **Paramètres** ;
2. changer le mot de passe ;
3. vérifier les informations de la société.

---

## 8. Accès depuis un téléphone ou un autre PC

Le PC doit rester allumé, avec Apache et MySQL démarrés dans XAMPP.

### 8.1 Trouver l’adresse IP

Dans `cmd` :

```bat
ipconfig
```

Repérer **Adresse IPv4**, par exemple :

```text
192.168.1.20
```

### 8.2 Ouvrir depuis le téléphone

Le téléphone doit être sur le **même Wi-Fi**.

```text
http://192.168.1.20/CHEQUE_EFFET/
```

Si Apache est sur 8080 :

```text
http://192.168.1.20:8080/CHEQUE_EFFET/
```

> `localhost` ne fonctionne pas depuis le téléphone.

### 8.3 Autoriser le pare-feu Windows

En PowerShell **administrateur** :

```powershell
New-NetFirewallRule -DisplayName "Gestion Cheques HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private
```

Si port 8080 :

```powershell
New-NetFirewallRule -DisplayName "Gestion Cheques HTTP 8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -Profile Private
```

### 8.4 Si XAMPP renvoie 403 Forbidden

Ouvrir :

```text
C:\xampp\apache\conf\httpd.conf
```

Vérifier :

```apache
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks Includes ExecCGI
    AllowOverride All
    Require all granted
</Directory>
```

Redémarrer Apache.

---

## 9. Alternative : serveur PHP intégré de XAMPP

Si Apache pose problème, utiliser PHP CLI de XAMPP :

```bat
cd C:\xampp\htdocs\CHEQUE_EFFET
C:\xampp\php\php.exe -S 0.0.0.0:8001 router.php
```

Le fichier `router.php` est obligatoire avec le serveur PHP intégré : il bloque l’accès direct aux dossiers internes (`config`, `includes`, `lib`, `storage`, `database`), aux fichiers cachés (`.git`, `.env`) et aux fichiers sensibles (`.sql`, `.log`, `.json`).

Puis :

```text
http://127.0.0.1:8001/
```

Depuis un autre appareil :

```text
http://192.168.1.20:8001/
```

MySQL doit quand même être démarré depuis XAMPP.

---

## 10. Alertes et cron

Sans SMTP/Twilio configurés, les alertes sont simulées et visibles dans l’historique.

Pour exécuter les rappels automatiquement, créer une tâche planifiée Windows :

- **Programme :**

```text
C:\xampp\php\php.exe
```

- **Arguments :**

```text
C:\xampp\htdocs\CHEQUE_EFFET\alertes\cron.php
```

- **Démarrer dans :**

```text
C:\xampp\htdocs\CHEQUE_EFFET
```

Planifier toutes les minutes.

Si le cron est appelé par URL, utiliser un en-tête HTTP et non un paramètre dans l’URL :

```text
X-Cron-Token: VOTRE_JETON_DE_32_CARACTERES_MINIMUM
```

ou :

```text
Authorization: Bearer VOTRE_JETON_DE_32_CARACTERES_MINIMUM
```

Définir `CRON_TOKEN` avec une valeur longue et aléatoire. Le jeton par défaut `change-me-cron-token` est refusé.

---

## 11. Dépannage rapide

| Problème | Solution |
|---|---|
| Apache ne démarre pas | Port 80 occupé → passer à 8080 |
| MySQL ne démarre pas | Port 3306 occupé → passer à 3307 + `DB_PORT` |
| `Access denied for user 'root'` | Vérifier `DB_USER` / `DB_PASS` |
| `could not find driver` | Activer `pdo_mysql`, redémarrer Apache |
| Page blanche | Activer temporairement `APP_DEBUG` |
| 403 Forbidden | Vérifier `Require all granted` dans `httpd.conf` |
| Export Excel en erreur | Activer `extension=zip` dans `php.ini` |
| Téléphone ne se connecte pas | Même Wi-Fi + IP correcte + pare-feu |
| Les styles ne chargent pas | Ouvrir via `http://localhost/...`, pas `file://` |
| Base vide après import | Vérifier que l’import a bien créé `gestion_cheques` |

---

## 12. Sécurité

- Changer `admin123` après installation (nouveau mot de passe : 8 caractères minimum, avec une lettre et un chiffre).
- Toutes les pages et API privées exigent une connexion : un accès direct par URL est redirigé vers `login.php`.
- Le fichier `.htaccess` à la racine protège les dossiers internes et les fichiers sensibles. Il nécessite `AllowOverride All` dans Apache (voir § 8.4).
- Avec le serveur PHP intégré, toujours lancer `php -S ... router.php`, jamais `-t .` seul.
- Session : expiration après 30 minutes d’inactivité et 8 heures maximum. Le cookie est `HttpOnly` et `SameSite=Lax`.
- Connexion : verrouillage temporaire après 5 échecs pendant 15 minutes. Ces valeurs sont paramétrables par les variables `LOGIN_MAX_ATTEMPTS`, `LOGIN_WINDOW_SECONDS` et `LOGIN_LOCK_SECONDS`.
- Mettre un mot de passe à MySQL `root`.
- Ne pas exposer XAMPP directement sur Internet.
- Pour un accès distant, utiliser un VPN ou HTTPS. En HTTPS, le cookie de session passe automatiquement en `Secure` et HSTS est activé.
- Sauvegarder régulièrement la base `gestion_cheques`.
- Le ZIP contient la base et le hash du mot de passe : le conserver dans un endroit sûr, puis le supprimer de la clé USB après installation.
