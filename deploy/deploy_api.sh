#!/usr/bin/env bash
# =============================================================================
# deploy_api.sh — Déploiement de l'API REST Mediatek86
# Prérequis : server_setup.sh exécuté au préalable
#
# Usage : sudo bash deploy_api.sh
# =============================================================================
set -euo pipefail

REPO_URL="https://github.com/NotYson/rest_mediatekdocuments.git"
BRANCH="main"
WEBROOT="/var/www/html/rest_mediatekdocuments"
VHOST_CONF="/etc/apache2/sites-available/rest_mediatekdocuments.conf"
LOG_DIR="${WEBROOT}/logs"
CREDENTIALS_FILE="/root/.mediatek86_credentials"

# ---------------------------------------------------------------------------
# Vérifications
# ---------------------------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
    echo "ERREUR : ce script doit être exécuté en root (sudo)." >&2
    exit 1
fi

if ! command -v composer &>/dev/null; then
    echo "ERREUR : Composer introuvable. Exécutez d'abord server_setup.sh." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Lecture des credentials existants ou saisie manuelle
# ---------------------------------------------------------------------------
if [[ -f "${CREDENTIALS_FILE}" ]]; then
    echo "==> Chargement des credentials depuis ${CREDENTIALS_FILE}"
    # shellcheck source=/dev/null
    source "${CREDENTIALS_FILE}"
else
    echo "Fichier ${CREDENTIALS_FILE} introuvable — saisie manuelle requise."
    read -rp  "Login BDD applicatif  : " DB_APP_USER
    read -rsp "Mot de passe BDD app  : " DB_APP_PWD;  echo
    read -rp  "Nom de la base        : " DB_NAME
fi

read -rp  "Domaine ou IP du serveur (ex: api.example.com ou 1.2.3.4) : " SERVER_NAME
read -rp  "Login API Basic Auth (ex: admin)   : " AUTH_USER
read -rsp "Mot de passe API Basic Auth        : " AUTH_PW; echo

# ---------------------------------------------------------------------------
# Clonage du dépôt
# ---------------------------------------------------------------------------
echo ""
echo "==> [1/6] Clonage du dépôt (branche ${BRANCH})"
if [[ -d "${WEBROOT}/.git" ]]; then
    echo "    Mise à jour du dépôt existant"
    git -C "${WEBROOT}" fetch --quiet origin "${BRANCH}"
    git -C "${WEBROOT}" reset --hard "origin/${BRANCH}"
else
    rm -rf "${WEBROOT}"
    git clone --branch "${BRANCH}" --depth 1 "${REPO_URL}" "${WEBROOT}"
fi

# ---------------------------------------------------------------------------
# Dépendances Composer
# ---------------------------------------------------------------------------
echo "==> [2/6] Installation des dépendances Composer (sans dev)"
composer install \
    --working-dir="${WEBROOT}" \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --quiet

# ---------------------------------------------------------------------------
# Fichier .env
# ---------------------------------------------------------------------------
echo "==> [3/6] Création du fichier .env de production"
mkdir -p "${LOG_DIR}"
touch "${LOG_DIR}/api.log"

cat > "${WEBROOT}/src/.env" <<ENV
AUTHENTIFICATION=basic
AUTH_USER=${AUTH_USER}
AUTH_PW=${AUTH_PW}
BDD_LOGIN=${DB_APP_USER}
BDD_PWD=${DB_APP_PWD}
BDD_BD=${DB_NAME}
BDD_SERVER=127.0.0.1
BDD_PORT=3306
LOG_PATH=${LOG_DIR}/api.log
ENV

# ---------------------------------------------------------------------------
# Import de la base de données
# ---------------------------------------------------------------------------
echo "==> [4/6] Import de la base de données"
SQL_FILE="${WEBROOT}/mediatek86.sql"
if [[ ! -f "${SQL_FILE}" ]]; then
    echo "ERREUR : ${SQL_FILE} introuvable dans le dépôt." >&2
    exit 1
fi
mysql -u "${DB_APP_USER}" -p"${DB_APP_PWD}" "${DB_NAME}" < "${SQL_FILE}"

# ---------------------------------------------------------------------------
# Permissions
# ---------------------------------------------------------------------------
echo "==> [5/6] Application des permissions"
chown -R www-data:www-data "${WEBROOT}"
chmod -R 750 "${WEBROOT}"
chmod 640 "${WEBROOT}/src/.env"
chmod 664 "${LOG_DIR}/api.log"

# Protège .env contre la lecture directe via HTTP (double sécurité avec .htaccess)
chmod 640 "${WEBROOT}/src/.env"

# ---------------------------------------------------------------------------
# Virtual Host Apache
# ---------------------------------------------------------------------------
echo "==> [6/6] Configuration du Virtual Host Apache"
cat > "${VHOST_CONF}" <<VHOST
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    DocumentRoot ${WEBROOT}

    <Directory ${WEBROOT}>
        Options -Indexes -FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Bloque l'accès direct au fichier .env
    <Files ".env">
        Require all denied
    </Files>

    # Bloque l'accès aux dossiers de tests et vendor
    <DirectoryMatch "^${WEBROOT}/(tests|vendor)">
        Require all denied
    </DirectoryMatch>

    ErrorLog  \${APACHE_LOG_DIR}/mediatekdoc_error.log
    CustomLog \${APACHE_LOG_DIR}/mediatekdoc_access.log combined
</VirtualHost>
VHOST

a2ensite rest_mediatekdocuments.conf
a2dissite 000-default.conf 2>/dev/null || true
systemctl reload apache2

# ---------------------------------------------------------------------------
# Résumé
# ---------------------------------------------------------------------------
echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                DÉPLOIEMENT TERMINÉ                          ║"
echo "╠══════════════════════════════════════════════════════════════╣"
printf "║  URL de l'API : http://%-38s ║\n" "${SERVER_NAME}/rest_mediatekdocuments/"
printf "║  Logs         : %-44s ║\n" "${LOG_DIR}/api.log"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "Test rapide :"
echo "  curl -u ${AUTH_USER}:<mot_de_passe> http://${SERVER_NAME}/rest_mediatekdocuments/livre"
