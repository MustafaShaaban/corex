# Corex — multi-stage image (spec 028).
#   target: dev   → php-fpm + Composer + WP-CLI + Node, the monorepo symlinked in (used by docker-compose.yml)
#   target: prod  → a lean php-fpm runtime with built assets and vendored deps baked in, no dev tooling
#
# Build the production image:  docker build --target prod -t corex:prod .

# ---- base: php-fpm + the PHP extensions WordPress needs ------------------------------------
FROM php:8.3-fpm-alpine AS base
RUN apk add --no-cache mariadb-client bash less \
 && docker-php-ext-install mysqli pdo_mysql opcache gd \
 && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
 && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp
WORKDIR /var/www/html

# ---- build: install PHP + JS deps and compile the block assets ----------------------------
FROM node:20-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --optimize-autoloader

# ---- dev: the development target the compose stack builds ----------------------------------
FROM base AS dev
RUN apk add --no-cache nodejs npm
COPY docker/php/entrypoint.sh /usr/local/bin/corex-entrypoint
RUN chmod +x /usr/local/bin/corex-entrypoint
ENTRYPOINT ["corex-entrypoint"]
CMD ["php-fpm"]

# ---- prod: lean runtime, source + built assets + vendored deps baked in --------------------
FROM base AS prod
# WordPress core
RUN wp core download --path=/var/www/html --skip-content --locale=en_US --allow-root
# Corex source (theme + plugins + addons) into wp-content
COPY theme/   /var/www/html/wp-content/themes/corex/
COPY plugins/ /var/www/html/wp-content/plugins/
COPY addons/  /var/www/html/wp-content/plugins/
# vendored PHP deps + compiled block assets from the build stages
COPY --from=vendor /build/vendor/ /var/www/html/vendor/
COPY --from=assets /build/plugins/ /var/www/html/wp-content/plugins/
COPY --from=assets /build/addons/  /var/www/html/wp-content/plugins/
RUN chown -R www-data:www-data /var/www/html
USER www-data
CMD ["php-fpm"]
