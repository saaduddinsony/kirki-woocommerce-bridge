#!/usr/bin/env bash
# Build a disposable local WordPress + WooCommerce + Kirki store for testing
# this plugin. Uses SQLite (no MySQL needed) and PHP's built-in server.
#
#   bin/setup-test-site.sh [target-dir]
#
# Environment:
#   WP_PORT          Port for `php -S` (default 8895).
#   WP_ADMIN_USER    Admin login (default admin).
#   WP_ADMIN_PASS    Admin password (default admin-local-test; local only!).
#   WP_VERSION       WordPress version (default latest).
#   EXTRA_PLUGINS    Space-separated plugin zip paths or URLs to install too (e.g. Kirki eCommerce, Kirki Pro).
#   START_SERVER     1 to start the server in the background (default 1).
#
# Never point this at a real site: it installs a brand-new WordPress.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${1:-$PLUGIN_DIR/../kirki-woo-site}"
PORT="${WP_PORT:-8895}"
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASS="${WP_ADMIN_PASS:-admin-local-test}"
URL="http://127.0.0.1:${PORT}"

mkdir -p "$TARGET"
TARGET="$(cd "$TARGET" && pwd)"
SITE="$TARGET/site"

if [ ! -f "$TARGET/wp-cli.phar" ]; then
	curl -fsSL -o "$TARGET/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
wp() { php -d memory_limit=1024M "$TARGET/wp-cli.phar" --path="$SITE" "$@"; }

if [ ! -f "$SITE/wp-load.php" ]; then
	wp core download --version="${WP_VERSION:-latest}" --skip-content --force
fi
mkdir -p "$SITE/wp-content/plugins" "$SITE/wp-content/themes" "$SITE/wp-content/uploads"

# Download from WordPress.org with curl (works where WordPress' HTTP API is restricted).
install_org() { # $1 = plugin|theme, $2 = slug
	if [ ! -d "$SITE/wp-content/$1s/$2" ]; then
		curl -fsSL -o "$TARGET/$2.zip" "https://downloads.wordpress.org/$1/$2.latest-stable.zip" ||
			curl -fsSL -o "$TARGET/$2.zip" "https://downloads.wordpress.org/$1/$2.zip"
		unzip -q -o "$TARGET/$2.zip" -d "$SITE/wp-content/$1s/"
		rm -f "$TARGET/$2.zip"
	fi
}

# SQLite drop-in.
install_org plugin sqlite-database-integration
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/sqlite-database-integration#" \
	-e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
	"$SITE/wp-content/plugins/sqlite-database-integration/db.copy" > "$SITE/wp-content/db.php"

if [ ! -f "$SITE/wp-config.php" ]; then
	wp config create --dbname=wp --dbuser=x --dbpass=x --dbhost=localhost --skip-check --extra-php <<'PHP'
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DISABLE_WP_CRON', true );
PHP
fi

if ! wp core is-installed 2>/dev/null; then
	wp core install --url="$URL" --title="Kirki Woo Test" --admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASS" --admin_email=admin@example.com --skip-email
fi
wp option update siteurl "$URL" >/dev/null
wp option update home "$URL" >/dev/null
wp rewrite structure '/%postname%/' >/dev/null

install_org theme twentytwentyfive
wp theme activate twentytwentyfive >/dev/null
install_org plugin woocommerce
install_org plugin kirki
wp plugin activate woocommerce kirki
for zip in ${EXTRA_PLUGINS:-}; do
	wp plugin install "$zip" --activate --force
done

ln -sfn "$PLUGIN_DIR" "$SITE/wp-content/plugins/kirki-woocommerce-bridge"
wp plugin activate kirki-woocommerce-bridge

# Sample catalogue + starter designs (only once).
if [ -z "$(wp option get kwt_seed_ids 2>/dev/null || true)" ]; then
	wp eval 'WC_Install::create_pages();'
	wp eval-file "$PLUGIN_DIR/tests/bin/seed.php"
	wp eval-file "$PLUGIN_DIR/tests/bin/designs.php"
fi
wp rewrite flush >/dev/null

# Start the debug log fresh: entries so far come from WP-CLI installing a
# brand-new site (e.g. wp_guess_url() without HTTP_HOST), not from the tests.
: > "$SITE/wp-content/debug.log"

echo "WordPress ready in $SITE"
echo "KIRKI_WOO_WP_LOAD=$SITE/wp-load.php"

if [ "${START_SERVER:-1}" = "1" ]; then
	KWT_ROOT="$SITE" PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}" nohup php -d memory_limit=1024M -S "127.0.0.1:${PORT}" -t "$SITE" "$PLUGIN_DIR/tests/bin/router.php" > "$TARGET/server.log" 2>&1 &
	for _ in $(seq 1 30); do
		curl -fs -o /dev/null "$URL/" && break
		sleep 1
	done
	# Warm-up: sign in once and open wp-admin, like an admin would after
	# activating plugins. Some plugins only finish installing on admin_init
	# (e.g. Kirki eCommerce creates its database tables there, not on WP-CLI
	# activation; until then creating a WordPress user throws in its
	# user_register hook).
	JAR="$TARGET/warmup-cookies.txt"
	curl -s -o /dev/null -c "$JAR" "$URL/wp-login.php" || true
	curl -s -o /dev/null -b "$JAR" -c "$JAR" -d "log=$ADMIN_USER" --data-urlencode "pwd=$ADMIN_PASS" \
		-d "wp-submit=Log+In" -d "testcookie=1" -d "redirect_to=$URL/wp-admin/" "$URL/wp-login.php" || true
	curl -s -o /dev/null -L -b "$JAR" -c "$JAR" "$URL/wp-admin/" || true
	rm -f "$JAR"
	for path in / /shop/ /wp-json/; do
		curl -s -o /dev/null "$URL$path" || true
	done
	echo "Serving $URL (log: $TARGET/server.log)"
fi
