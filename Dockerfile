# =============================================================================
# Image de production pour l'application Symfony "Ina Zaoui".
#
# Base : PHP 8.4 + Apache (mod_php). Un seul conteneur sert le site.
# Le build installe les dépendances de PROD uniquement et optimise l'autoloader.
# =============================================================================
FROM php:8.4-apache

# --- 1) Dépendances système + extensions PHP nécessaires à l'app ---
#   - libicu-dev  : pour l'extension intl (symfony/intl)
#   - libzip-dev  : pour l'extension zip (utilisée par Composer)
#   - unzip, git  : utilitaires d'installation Composer
#   - pdo_mysql   : connexion à la base MySQL de production
#   - opcache     : cache d'opcodes PHP (indispensable en prod pour la perf)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --- 2) Configuration PHP de production ---
# On part du php.ini "production" fourni par l'image, puis on active OPcache.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

# --- 3) Configuration Apache pour Symfony ---
# La racine web est public/, et toutes les URL inconnues sont réécrites vers
# index.php (FallbackResource) => pas besoin de .htaccess.
RUN a2enmod rewrite
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# --- 4) Composer (copié depuis l'image officielle) ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# L'environnement d'exécution par défaut est la PRODUCTION.
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# --- 5) Installation des dépendances (couche mise en cache tant que les
#         fichiers Composer ne changent pas) ---
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# --- 6) Copie du code applicatif puis finalisation de l'autoloader ---
COPY . .
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

# --- 7) Entrée : on prépare le cache de prod au démarrage (quand les vraies
#         variables d'environnement sont présentes) puis on lance Apache ---
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]


