FROM php:8.3-apache

LABEL description="PHP 8.3 + Apache + MariaDB + Chromium for Joomla! extension testing"

WORKDIR /var/www/joomla

# System deps
RUN apt-get update && apt-get install -y \
    mariadb-server mariadb-client \
    curl wget git unzip vim jq \
    libicu-dev libpng-dev libjpeg-dev libwebp-dev libzip-dev libonig-dev \
    chromium chromium-driver \
    fonts-liberation fonts-dejavu \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions Joomla requires / recommends
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install pdo pdo_mysql mysqli gd zip intl opcache

# Composer + PHPUnit + PHPStan (for extension tests / static analysis)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer global require --no-interaction phpunit/phpunit phpstan/phpstan \
 && ln -s /root/.composer/vendor/bin/phpunit /usr/local/bin/phpunit \
 && ln -s /root/.composer/vendor/bin/phpstan /usr/local/bin/phpstan || true

RUN a2enmod rewrite headers

RUN mkdir -p /var/www/joomla /var/log/joomla \
 && chown -R www-data:www-data /var/www /var/log/joomla

# Apache vhost
RUN printf '%s\n' \
  '<VirtualHost *:80>' \
  '    ServerName localhost' \
  '    DocumentRoot /var/www/joomla' \
  '    <Directory /var/www/joomla>' \
  '        Options FollowSymLinks' \
  '        AllowOverride All' \
  '        Require all granted' \
  '    </Directory>' \
  '    ErrorLog /var/log/joomla/apache-error.log' \
  '    CustomLog /var/log/joomla/apache-access.log combined' \
  '</VirtualHost>' \
  > /etc/apache2/sites-available/000-default.conf

# Entrypoint: start MariaDB, create DB, download latest stable Joomla from
# GitHub releases, run the CLI installer, then start Apache in foreground.
RUN cat > /entrypoint.sh <<'EOS'
#!/bin/bash
set -e
service mariadb start
mariadb -uroot -e "CREATE DATABASE IF NOT EXISTS joomla CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
  CREATE USER IF NOT EXISTS 'joomla'@'localhost' IDENTIFIED BY 'joomla'; \
  GRANT ALL ON joomla.* TO 'joomla'@'localhost'; CREATE USER IF NOT EXISTS 'joomla'@'127.0.0.1' IDENTIFIED BY 'joomla'; GRANT ALL ON joomla.* TO 'joomla'@'127.0.0.1'; FLUSH PRIVILEGES;"

cd /var/www/joomla
if [ ! -f includes/version.php ] && [ ! -f libraries/src/Version.php ]; then
  echo "Downloading latest stable Joomla from GitHub releases..."
  URL=$(curl -s https://api.github.com/repos/joomla/joomla-cms/releases \
        | jq -r '[.[] | select(.prerelease==false)][0].assets[]
                 | select(.name | test("Full_Package.zip$")) | .browser_download_url' \
        | head -1)
  curl -sL "$URL" -o /tmp/joomla.zip
  unzip -q /tmp/joomla.zip -d /var/www/joomla
  chown -R www-data:www-data /var/www/joomla
  php installation/joomla.php install \
      --site-name "Ext Test" \
      --admin-user "Admin" --admin-username admin \
      --admin-password admin12345678 --admin-email admin@example.com \
      --db-type mysqli --db-host 127.0.0.1 \
      --db-user joomla --db-pass joomla --db-name joomla \
      --db-prefix jos_ --no-interaction || true
  chown -R www-data:www-data /var/www/joomla
fi

exec apache2-foreground
EOS
RUN chmod +x /entrypoint.sh

EXPOSE 80 4444
CMD ["/entrypoint.sh"]
