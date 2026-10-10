#!/bin/bash
set -e

# Setup storage directory structure and permissions
mkdir -p /var/www/html/storage/app/public \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

# Remove stale package / service discovery caches if present
rm -f /var/www/html/bootstrap/cache/packages.php /var/www/html/bootstrap/cache/services.php /var/www/html/bootstrap/cache/config.php /var/www/html/bootstrap/cache/routes-v7.php

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Ensure .env exists
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.example ]; then
        echo "Creating .env from .env.example..."
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Ensure composer dependencies are installed (e.g. if mounted volume without vendor)
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generate APP_KEY if not already set in .env and not provided via environment
if [ -z "$APP_KEY" ]; then
    if ! grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
        echo "Generating application encryption key..."
        php artisan key:generate --force
    fi
fi

# Ensure frontend build exists (e.g. if mounted volume without build)
if [ ! -d /var/www/html/public/build ]; then
    echo "Building frontend assets..."
    if [ ! -d /var/www/html/node_modules ]; then
        npm ci || npm install
    fi
    npm run build
fi

# Wait for MariaDB / MySQL if DB_HOST is configured
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "mariadb" -o "$DB_CONNECTION" = "mysql" ]; then
    echo "Waiting for database connection ($DB_HOST:${DB_PORT:-3306})..."
    php -r '
        $host = getenv("DB_HOST");
        $port = getenv("DB_PORT") ?: "3306";
        $db = getenv("DB_DATABASE") ?: "tinytld";
        $user = getenv("DB_USERNAME") ?: "tinytld";
        $pass = getenv("DB_PASSWORD") ?: "";
        $maxTries = 30;
        $connected = false;
        for ($i = 1; $i <= $maxTries; $i++) {
            try {
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, [
                    PDO::ATTR_TIMEOUT => 2,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $connected = true;
                break;
            } catch (Exception $e) {
                sleep(1);
            }
        }
        if (!$connected) {
            fwrite(STDERR, "Could not connect to database at $host:$port\n");
            exit(1);
        }
    '
    echo "Database is ready!"
fi

# Execute passed command or start apache
if [ "$#" -gt 0 ]; then
    exec "$@"
else
    exec apache2-foreground
fi
