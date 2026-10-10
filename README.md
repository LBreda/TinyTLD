# TinyTLD
TinyTLD is a lightweight and basic Registry management system for small-scale TLDs.

It lets you manage the registered SLDs, and the associated registrar and registrant information. The registrars
can also manage their own SLDs and their associated registrant information.

The TLD zone can be exported programmatically.

The system also provides a RDAP endpoint for querying the information about the registered SLDs.

## Requirements
The application is PHP-based. It requires php 8.4 or higher and composer. It also requires a database server, such as
MySQL or PostgreSQL. It can use SQLite, but it is not recommended for production use. The application also requires a
web server, such as Apache or Nginx.

## Installation
You can download the last release from the [releases page](https://github.com/lbreda/tinytld/releases) and upload it on
your web server. The application requires a database to be configured. You can use the included .env.example file as a
starting point.

You need to run `composer install` to install the dependencies.

## First setup
After running composer, you need to run `php artisan app:install` to configure the database and the application.
