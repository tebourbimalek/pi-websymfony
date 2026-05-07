FROM php:8.2-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libicu-dev \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_mysql intl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Memory limit
ENV PHP_MEMORY_LIMIT=512M

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts

# Copy project
COPY . .

# Symfony folders
RUN mkdir -p var/cache var/log var/sessions public/assets \
    && chmod -R 777 var public/assets

# Create startup script
RUN echo '#!/bin/sh\n\
echo "Starting Symfony..."\n\
\n\
# Install importmap packages at runtime\n\
php bin/console importmap:install || true\n\
\n\
# Compile assets\n\
php bin/console asset-map:compile || true\n\
\n\
# Clear cache\n\
php -d memory_limit=512M bin/console cache:clear --env=prod || true\n\
\n\
# Warmup cache\n\
php -d memory_limit=512M bin/console cache:warmup --env=prod || true\n\
\n\
# Start server\n\
php -d memory_limit=512M -S 0.0.0.0:${PORT:-8080} -t public\n\
' > /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 8080

CMD ["/entrypoint.sh"]