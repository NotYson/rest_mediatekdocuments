@echo off
:: =============================================================================
:: backup_config.example.bat — Template de configuration pour backup_db.bat
::
:: UTILISATION :
::   1. Copier ce fichier en backup_config.bat (dans le même dossier)
::   2. Renseigner les valeurs ci-dessous
::   3. Ne jamais committer backup_config.bat (il est dans .gitignore)
:: =============================================================================

:: Connexion MySQL
set DB_HOST=127.0.0.1
set DB_PORT=3306
set DB_USER=mediatek_app
set DB_PWD=VOTRE_MOT_DE_PASSE_ICI
set DB_NAME=mediatek86

:: Chemin complet vers mysqldump.exe
:: Exemple : C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe
set MYSQLDUMP=mysqldump

:: Dossier de destination des sauvegardes
set BACKUP_DIR=C:\Backups\Mediatek86

:: Nombre de jours de conservation (0 = conservation infinie)
set KEEP_DAYS=30
