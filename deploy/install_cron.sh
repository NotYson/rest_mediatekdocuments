#!/usr/bin/env bash
# =============================================================================
# install_cron.sh — Installe la tâche cron de sauvegarde quotidienne
#
# Usage : sudo bash install_cron.sh
#
# Installe un fichier dans /etc/cron.d/ qui exécute backup_db.sh
# tous les jours à 02h00 en tant que root.
# Idempotent : sans effet si la cron est déjà installée.
# =============================================================================
set -euo pipefail

CRON_FILE="/etc/cron.d/mediatek86_backup"
CRON_LOG="/var/log/mediatek86_backup.log"
CRON_HOUR="2"
CRON_MINUTE="0"

# ---------------------------------------------------------------------------
# Vérifications
# ---------------------------------------------------------------------------
if [[ $EUID -ne 0 ]]; then
    echo "ERREUR : ce script doit être exécuté en root (sudo)." >&2
    exit 1
fi

# Résolution du chemin absolu de backup_db.sh (même dossier que ce script)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_SCRIPT="${SCRIPT_DIR}/backup_db.sh"

if [[ ! -f "${BACKUP_SCRIPT}" ]]; then
    echo "ERREUR : backup_db.sh introuvable dans ${SCRIPT_DIR}." >&2
    exit 1
fi

chmod +x "${BACKUP_SCRIPT}"

# ---------------------------------------------------------------------------
# Idempotence : ne réinstalle pas si déjà en place
# ---------------------------------------------------------------------------
if [[ -f "${CRON_FILE}" ]]; then
    echo "La cron est déjà installée dans ${CRON_FILE}."
    echo "Contenu actuel :"
    cat "${CRON_FILE}"
    echo ""
    echo "Pour modifier le planning, éditez directement ${CRON_FILE}."
    exit 0
fi

# ---------------------------------------------------------------------------
# Création du fichier de cron
# ---------------------------------------------------------------------------
cat > "${CRON_FILE}" <<CRON
# Sauvegarde quotidienne de la base MySQL mediatek86
# Généré par install_cron.sh — modifiez ce fichier pour changer le planning
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

${CRON_MINUTE} ${CRON_HOUR} * * *  root  bash ${BACKUP_SCRIPT} >> ${CRON_LOG} 2>&1
CRON

chmod 644 "${CRON_FILE}"

# ---------------------------------------------------------------------------
# Fichier de log
# ---------------------------------------------------------------------------
touch "${CRON_LOG}"
chmod 640 "${CRON_LOG}"

# ---------------------------------------------------------------------------
# Résumé
# ---------------------------------------------------------------------------
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║              CRON INSTALLÉE AVEC SUCCÈS                     ║"
echo "╠══════════════════════════════════════════════════════════════╣"
printf "║  Planning    : tous les jours à %02dh%02d                       ║\n" "${CRON_HOUR}" "${CRON_MINUTE}"
printf "║  Script      : %-44s ║\n" "${BACKUP_SCRIPT}"
printf "║  Logs        : %-44s ║\n" "${CRON_LOG}"
printf "║  Cron file   : %-44s ║\n" "${CRON_FILE}"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "Test immédiat : sudo bash ${BACKUP_SCRIPT}"
echo "Suivi des logs : tail -f ${CRON_LOG}"
