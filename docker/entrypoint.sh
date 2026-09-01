#!/bin/sh
# =============================================================================
# Entrypoint du conteneur applicatif.
# Exécuté à chaque démarrage, quand les vraies variables d'environnement
# (APP_SECRET, DATABASE_URL...) sont disponibles.
# =============================================================================
set -e

# S'assure que les dossiers d'écriture existent et appartiennent à Apache.
mkdir -p var/cache var/log public/uploads
chown -R www-data:www-data var public/uploads

# Prépare le cache de production (compilation du conteneur, warmup).
php bin/console cache:clear --no-interaction
php bin/console cache:warmup --no-interaction

# Lance la commande passée en CMD (par défaut : apache2-foreground).
exec "$@"

