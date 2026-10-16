<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

use WC_Product;

/** Re-evaluates a product whenever WooCommerce saves it, one of its variations, or its stock. */
final class ProductEvents
{
    private const PRODUCT_SAVES = ['woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation'];
    private const STOCK_CHANGES = ['woocommerce_product_set_stock', 'woocommerce_variation_set_stock'];

    public function __construct(private readonly Membership $membership) {}

    public function register(): void
    {
        foreach (self::PRODUCT_SAVES as $hook) {
            add_action($hook, $this->saved(...));
        }
        foreach (self::STOCK_CHANGES as $hook) {
            add_action($hook, $this->stockChanged(...));
        }
    }

    public function saved(int $productId): void
    {
        $this->membership->apply($productId);
    }

    public function stockChanged(WC_Product $product): void
    {
        $this->membership->apply($product->get_id());
    }
}
