<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * Plugin Name: Dynamic Collections for WooCommerce
 * Description: Shopify-style rule-based (smart) product categories: products join and leave a category automatically when they match its conditions.
 * Version: 0.0.1
 * Requires at least: 6.6
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * WC requires at least: 9.6
 * Author: Jameson Grieve
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: wp-woo-dynamic-collections
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

WooDynamicCollections\Wp\Plugin::boot(__FILE__);
