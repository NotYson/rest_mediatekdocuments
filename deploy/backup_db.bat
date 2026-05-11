@echo off
:: =============================================================================
:: backup_db.bat — Sauvegarde quotidienne de la base MySQL mediatek86
::
:: Prérequis :
::   - Copier backup_config.example.bat en backup_config.bat et le renseigner
::   - mysqldump.exe accessible (PATH ou chemin complet dans backup_config.bat)
::
:: Planification automatique :
::   Importer backup_mediatek86.xml dans le Planificateur de tâches Windows :
::   schtasks /Create /XML "%~dp0backup_mediatek86.xml" /TN "Backup Mediatek86"
::   (adapter le chemin du script dans le XML avant import)
:: =============================================================================
setlocal EnableDelayedExpansion

:: ---------------------------------------------------------------------------
:: Chargement de la configuration depuis backup_config.bat
:: ---------------------------------------------------------------------------
set CONFIG_FILE=%~dp0backup_config.bat

if not exist "%CONFIG_FILE%" (
    echo ERREUR : fichier de configuration introuvable.
    echo   Copiez backup_config.example.bat en backup_config.bat
    echo   et renseignez les variables.
    exit /b 1
)
call "%CONFIG_FILE%"

:: Vérification des variables obligatoires
if "%DB_PWD%"=="VOTRE_MOT_DE_PASSE_ICI" (
    echo ERREUR : DB_PWD n'a pas ete configure dans backup_config.bat.
    exit /b 1
)
if "%DB_USER%"=="" (
    echo ERREUR : DB_USER est vide dans backup_config.bat.
    exit /b 1
)
if "%DB_NAME%"=="" (
    echo ERREUR : DB_NAME est vide dans backup_config.bat.
    exit /b 1
)

:: ---------------------------------------------------------------------------
:: Timestamp (PowerShell — indépendant de la locale Windows)
:: ---------------------------------------------------------------------------
for /f "tokens=*" %%i in (
    'powershell -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'"'
) do set TIMESTAMP=%%i

if "%TIMESTAMP%"=="" (
    echo ERREUR : impossible de generer le timestamp ^(PowerShell requis^).
    exit /b 1
)

:: ---------------------------------------------------------------------------
:: Création du dossier de sauvegarde
:: ---------------------------------------------------------------------------
if not exist "%BACKUP_DIR%" (
    mkdir "%BACKUP_DIR%"
    if errorlevel 1 (
        echo ERREUR : impossible de creer le dossier %BACKUP_DIR%.
        exit /b 1
    )
)

set BACKUP_FILE=%BACKUP_DIR%\mediatek86_%TIMESTAMP%.sql

:: ---------------------------------------------------------------------------
:: Fichier d'options MySQL temporaire
:: (évite le mot de passe dans la liste des processus)
:: ---------------------------------------------------------------------------
set OPT_FILE=%TEMP%\mediatek86_mysqldump_%TIMESTAMP%.cnf

(
    echo [mysqldump]
    echo user=%DB_USER%
    echo password=%DB_PWD%
    echo host=%DB_HOST%
    echo port=%DB_PORT%
) > "%OPT_FILE%"

:: ---------------------------------------------------------------------------
:: Dump MySQL
:: ---------------------------------------------------------------------------
echo [%TIMESTAMP%] Debut de la sauvegarde : %BACKUP_FILE%

"%MYSQLDUMP%" ^
    --defaults-extra-file="%OPT_FILE%" ^
    --single-transaction ^
    --routines ^
    --triggers ^
    --set-gtid-purged=OFF ^
    "%DB_NAME%" > "%BACKUP_FILE%"

set DUMP_EXIT=%errorlevel%

:: Suppression immédiate du fichier d'options temporaire
del "%OPT_FILE%" 2>nul

if %DUMP_EXIT% neq 0 (
    echo ERREUR : mysqldump a echoue ^(code %DUMP_EXIT%^).
    echo   Verifiez les credentials, le nom de la base et l'etat du serveur MySQL.
    if exist "%BACKUP_FILE%" del "%BACKUP_FILE%"
    exit /b 1
)

:: Vérification que le fichier n'est pas vide
for %%F in ("%BACKUP_FILE%") do set BACKUP_SIZE=%%~zF
if "%BACKUP_SIZE%"=="0" (
    echo ERREUR : le fichier de sauvegarde est vide.
    del "%BACKUP_FILE%"
    exit /b 1
)

echo [%TIMESTAMP%] Sauvegarde creee : %BACKUP_FILE% ^(%BACKUP_SIZE% octets^)

:: ---------------------------------------------------------------------------
:: Rotation : suppression des sauvegardes plus anciennes que KEEP_DAYS jours
:: ---------------------------------------------------------------------------
if %KEEP_DAYS% gtr 0 (
    forfiles /p "%BACKUP_DIR%" /m "mediatek86_*.sql" /d -%KEEP_DAYS% ^
        /c "cmd /c echo Suppression : @file && del @file" 2>nul
)

endlocal
exit /b 0
