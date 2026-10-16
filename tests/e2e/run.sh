#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# End-to-end suite in a real WordPress + WooCommerce + MariaDB: rule-based categories fill on save, keep
# hand-picked and not-evaluable categories, follow a price change, and a reconcile over unchanged data
# writes nothing. Everything it creates is removed on exit.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PREFIX="wdc-e2e-$$"
NETWORK="${PREFIX}-net"
DB="${PREFIX}-db"
WP_VOLUME="${PREFIX}-wp"
STATE_DIR="$(mktemp -d)"
DB_IMAGE="docker.io/library/mariadb:11.4"
WP_IMAGE="docker.io/library/wordpress:cli-php8.3"
PLUGIN_SLUG="wp-woo-dynamic-collections"
PLUGIN_PATH="/var/www/html/wp-content/plugins/$PLUGIN_SLUG"
DB_READY_ATTEMPTS=120
DB_READY_INTERVAL=1
WP_CLI_MEMORY=1G

# Optional: keep the image/container store somewhere else (e.g. local disk when $HOME is on NFS).
if [ -n "${E2E_PODMAN_ROOT:-}" ]; then
    mkdir -p "$E2E_PODMAN_ROOT"
    podman() { command podman --root "$E2E_PODMAN_ROOT" "$@"; }
fi

cleanup() {
    podman rm -f "$DB" >/dev/null 2>&1 || true
    podman volume rm -f "$WP_VOLUME" >/dev/null 2>&1 || true
    podman network rm -f "$NETWORK" >/dev/null 2>&1 || true
    rm -rf "$STATE_DIR"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

# WP-CLI as www-data; the plugin is mounted only once WordPress has created wp-content itself.
PLUGIN_MOUNT=()
wp() {
    podman run --rm -i --network "$NETWORK" --user 33:33 \
        -v "$WP_VOLUME:/var/www/html" \
        "${PLUGIN_MOUNT[@]}" \
        -v "$STATE_DIR:/state" \
        "$WP_IMAGE" php -d memory_limit="$WP_CLI_MEMORY" /usr/local/bin/wp "$@"
}

# MariaDB's image first runs a socket-only server to initialise, so readiness is a TCP ping: it only
# answers once the real server is listening for WordPress.
wait_for_db() {
    for _ in $(seq "$DB_READY_ATTEMPTS"); do
        podman exec "$DB" mariadb-admin ping -h 127.0.0.1 --protocol=tcp -uwp -pwp --silent >/dev/null 2>&1 && return 0
        sleep "$DB_READY_INTERVAL"
    done
    echo "MariaDB never became ready" >&2
    return 1
}

podman network create "$NETWORK" >/dev/null
podman volume create "$WP_VOLUME" >/dev/null
chmod 0777 "$STATE_DIR"
podman run -d --name "$DB" --network "$NETWORK" --network-alias db \
    -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp -e MARIADB_RANDOM_ROOT_PASSWORD=1 \
    "$DB_IMAGE" >/dev/null
podman run --rm --user 0:0 -v "$WP_VOLUME:/var/www/html" "$WP_IMAGE" chown -R 33:33 /var/www/html
wp core download --quiet
PLUGIN_MOUNT=(-v "$ROOT:$PLUGIN_PATH:ro")
wp config create --dbname=wp --dbuser=wp --dbpass=wp --dbhost=db --skip-check
wait_for_db
wp core install --url=http://localhost --title=E2E --admin_user=e2e --admin_email=e2e@example.com --skip-email --quiet
wp plugin install woocommerce --activate --quiet
podman run --rm --user 33:33 -v "$WP_VOLUME:/var/www/html" -v "$ROOT:/src:ro" "$WP_IMAGE" \
    sh -c 'mkdir -p /var/www/html/wp-content/mu-plugins && cp /src/tests/e2e/mu-plugin.php /var/www/html/wp-content/mu-plugins/wdc-e2e.php'
wp plugin activate "$PLUGIN_SLUG"

echo "== Rule-based categories fill on save"
wp wdc-e2e setup
wp wdc-e2e initial

echo "== A price change moves a product on save"
wp wdc-e2e change

echo "== Reconcile over unchanged data writes nothing"
wp wdc-e2e checksum /state/before
wp dynamic-collections reconcile
wp wdc-e2e unchanged /state/before
echo "E2E PASSED"
