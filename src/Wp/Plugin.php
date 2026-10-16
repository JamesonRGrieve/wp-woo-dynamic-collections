<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

use WC_Product;
use WooDynamicCollections\Rules\Evaluator;
use WP_CLI;

/** Wires the plugin into WordPress once WooCommerce is loaded; it does nothing without WooCommerce. */
final class Plugin
{
    public const VERSION = '0.0.1';
    public const CLI_COMMAND = 'dynamic-collections';

    public static function boot(string $pluginFile): void
    {
        register_deactivation_hook($pluginFile, Reconciler::unscheduleAll(...));
        add_action('before_woocommerce_init', static function () use ($pluginFile): void {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', $pluginFile, true);
            }
        });
        add_action('plugins_loaded', static fn() => self::start($pluginFile));
    }

    public static function start(string $pluginFile): void
    {
        if (!class_exists(WC_Product::class)) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>' . esc_html__('Dynamic collections needs WooCommerce to be active.', 'wp-woo-dynamic-collections') . '</p></div>';
            });

            return;
        }
        $store = new RuleStore();
        $membership = new Membership($store, new FactsProvider(), new Evaluator());
        $reconciler = new Reconciler($membership);
        $reconciler->register();
        (new ProductEvents($membership))->register();
        if (is_admin()) {
            (new CategoryRulesField($store, $reconciler, $pluginFile))->register();
        }
        if (defined('WP_CLI') && constant('WP_CLI') === true) {
            WP_CLI::add_command(self::CLI_COMMAND, new Cli($reconciler));
        }
    }
}
