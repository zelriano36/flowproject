#!/usr/bin/env bash
# Installation de FlowProject sur Debian/Ubuntu avec Apache + PHP + MariaDB
# Usage : sudo ./install.sh [nom-de-domaine]        (défaut : localhost)
# Mode non interactif : sudo ADMIN_NAME=.. ADMIN_EMAIL=.. ADMIN_PASS=.. ./install.sh
set -euo pipefail

APP_NAME="flowproject"
APP_DIR="/var/www/${APP_NAME}"
DB_NAME="flowproject"
DB_USER="flowproject"
CONFIG_FILE="config/config.php"
SERVER_NAME="${1:-localhost}"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEMA="${SRC_DIR}/sql/schema.sql"

# ---------- Vérifications ----------
if [ "${EUID}" -ne 0 ]; then
    echo "Lancez ce script avec sudo." >&2
    exit 1
fi
if ! command -v apt-get >/dev/null 2>&1; then
    echo "Ce script supporte uniquement Debian/Ubuntu (apt)." >&2
    exit 1
fi
if [ ! -f "${SCHEMA}" ]; then
    echo "Fichier ${SCHEMA} introuvable (placez le schéma dans sql/schema.sql)." >&2
    exit 1
fi

# ---------- Saisie du compte administrateur ----------
# Échappe antislash et apostrophe pour une chaîne SQL entre apostrophes
sql_escape() { printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"; }

ask_admin() {
    local pass2
    ADMIN_NAME="${ADMIN_NAME:-}"
    ADMIN_EMAIL="${ADMIN_EMAIL:-}"
    ADMIN_PASS="${ADMIN_PASS:-}"

    if [ -t 0 ]; then
        echo "=== Compte administrateur ==="
        while [ -z "${ADMIN_NAME}" ]; do
            read -rp "Nom : " ADMIN_NAME
        done
        while true; do
            if [ -z "${ADMIN_EMAIL}" ]; then
                read -rp "Email : " ADMIN_EMAIL
            fi
            if [[ "${ADMIN_EMAIL}" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; then
                break
            fi
            echo "Email invalide."
            ADMIN_EMAIL=""
        done
        while true; do
            if [ -z "${ADMIN_PASS}" ]; then
                read -rsp "Mot de passe (8 caractères minimum) : " ADMIN_PASS; echo
                read -rsp "Confirmez le mot de passe : " pass2; echo
                if [ "${ADMIN_PASS}" != "${pass2}" ]; then
                    echo "Les mots de passe ne correspondent pas."
                    ADMIN_PASS=""
                    continue
                fi
            fi
            if [ "${#ADMIN_PASS}" -ge 8 ]; then
                break
            fi
            echo "Mot de passe trop court."
            ADMIN_PASS=""
        done
    else
        ADMIN_NAME="${ADMIN_NAME:-Admin}"
        ADMIN_EMAIL="${ADMIN_EMAIL:-admin@localhost.local}"
        if [ -z "${ADMIN_PASS}" ]; then
            ADMIN_PASS="$(openssl rand -base64 18 | tr -d '/+=')"
            GENERATED_PASS=1
        fi
    fi
}

# Les questions sont posées avant toute installation, si la base n'existe pas encore
FIRST_INSTALL=1
if command -v mysql >/dev/null 2>&1 && mysql -e "USE \`${DB_NAME}\`" 2>/dev/null; then
    FIRST_INSTALL=0
fi
GENERATED_PASS=0
if [ "${FIRST_INSTALL}" -eq 1 ]; then
    ask_admin
fi

# ---------- Paquets ----------
echo "==> Installation des paquets"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y apache2 mariadb-server php php-cli libapache2-mod-php php-mysql php-mbstring php-gd rsync openssl
systemctl enable --now mariadb apache2

if ! mysql -e "SELECT 1" >/dev/null 2>&1; then
    echo "Impossible de se connecter à MariaDB en root (socket)." >&2
    echo "Vérifiez l'accès root ou lancez : sudo mysql_secure_installation" >&2
    exit 1
fi

# ---------- Réglages PHP (envoi d'images) ----------
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
PHP_CONF_DIR="/etc/php/${PHP_VER}/apache2/conf.d"
if [ -d "${PHP_CONF_DIR}" ]; then
    echo "==> Réglages PHP (${PHP_CONF_DIR}/99-${APP_NAME}.ini)"
    cat > "${PHP_CONF_DIR}/99-${APP_NAME}.ini" <<INI
upload_max_filesize = 16M
post_max_size = 48M
memory_limit = 256M
INI
fi

# ---------- Fichiers ----------
echo "==> Copie des fichiers vers ${APP_DIR}"
mkdir -p "${APP_DIR}"
rsync -a \
    --exclude '.git' --exclude '/install.sh' --exclude '/sql/' \
    --exclude "/${CONFIG_FILE}" --exclude '/storage/' \
    "${SRC_DIR}/" "${APP_DIR}/"
mkdir -p "${APP_DIR}/$(dirname "${CONFIG_FILE}")"

# ---------- Base de données ----------
if mysql -e "USE \`${DB_NAME}\`" 2>/dev/null; then
    echo "==> Base '${DB_NAME}' déjà présente : création et import ignorés."
else
    echo "==> Création de la base et de l'utilisateur SQL"
    DB_PASS="$(openssl rand -base64 24 | tr -d '/+=')"
    mysql <<SQL
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

    echo "==> Import du schéma (${SCHEMA})"
    mysql "${DB_NAME}" < "${SCHEMA}"

    echo "==> Écriture de ${CONFIG_FILE}"
    cat > "${APP_DIR}/${CONFIG_FILE}" <<PHP
<?php
// Fichier généré par install.sh
return [
    'db' => [
        'host' => 'localhost',
        'name' => '${DB_NAME}',
        'user' => '${DB_USER}',
        'pass' => '${DB_PASS}',
    ],
];
PHP

    echo "==> Création du compte administrateur"
    ADMIN_HASH="$(ADMIN_PASS="${ADMIN_PASS}" php -r 'echo password_hash(getenv("ADMIN_PASS"), PASSWORD_DEFAULT);')"
    mysql "${DB_NAME}" <<SQL
INSERT INTO \`user\` (name, email, password, role_id)
VALUES ('$(sql_escape "${ADMIN_NAME}")', '$(sql_escape "${ADMIN_EMAIL}")', '${ADMIN_HASH}',
        (SELECT id FROM \`role\` WHERE name = 'admin' LIMIT 1));
SQL
fi

# ---------- Droits ----------
echo "==> Droits d'accès"
chown -R root:www-data "${APP_DIR}"
find "${APP_DIR}" -type d -exec chmod 750 {} \;
find "${APP_DIR}" -type f -exec chmod 640 {} \;

echo "==> Dossiers de stockage (avatars, images de tâches, logo du site)"
mkdir -p "${APP_DIR}/storage/avatars" "${APP_DIR}/storage/task_images" "${APP_DIR}/storage/branding"
chown -R www-data:www-data "${APP_DIR}/storage"
chmod -R 750 "${APP_DIR}/storage"

# ---------- Apache ----------
echo "==> Configuration d'Apache"
cat > "/etc/apache2/sites-available/${APP_NAME}.conf" <<CONF
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${APP_NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${APP_NAME}_access.log combined
</VirtualHost>
CONF

a2enmod rewrite headers >/dev/null
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite "${APP_NAME}" >/dev/null
apache2ctl configtest
systemctl restart apache2

# ---------- Fin ----------
echo
echo "Installation terminée : http://${SERVER_NAME}/"
if [ "${FIRST_INSTALL}" -eq 1 ]; then
    echo "Compte administrateur : ${ADMIN_EMAIL}"
    if [ "${GENERATED_PASS}" -eq 1 ]; then
        echo "Mot de passe généré   : ${ADMIN_PASS}  (notez-le, il ne sera plus affiché)"
    fi
fi