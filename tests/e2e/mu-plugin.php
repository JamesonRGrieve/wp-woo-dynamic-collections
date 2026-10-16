<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/* End-to-end environment only: registers the suite's checks as `wp wdc-e2e <scenario>`. */

const WDC_E2E_CHECKS = WP_PLUGIN_DIR . '/wp-woo-dynamic-collections/tests/e2e/checks.php';

if (defined('WP_CLI') && constant('WP_CLI') === true) {
    add_action('plugins_loaded', static function (): void {
        require WDC_E2E_CHECKS;
    }, 20);
}
