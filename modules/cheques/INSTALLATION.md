# Installation sur un PC Windows — accès local

Ce guide explique comment installer l'application **Gestion Chèques & Effets** sur un PC Windows, puis comment y accéder :

- depuis ce même PC (`http://localhost/...`) ;
- depuis un téléphone ou un autre PC du même réseau local (`http://192.168.x.x/...`).

---

## 1. Ce qu'il faut préparer

Sur le PC cible :

- Windows 10 ou 11 ;
- **WAMP** (recommandé) ou **XAMPP** ;
- le dossier de l'application ;
- le fichier de base de données `gestion_cheques.sql`.

Deux archives sont prêtes sur le PC actuel :

| Élément | Emplacement |
|---|---|
| Dossier de l'application | `D:\dev\fullstack\CHEQUE_EFFET` |
| Sauvegarde de la base | `D:\dev\fullstack\gestion_cheques.sql` |
| Paquet complet à copier | `D:\dev\fullstack\CHEQUE_EFFET_deploy.zip` |

Le fichier ZIP contient le dossier `CHEQUE_EFFET` **et** le fichier `gestion_cheques.sql`.

> Copier le ZIP sur une clé USB, puis le décompresser sur le nouveau PC.

---

## 2. Installer WAMP

1. Télécharger WAMP 64 bits : <https://www.wampserver.com/>
2. Installer WAMP en laissant les chemins par défaut (`C:\wamp64`).
3. Lancer WAMP.
4. Attendre que l'icône WAMP devienne **verte** dans la barre des tâches.
5. Cliquer sur l'icône : les services **Apache** et **MySQL** doivent être démarrés.

Si l'icône reste orange ou rouge :

- cliquer sur WAMP → Apache → Service → Démarrer ;
- puis MySQL → Service → Démarrer ;
- vérifier qu'aucun autre programme n'utilise le port 80 (IIS, Skype, etc.).

---

## 3. Copier l'application

1. Décompresser `CHEQUE_EFFET_deploy.zip`.
2. Copier le dossier `CHEQUE_EFFET` dans :

```text
C:\wamp64\www\CHEQUE_EFFET
```

Le chemin final doit ressembler à :

```text
C:\wamp64\www\CHEQUE_EFFET\index.php
C:\wamp64\www\CHEQUE_EFFET\login.php
C:\wamp64\www\CHEQUE_EFFET\config\
```

> Ne pas mettre le dossier dans `C:\wamp64\www\CHEQUE_EFFET\CHEQUE_EFFET` (dossier en double).

---

## 4. Importer la base de données

Le fichier `gestion_cheques.sql` crée la base `gestion_cheques`, toutes les tables et toutes les données existantes.

### Méthode A — phpMyAdmin (la plus simple)

1. Ouvrir : <http://localhost/phpmyadmin/>
2. Se connecter avec l'utilisateur MySQL `root`.
3. Cliquer sur l'onglet **Importer**.
4. Choisir le fichier `gestion_cheques.sql`.
5. Cliquer sur **Exécuter / Importer**.
6. Vérifier que la base `gestion_cheques` apparaît à gauche, avec les tables :
   `document_paiement`, `carnet`, `personne`, `beneficiaire`, `utilisateur`, etc.

> Le script contient `DROP DATABASE IF EXISTS gestion_cheques`. Sur un nouveau PC, il n'y a pas de risque. Ne pas l'importer sur une base existante contenant des données à conserver.

### Méthode B — ligne de commande

Ouvrir **Invite de commandes** (`cmd`) et exécuter :

```bat
"C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe" -u root < "C:\chemin\vers\gestion_cheques.sql"
```

Adapter le chemin de `mysql.exe` si la version de MySQL est différente.

---

## 5. Configurer la connexion MySQL

Ouvrir le fichier :

```text
C:\wamp64\www\CHEQUE_EFFET\config\config.php
```

Vérifier les lignes suivantes :

```php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'gestion_cheques');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');
```

Avec WAMP, la configuration par défaut fonctionne si :

- l'utilisateur MySQL est `root` ;
- le mot de passe MySQL est vide.

Si vous avez défini un mot de passe MySQL, remplacer la dernière ligne par :

```php
define('DB_PASS', 'VOTRE_MOT_DE_PASSE_MYSQL');
```

> Ne pas ajouter d'espace avant ou après le mot de passe.

---

## 6. Vérifier les extensions PHP

Dans WAMP :

1. Clic sur l'icône WAMP → **PHP** → **PHP extensions**.
2. Vérifier que ces extensions sont cochées :
   - `php_pdo_mysql`
   - `php_mbstring`
   - `php_zip`
   - `php_curl`
   - `php_openssl`
   - `php_fileinfo`
   - `php_gd`
   - `php_intl`
3. Redémarrer Apache après toute modification.

Elles servent notamment à :

- `pdo_mysql` : connexion à la base ;
- `zip` : export Excel `.xlsx` ;
- `curl` : envoi SMS Twilio ;
- `mbstring` : accents et arabe ;
- `openssl` : SMTP sécurisé.

---

## 7. Ouvrir l'application

Dans un navigateur du PC :

```text
http://localhost/CHEQUE_EFFET/
```

Identifiants par défaut :

```text
Utilisateur : admin
Mot de passe : admin123
```

Après la première connexion :

1. aller dans **Paramètres** ;
2. changer le mot de passe ;
3. vérifier les informations de la société.

---

## 8. Accéder depuis un téléphone ou un autre PC du réseau local

Le PC qui héberge l'application doit rester allumé, avec WAMP démarré.

### 8.1 Trouver l'adresse IP du PC

Ouvrir une invite de commandes et taper :

```bat
ipconfig
```

Repérer la ligne **Adresse IPv4** de la carte réseau active, par exemple :

```text
192.168.1.20
```

### 8.2 Ouvrir l'application depuis le téléphone

Le téléphone doit être connecté au **même Wi-Fi / réseau local** que le PC.

Dans le navigateur du téléphone :

```text
http://192.168.1.20/CHEQUE_EFFET/
```

Remplacer `192.168.1.20` par l'adresse IPv4 réelle du PC.

> Ne pas utiliser `localhost` depuis le téléphone : `localhost` désigne le téléphone lui-même.

### 8.3 Autoriser le pare-feu Windows

Si la page ne s'ouvre pas :

1. Ouvrir **Pare-feu Windows Defender** → **Autoriser une application**.
2. Autoriser **Apache HTTP Server** sur les réseaux **privés**.
3. Ou créer une règle entrante pour le port 80.

En PowerShell **en administrateur** :

```powershell
New-NetFirewallRule -DisplayName "Gestion Cheques HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private
```

### 8.4 Si WAMP affiche 403 Forbidden

Ouvrir :

```text
C:\wamp64\bin\apache\apache2.4.x\conf\extra\httpd-vhosts.conf
```

Vérifier que le dossier `www` est autorisé :

```apache
<Directory "c:/wamp64/www/">
    Options +Indexes +FollowSymLinks +MultiViews
    AllowOverride All
    Require all granted
</Directory>
```

Redémarrer Apache.

### 8.5 Si le PC change d'adresse IP

L'adresse IP locale peut changer après un redémarrage de la box.

Pour la fixer :

- soit réserver l'adresse IP du PC dans l'interface de la box ;
- soit utiliser le nom du PC (`http://NOM-DU-PC/CHEQUE_EFFET/`) si la résolution locale fonctionne.

---

## 9. Alternative sans WAMP : serveur PHP intégré

Si PHP et MySQL sont déjà installés :

1. ouvrir un terminal dans le dossier de l'application :

```bat
cd C:\wamp64\www\CHEQUE_EFFET
```

2. lancer :

```bat
php -S 127.0.0.1:8001 router.php
```

3. ouvrir :

```text
http://127.0.0.1:8001/
```

Pour un accès depuis le réseau local :

```bat
php -S 0.0.0.0:8001 router.php
```

Puis ouvrir depuis le téléphone :

```text
http://192.168.1.20:8001/
```

Autoriser le port `8001` dans le pare-feu Windows si nécessaire.

Le fichier `router.php` est obligatoire avec le serveur PHP intégré : il bloque l'accès direct aux dossiers internes (`config`, `includes`, `lib`, `storage`, `database`), aux fichiers cachés (`.git`, `.env`) et aux fichiers sensibles (`.sql`, `.log`, `.json`).

---

## 10. Alertes email / SMS et tâche planifiée

Sans configuration, les alertes sont **simulées** et visibles dans l'historique de l'application.

Pour activer les envois réels, définir les variables d'environnement SMTP / Twilio (voir `README.md`).

Pour lancer les rappels automatiquement, créer une tâche planifiée Windows :

1. Ouvrir **Planificateur de tâches** ;
2. **Créer une tâche** ;
3. Déclencher toutes les minutes ;
4. Action : **Démarrer un programme** ;
5. Programme :

```text
C:\wamp64\bin\php\php8.4.7\php.exe
```

6. Arguments :

```text
C:\wamp64\www\CHEQUE_EFFET\alertes\cron.php
```

7. Démarrer dans :

```text
C:\wamp64\www\CHEQUE_EFFET
```

---

## 11. Dépannage rapide

| Problème | Solution |
|---|---|
| `Access denied for user 'root'` | Vérifier `DB_USER` / `DB_PASS` dans `config/config.php`. |
| `could not find driver` | Activer `pdo_mysql` dans WAMP, puis redémarrer Apache. |
| Page blanche | Activer temporairement `APP_DEBUG` (variable d'environnement ou ligne 84 de `config/config.php`). |
| `403 Forbidden` | Vérifier la configuration Apache du dossier `www`, puis redémarrer Apache. |
| Le port 80 est occupé | Arrêter IIS/Skype ou changer le port Apache. |
| Les styles CSS ne chargent pas | Ouvrir l'application via `http://localhost/...`, jamais via `file://`. |
| Connexion impossible depuis le téléphone | Même Wi-Fi, bonne adresse IP, pare-feu autorisé. |
| Export Excel en erreur | Activer l'extension PHP `zip`. |
| Les alertes ne partent pas | Normal en mode simulation ; configurer SMTP/Twilio. |

---

## 12. Sécurité

- Changer le mot de passe `admin123` après l'installation (8 caractères minimum, avec une lettre et un chiffre).
- Toutes les pages et API privées exigent une connexion : un accès direct par URL est redirigé vers la page de connexion.
- Les dossiers `config`, `lib`, `includes`, `storage` et `database` sont protégés par `.htaccess` sous Apache. Les fichiers cachés (`.git`, `.env`) et les fichiers `.sql`, `.log`, `.json`, etc. sont également bloqués.
- Sous Apache, vérifier que `AllowOverride All` est actif pour que le `.htaccess` soit pris en compte.
- Avec le serveur PHP intégré, lancer `php -S ... router.php` et non `-t .`.
- Session : expiration après 30 minutes d'inactivité et 8 heures maximum ; cookie `HttpOnly` et `SameSite=Lax`.
- Connexion : verrouillage temporaire après 5 échecs pendant 15 minutes. Paramétrable via `LOGIN_MAX_ATTEMPTS`, `LOGIN_WINDOW_SECONDS` et `LOGIN_LOCK_SECONDS`.
- Tous les formulaires POST sont protégés par un jeton CSRF.
- Définir un mot de passe MySQL pour `root`.
- Ne pas exposer l'application directement sur Internet.
- Pour un accès à distance, utiliser un VPN ou HTTPS. En HTTPS, le cookie passe automatiquement en `Secure` et HSTS est activé.
- Sauvegarder régulièrement la base `gestion_cheques` (export SQL).
