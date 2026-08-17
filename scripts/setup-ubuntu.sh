#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v apt-get >/dev/null 2>&1; then
    echo "This setup script is for Ubuntu/Debian systems with apt-get."
    exit 1
fi

echo "Preparing Ubuntu package sources..."
sudo apt-get update
sudo apt-get install -y \
    software-properties-common \
    ca-certificates \
    lsb-release \
    apt-transport-https

if ! apt-cache show php8.3-cli >/dev/null 2>&1; then
    echo "Adding PHP 8.3 package source..."
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt-get update
fi

echo "Installing Ubuntu packages..."
sudo apt-get install -y \
    git \
    unzip \
    curl \
    mysql-server \
    composer \
    php8.3-cli \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-mysql \
    php8.3-zip \
    php8.3-bcmath

sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.3 83
sudo update-alternatives --set php /usr/bin/php8.3

PHP_VERSION_ID="$(php -r 'echo PHP_VERSION_ID;')"
if [ "$PHP_VERSION_ID" -lt 80300 ]; then
    echo "PHP 8.3 or newer is required. Current version: $(php -r 'echo PHP_VERSION;')"
    echo "Use Ubuntu 24.04+ or install PHP 8.3+, then run this script again."
    exit 1
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

DB_NAME="finbank"
DB_USER="finbank_lab"
DB_PASS="$(php -r 'echo bin2hex(random_bytes(16));')"

echo "Starting MySQL..."
sudo systemctl enable --now mysql

echo "Creating lab database and user..."
sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "Writing Laravel .env database settings..."
DB_NAME_VALUE="$DB_NAME" DB_USER_VALUE="$DB_USER" DB_PASS_VALUE="$DB_PASS" php <<'PHP'
<?php

$path = '.env';
$contents = file_get_contents($path);
$updates = [
    'APP_ENV' => 'local',
    'APP_DEBUG' => 'false',
    'APP_URL' => 'http://127.0.0.1:8000',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => getenv('DB_NAME_VALUE'),
    'DB_USERNAME' => getenv('DB_USER_VALUE'),
    'DB_PASSWORD' => getenv('DB_PASS_VALUE'),
];

foreach ($updates as $key => $value) {
    $line = $key.'='.$value;

    if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
        $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
        continue;
    }

    $contents .= PHP_EOL.$line;
}

file_put_contents($path, $contents);
PHP

echo "Installing Composer dependencies..."
composer install

echo "Generating app key and preparing Laravel..."
php artisan key:generate --force
php artisan migrate --seed --force
php artisan optimize:clear

echo "Running test suite..."
php artisan test

cat <<'NEXT'

Ubuntu setup complete.

Start the FinBank lab with:
php artisan serve --host=0.0.0.0 --port=8000

From Windows, browse to:
http://<ubuntu-vm-ip>:8000

Find the Ubuntu VM IP with:
ip addr

NEXT
