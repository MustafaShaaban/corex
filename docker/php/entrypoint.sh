#!/usr/bin/env bash
# Corex dev container entrypoint (spec 028).
# Mirrors scripts/setup-wordpress.ps1 inside the container: download WP core into the shared
# wp_core volume, configure + install it, symlink the monorepo (mounted at /repo) into
# wp-content, and activate the theme + plugins. Idempotent — safe on every container start.
set -euo pipefail

WP="/var/www/html"
REPO="/repo"
SITE_URL="${COREX_SITE_URL:-http://localhost:8080}"

echo "== Corex dev entrypoint =="

# 1. WordPress core (only on first boot — persisted in the wp_core volume).
if [ ! -f "$WP/wp-load.php" ]; then
  echo "Downloading WordPress core ..."
  wp core download --path="$WP" --skip-content --locale=en_US --allow-root
fi

# 2. wp-config.php
if [ ! -f "$WP/wp-config.php" ]; then
  wp config create --path="$WP" --allow-root \
    --dbname="${WORDPRESS_DB_NAME:-corex}" \
    --dbuser="${WORDPRESS_DB_USER:-corex}" \
    --dbpass="${WORDPRESS_DB_PASSWORD:-corex}" \
    --dbhost="${WORDPRESS_DB_HOST:-db}" \
    --dbprefix="${WORDPRESS_TABLE_PREFIX:-cx_}" \
    --locale=en_US
fi

# 3. Install WordPress (only if not already installed).
if ! wp core is-installed --path="$WP" --allow-root 2>/dev/null; then
  echo "Installing WordPress ..."
  wp core install --path="$WP" --allow-root \
    --url="$SITE_URL" --title=Corex \
    --admin_user=admin --admin_email=admin@example.com --admin_password=changeme --skip-email
fi

# 4. Symlink the monorepo into wp-content (the container analogue of the Windows junctions).
mkdir -p "$WP/wp-content/themes" "$WP/wp-content/plugins"
ln -sfn "$REPO/theme" "$WP/wp-content/themes/corex"
for dir in "$REPO"/plugins/* "$REPO"/addons/*; do
  [ -d "$dir" ] && ln -sfn "$dir" "$WP/wp-content/plugins/$(basename "$dir")"
done

# 5. Activate theme + framework plugins.
wp theme activate corex --path="$WP" --allow-root || true
wp plugin activate corex-core corex-blocks corex-config corex-forms --path="$WP" --allow-root || true

chown -R www-data:www-data "$WP/wp-content" || true
echo "== Ready: $SITE_URL =="

exec "$@"
