FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash curl git zip unzip icu-dev oniguruma-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    python3 py3-pip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring intl gd opcache

# Install Python packages for CSV processing
RUN pip3 install --break-system-packages pandas

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Create a non-root user (optional but nice)
RUN addgroup -g 1000 app && adduser -G app -g app -s /bin/sh -D -u 1000 app
USER app

CMD ["php-fpm"]