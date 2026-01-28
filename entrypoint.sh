#!/bin/bash

# Fix permissions first (in case of volume mount issues)
chown -R www-data:www-data /var/www/html

# Run Migrations
echo "Running Database Migrations..."
php /var/www/html/api/migration_theme.php

# Start Apache
echo "Starting Apache..."
exec apache2-foreground
