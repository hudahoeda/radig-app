# syntax=docker/dockerfile:1.7
FROM php:8.2-apache

# Install system dependencies and PHP extensions needed by the app
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        mysqli \
        pdo_mysql \
        gd \
        zip \
    && a2enmod rewrite

# Copy Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# PHP configuration overrides (timezone, limits, etc.)
COPY docker/php/conf.d/ /usr/local/etc/php/conf.d/

# Bring in dependencies first to leverage Docker layer caching and avoid network installs in CI
COPY composer.json composer.lock ./
COPY vendor ./vendor
RUN if [ -d vendor ]; then \
      composer dump-autoload --optimize --no-dev; \
    else \
      composer install --no-dev --prefer-dist --optimize-autoloader; \
    fi

# Copy application source
COPY . .

# Ensure Apache can read/write required paths
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
