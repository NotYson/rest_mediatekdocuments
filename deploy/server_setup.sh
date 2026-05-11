#!/usr/bin/env bash
# =============================================================================
# server_setup.sh — Provision d'un serveur Ubuntu 22.04 LTS
# Installe : Apache 2, PHP 8.2, MySQL 8, Composer, UFW
#
# Usage : sudo bash server_setup.sh
# Durée estimée : 3-5 minutes selon la connexion
# =============================================================================
set -euo pipefail

# ---------------------------------------------------------------------------
# Vérifications préalables
# ---------------------------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
    echo "ERREUR : ce script doit être exécuté en root (sudo)." >&2
    exit 1
fi

PHP_VERSION="8.2"
DB_NAME="mediatek86"
DB_APP_USER="mediatek_app"
DB_ROOT_PWD="$(openssl rand -base64 24)"
DB_APP_PWD="$(openssl rand -base64 24)"

CREDENTIALS_FILE="/root/.mediatek86_credentials"

# ---------------------------------------------------------------------------
# Mise à jour système
# ---------------------------------------------------------------------------
echo "==> [1/7] Mise à jour des paquets système"
apt-get update -qq
apt-get upgrade -y -qq

# ---------------------------------------------------------------------------
# Apache
# ---------------------------------------------------------------------------
echo "==> [2/7] Installation d'Apache2"
apt-get install -y -qq apache2

a2enmod rewrite
a2enmod headers
systemctl enable apache2

# ---------------------------------------------------------------------------
# PHP 8.2
# ---------------------------------------------------------------------------
echo "==> [3/7] Installation de PHP ${PHP_VERSION}"
apt-get install -y -qq software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update -qq
apt-get install -y -qq \
    "php${PHP_VERSION}" \
    "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-curl" \
    "libapache2-mod-php${PHP_VERSION}"

# Désactiver les versions PHP moins récentes si présentes
for other_php in /etc/apache2/mods-enabled/php*.conf; do
    modname=$(basename "${other_php}" .conf)
    if [[ "${modname}" != "php${PHP_VERSION}" ]]; then
        a2dismod "${modname}" 2>/dev/null || true
    fi
done
a2enmod "php${PHP_VERSION}"

# ---------------------------------------------------------------------------
# MySQL Server
# ---------------------------------------------------------------------------
echo "==> [4/7] Installation et configuration de MySQL"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server

systemctl enable mysql

# Sécurisation et création de la base applicative
mysql -u root <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PWD}';
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'127.0.0.1'
    IDENTIFIED BY '${DB_APP_PWD}';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON \`${DB_NAME}\`.*
    TO '${DB_APP_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------------------
# Composer
# ---------------------------------------------------------------------------
echo "==> [5/7] Installation de Composer"
EXPECTED_SIG="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
if [[ "${EXPECTED_SIG}" != "${ACTUAL_SIG}" ]]; then
    echo "ERREUR : signature Composer invalide — abandonnez et réessayez." >&2
    rm /tmp/composer-setup.php
    exit 1
fi
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
rm /tmp/composer-setup.php

# ---------------------------------------------------------------------------
# Pare-feu UFW
# ---------------------------------------------------------------------------
echo "==> [6/7] Configuration du pare-feu UFW"
apt-get install -y -qq ufw
ufw allow OpenSSH
ufw allow 'Apache Full'
ufw --force enable

# ---------------------------------------------------------------------------
# Sauvegarde des credentials
# ---------------------------------------------------------------------------
echo "==> [7/7] Sauvegarde des credentials dans ${CREDENTIALS_FILE}"
cat > "${CREDENTIALS_FILE}" <<CREDS
# Credentials générés par server_setup.sh — NE PAS PARTAGER
DB_ROOT_PWD=${DB_ROOT_PWD}
DB_APP_USER=${DB_APP_USER}
DB_APP_PWD=${DB_APP_PWD}
DB_NAME=${DB_NAME}
CREDS
chmod 600 "${CREDENTIALS_FILE}"

# ---------------------------------------------------------------------------
# Résumé
# ---------------------------------------------------------------------------
echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║              SERVEUR PRÊT — CREDENTIALS                     ║"
echo "╠══════════════════════════════════════════════════════════════╣"
printf "║  MySQL root pwd  : %-42s ║\n" "${DB_ROOT_PWD}"
printf "║  DB user         : %-42s ║\n" "${DB_APP_USER}"
printf "║  DB user pwd     : %-42s ║\n" "${DB_APP_PWD}"
printf "║  Base de données : %-42s ║\n" "${DB_NAME}"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  Ces credentials sont aussi dans : ${CREDENTIALS_FILE}"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "Étape suivante : sudo bash deploy_api.sh"
