<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Rules;

/**
 * What a rule tests (Shopify's CollectionRuleColumn), the relations Shopify allows for it, and whether it
 * compares text or numbers.
 */
enum Column: string
{
    case Tag = 'TAG';
    case Title = 'TITLE';
    case Type = 'TYPE';
    case Vendor = 'VENDOR';
    case VariantTitle = 'VARIANT_TITLE';
    case VariantPrice = 'VARIANT_PRICE';
    case VariantCompareAtPrice = 'VARIANT_COMPARE_AT_PRICE';
    case VariantWeight = 'VARIANT_WEIGHT';
    case VariantInventory = 'VARIANT_INVENTORY';
    case IsPriceReduced = 'IS_PRICE_REDUCED';
    case ProductCategoryId = 'PRODUCT_CATEGORY_ID';
    case ProductCategoryIdWithDescendants = 'PRODUCT_CATEGORY_ID_WITH_DESCENDANTS';
    case ProductTaxonomyNodeId = 'PRODUCT_TAXONOMY_NODE_ID';
    case ProductMetafield = 'PRODUCT_METAFIELD_DEFINITION';
    case VariantMetafield = 'VARIANT_METAFIELD_DEFINITION';

    private const TEXT = [Relation::Equals, Relation::NotEquals, Relation::StartsWith, Relation::EndsWith, Relation::Contains, Relation::NotContains];
    private const NUMBER = [Relation::Equals, Relation::NotEquals, Relation::GreaterThan, Relation::LessThan];

    /** @return list<Relation> */
    public function relations(): array
    {
        return match ($this) {
            self::Tag => [Relation::Equals, Relation::NotEquals],
            self::Title, self::Type, self::Vendor, self::VariantTitle => self::TEXT,
            self::VariantPrice, self::VariantCompareAtPrice, self::VariantWeight => self::NUMBER,
            self::VariantInventory => [Relation::Equals, Relation::GreaterThan, Relation::LessThan],
            self::IsPriceReduced => [Relation::IsSet, Relation::IsNotSet],
            self::ProductCategoryId, self::ProductCategoryIdWithDescendants, self::ProductTaxonomyNodeId => [Relation::Equals, Relation::NotEquals],
            self::ProductMetafield, self::VariantMetafield => [...self::TEXT, Relation::GreaterThan, Relation::LessThan, Relation::IsSet, Relation::IsNotSet],
        };
    }

    public function isNumeric(): bool
    {
        return in_array($this, [self::VariantPrice, self::VariantCompareAtPrice, self::VariantWeight, self::VariantInventory], true);
    }

    public function needsMetafield(): bool
    {
        return $this === self::ProductMetafield || $this === self::VariantMetafield;
    }

    public function label(): string
    {
        return match ($this) {
            self::Tag => 'Product tag',
            self::Title => 'Product title',
            self::Type => 'Product type',
            self::Vendor => 'Product vendor',
            self::VariantTitle => 'Variant title',
            self::VariantPrice => 'Price',
            self::VariantCompareAtPrice => 'Compare-at price',
            self::VariantWeight => 'Weight',
            self::VariantInventory => 'Inventory stock',
            self::IsPriceReduced => 'Price reduced (on sale)',
            self::ProductCategoryId => 'Product category',
            self::ProductCategoryIdWithDescendants => 'Product category or its subcategories',
            self::ProductTaxonomyNodeId => 'Product category (legacy)',
            self::ProductMetafield => 'Product metafield',
            self::VariantMetafield => 'Variant metafield',
        };
    }
}
