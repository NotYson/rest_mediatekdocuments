#!/usr/bin/env bash
# =============================================================================
# backup_db.sh — Sauvegarde quotidienne de la base MySQL mediatek86
#
# Usage : bash backup_db.sh [fichier_credentials]
#   fichier_credentials : chemin vers le fichier de credentials
#                         (défaut : /root/.mediatek86_credentials)
#
# Format attendu du fichier de credentials (généré par server_setup.sh) :
#   DB_APP_USER=mediatek_app
#   DB_APP_PWD=...
#   DB_NAME=mediatek86
#
# Ce script est destiné à être appelé par la cron installée avec install_cron.sh
# =============================================================================
set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
CREDENTIALS_FILE="${1:-/root/.mediatek86_credentials}"
BACKUP_DIR="/var/backups/mediatek86"
DB_HOST="127.0.0.1"
DB_PORT="3306"
KEEP_DAYS=30          # Sauvegardes conservées (jours) ; 0 = conservation infinie
MYSQLDUMP_BIN="mysqldump"

# ---------------------------------------------------------------------------
# Chargement des credentials
# ---------------------------------------------------------------------------
if [[ ! -f "${CREDENTIALS_FILE}" ]]; then
    echo "ERREUR : fichier de credentials introuvable : ${CREDENTIALS_FILE}" >&2
    echo "  Exécutez d'abord server_setup.sh, ou passez le chemin en argument." >&2
    exit 1
fi

# shellcheck source=/dev/null
source "${CREDENTIALS_FILE}"

if [[ -z "${DB_APP_USER:-}" || -z "${DB_APP_PWD:-}" || -z "${DB_NAME:-}" ]]; then
    echo "ERREUR : variables DB_APP_USER, DB_APP_PWD ou DB_NAME manquantes" >&2
    echo "  dans ${CREDENTIALS_FILE}." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Préparation
# ---------------------------------------------------------------------------
mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"
BACKUP_FILE="${BACKUP_DIR}/mediatek86_${TIMESTAMP}.sql.gz"

# Fichier d'options MySQL temporaire (évite le mot de passe dans la liste
# des processus et dans les logs du système)
MYSQL_OPT_FILE="$(mktemp)"
chmod 600 "${MYSQL_OPT_FILE}"
cat > "${MYSQL_OPT_FILE}" <<OPT
[mysqldump]
user=${DB_APP_USER}
password=${DB_APP_PWD}
host=${DB_HOST}
port=${DB_PORT}
OPT

# Nettoyage garanti du fichier temporaire à la sortie (succès ou erreur)
trap 'rm -f "${MYSQL_OPT_FILE}"' EXIT

# ---------------------------------------------------------------------------
# Dump + compression
# ---------------------------------------------------------------------------
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Début de la sauvegarde : ${BACKUP_FILE}"

if ! "${MYSQLDUMP_BIN}" \
        --defaults-extra-file="${MYSQL_OPT_FILE}" \
        --single-transaction \
        --routines \
        --triggers \
        --set-gtid-purged=OFF \
        "${DB_NAME}" \
        | gzip -9 > "${BACKUP_FILE}"; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERREUR : mysqldump a échoué." >&2
    rm -f "${BACKUP_FILE}"
    exit 1
fi

chmod 640 "${BACKUP_FILE}"

SIZE="$(du -sh "${BACKUP_FILE}" | cut -f1)"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Sauvegarde créée : ${BACKUP_FILE} (${SIZE})"

# ---------------------------------------------------------------------------
# Rotation : suppression des sauvegardes plus anciennes que KEEP_DAYS jours
# ---------------------------------------------------------------------------
if [[ "${KEEP_DAYS}" -gt 0 ]]; then
    DELETED="$(find "${BACKUP_DIR}" -maxdepth 1 \
        -name "mediatek86_*.sql.gz" \
        -mtime "+${KEEP_DAYS}" \
        -print -delete | wc -l | tr -d ' ')"
    if [[ "${DELETED}" -gt 0 ]]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ${DELETED} ancienne(s) sauvegarde(s) supprimée(s) (>${KEEP_DAYS} jours)."
    fi
fi

TOTAL="$(find "${BACKUP_DIR}" -maxdepth 1 -name "mediatek86_*.sql.gz" | wc -l | tr -d ' ')"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Sauvegardes conservées dans ${BACKUP_DIR} : ${TOTAL}"
