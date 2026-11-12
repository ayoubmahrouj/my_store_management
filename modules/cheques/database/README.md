# Base de données

Ce dossier contient uniquement la **structure** de la base :

```text
database/schema.sql
```

Il crée la base `gestion_cheques`, les tables et les index, **sans aucune donnée réelle**.

## Pourquoi les données ne sont pas ici

La base de production contient des documents, des clients et des coordonnées réelles. Elle n'est **pas versionnée** dans GitHub.

Pour migrer les données, faire un export SQL local :

```bat
"C:\wamp64\bin\mysql\mysql8.4.7\bin\mysqldump.exe" -u root --databases gestion_cheques --result-file="C:\chemin\gestion_cheques.sql"
```

Puis importer ce fichier sur le nouveau PC via phpMyAdmin ou :

```bat
"C:\xampp\mysql\bin\mysql.exe" -u root < "C:\chemin\gestion_cheques.sql"
```

> Ne jamais publier un export contenant les données réelles sur un dépôt public.
