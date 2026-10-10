# TinyTLD
TinyTLD is a lightweight and basic Registry management system for small-scale TLDs.

It lets you manage the registered SLDs, and the associated registrar and registrant information. The registrars
can also manage their own SLDs and their associated registrant information.

The TLD zone can be exported programmatically.

The system also provides a RDAP endpoint for querying the information about the registered SLDs.

## Requirements
The application is PHP-based. It requires php 8.4 or higher and composer. It also requires a database server, such as
MariaDB, MySQL or PostgreSQL. It can use SQLite, but it is not recommended for production use. The application also requires a
web server, such as Apache or Nginx.

## Installation with Docker

The easiest way to run TinyTLD is using Docker and Docker Compose with MariaDB:

1. Clone the repository and copy the example environment file:
   ```bash
   cp .env.example .env
   ```
   (Optionally customize variables such as `APP_PORT`, `DB_PASSWORD`, etc.)

2. Start the containers:
   ```bash
   docker compose up -d
   ```

3. Run the initial application setup:
   ```bash
   docker compose exec app php artisan app:install
   ```

The application will be available at `http://localhost:8000` (or the configured `APP_PORT`).

## Manual Installation

To install and run TinyTLD directly on your server or local environment:

1. Clone the repository (or download the latest release) and enter the directory:
   ```bash
   git clone https://github.com/lbreda/tinytld.git
   cd tinytld
   ```

2. Install PHP dependencies:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

3. Install frontend dependencies and build assets:
   ```bash
   npm install && npm run build
   ```

4. Set up the environment configuration:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   (Edit `.env` to configure your database connection `DB_*` and application URL `APP_URL`)

5. Run the initial application setup (migrations, seeds, and admin account):
   ```bash
   php artisan app:install
   ```

6. Set file permissions and configure the web server:
   Ensure `storage` and `bootstrap/cache` are writable by the web server user (e.g. `www-data`):
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```
   Point your web server (Apache/Nginx) `DocumentRoot` to the `public/` directory with URL rewriting enabled.
