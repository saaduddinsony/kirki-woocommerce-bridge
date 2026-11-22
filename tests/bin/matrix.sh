#!/usr/bin/env bash
# Run the checks for one plugin combination on a local test site.
#
#   tests/bin/matrix.sh <site-dir> <base-url> <label>
#
# <site-dir> contains wp-cli.phar and site/ (as created by bin/setup-test-site.sh).
# Optional: EXTRA_PATHS (more URLs to probe), PW_ARGS (Playwright args).
# Prints one summary line per check and exits non-zero if any check failed.
set -uo pipefail
DIR="$1"; URL="$2"; LABEL="$3"
PLUGIN_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
wp() { php -d memory_limit=1024M "$DIR/wp-cli.phar" --path="$DIR/site" "$@" 2>/dev/null; }
fail=0
echo "== $LABEL =="
echo "active: $(wp plugin list --status=active --field=name | grep -v sqlite | tr '\n' ' ')"
: > "$DIR/site/wp-content/debug.log"

if wp eval-file "$PLUGIN_DIR/tests/bin/smoke.php" > "$DIR/smoke.log"; then echo "smoke: ok ($(grep -o 'abilities [0-9]*' "$DIR/smoke.log"))"; else echo "smoke: FAIL"; fail=1; fi

if KIRKI_WOO_WP_LOAD="$DIR/site/wp-load.php" php -d memory_limit=1024M "$PLUGIN_DIR/vendor/bin/phpunit" -c "$PLUGIN_DIR/phpunit.xml.dist" > "$DIR/phpunit.log" 2>&1; then echo "phpunit: $(tail -1 "$DIR/phpunit.log")"; else echo "phpunit: FAIL $(tail -1 "$DIR/phpunit.log")"; fail=1; fi

for path in / /shop/ /product/classic-tee/ /product/logo-hoodie/ /product-category/clothing/ /cart/ /my-account/ "/?s=tee&post_type=product" ${EXTRA_PATHS:-}; do
	code=$(curl -s -o /dev/null -w '%{http_code}' "$URL$path")
	case "$code" in 200|302) ;; *) echo "http $path: $code"; fail=1 ;; esac
done
echo "http: checked"

if (cd "$PLUGIN_DIR" && WP_BASE_URL="$URL" CHROME_PATH="${CHROME_PATH:-}" timeout 900 npx playwright test -c playwright.config.js ${PW_ARGS:-} > "$DIR/pw.log" 2>&1); then echo "e2e: $(grep -Eo '[0-9]+ passed' "$DIR/pw.log" | tail -1)"; else echo "e2e: FAIL $(grep -E 'passed|failed' "$DIR/pw.log" | tr '\n' ' ')"; fail=1; fi
cp "$DIR/pw.log" "$DIR/pw-$(echo "$LABEL" | cut -c1).log"

problems=$(grep -E "PHP (Fatal|Warning|Parse)" "$DIR/site/wp-content/debug.log" | grep -c . || true)
echo "php errors/warnings during run: $problems"
grep -E "PHP (Fatal|Warning|Parse)" "$DIR/site/wp-content/debug.log" | cut -c1-200 | sort | uniq | head -5
[ "$problems" = "0" ] || fail=1
exit $fail
