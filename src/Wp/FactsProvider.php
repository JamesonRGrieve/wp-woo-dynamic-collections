<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WooDynamicCollections\Rules\ProductFacts;
use WooDynamicCollections\Rules\VariantFacts;

/**
 * What the rules can see of a WooCommerce product. Facts WooCommerce has no field for come from other
 * plugins through the `wdc_product_extra_facts` filter, as plain data (no dependency on this plugin):
 *
 *     ['category_id' => 'aa-2-26',
 *      'metafields' => ['custom.season' => 'Fall'],
 *      'variant_metafields' => [<variation id> => ['custom.fabric' => 'cotton']]]
 */
final class FactsProvider
{
    public const EXTRA_FACTS_FILTER = 'wdc_product_extra_facts';
    public const TYPE_TAXONOMY_FILTER = 'wdc_product_type_taxonomy';
    public const DEFAULT_TYPE_TAXONOMY = 'pa_product-type';
    private const TAG_TAXONOMY = 'product_tag';
    private const BRAND_TAXONOMY = 'product_brand';
    /** Shopify's title for the one variant of a product without options. */
    private const DEFAULT_VARIANT_TITLE = 'Default Title';

    public function forProduct(WC_Product $product): ProductFacts
    {
        $extra = apply_filters(self::EXTRA_FACTS_FILTER, [], $product);
        $extra = is_array($extra) ? $extra : [];
        $categoryId = $extra['category_id'] ?? null;
        $variantMetafields = is_array($extra['variant_metafields'] ?? null) ? $extra['variant_metafields'] : [];

        return new ProductFacts(
            title: $product->get_name(),
            tags: self::termNames($product->get_id(), self::TAG_TAXONOMY),
            type: self::termNames($product->get_id(), self::typeTaxonomy())[0] ?? null,
            vendor: self::termNames($product->get_id(), self::BRAND_TAXONOMY)[0] ?? null,
            variants: $this->variants($product, $variantMetafields),
            categoryId: is_string($categoryId) && $categoryId !== '' ? $categoryId : null,
            metafields: self::stringMap($extra['metafields'] ?? []),
        );
    }

    /**
     * @param array<array-key, mixed> $variantMetafields
     *
     * @return list<VariantFacts>
     */
    private function variants(WC_Product $product, array $variantMetafields): array
    {
        if (!$product instanceof WC_Product_Variable) {
            return [self::variant($product, self::DEFAULT_VARIANT_TITLE, self::stringMap($variantMetafields[$product->get_id()] ?? []))];
        }
        $variants = [];
        foreach ($product->get_children() as $childId) {
            $variation = wc_get_product($childId);
            if ($variation instanceof WC_Product_Variation && $variation->get_status() === 'publish') {
                $variants[] = self::variant(
                    $variation,
                    wc_get_formatted_variation($variation, true, false, false),
                    self::stringMap($variantMetafields[$childId] ?? []),
                );
            }
        }

        return $variants;
    }

    /** @param array<string, string> $metafields */
    private static function variant(WC_Product $product, string $title, array $metafields): VariantFacts
    {
        $price = (string) $product->get_price();
        $weight = (string) $product->get_weight();

        return new VariantFacts(
            title: $title,
            price: $price === '' ? null : $price,
            compareAtPrice: $product->is_on_sale() ? (string) $product->get_regular_price() : null,
            weight: $weight === '' ? null : $weight,
            inventory: $product->managing_stock() ? $product->get_stock_quantity() : null,
            metafields: $metafields,
        );
    }

    /** @return array<string, string> */
    private static function stringMap(mixed $value): array
    {
        $map = [];
        foreach (is_array($value) ? $value : [] as $key => $item) {
            if (is_string($key) && is_scalar($item)) {
                $map[$key] = (string) $item;
            }
        }

        return $map;
    }

    /** @return list<string> */
    private static function termNames(int $productId, string $taxonomy): array
    {
        if (!taxonomy_exists($taxonomy)) {
            return [];
        }
        $names = wp_get_post_terms($productId, $taxonomy, ['fields' => 'names']);

        return is_array($names) ? array_values(array_filter($names, is_string(...))) : [];
    }

    private static function typeTaxonomy(): string
    {
        $taxonomy = apply_filters(self::TYPE_TAXONOMY_FILTER, self::DEFAULT_TYPE_TAXONOMY);

        return is_string($taxonomy) ? $taxonomy : self::DEFAULT_TYPE_TAXONOMY;
    }
}
