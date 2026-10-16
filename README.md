<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# wp-woo-dynamic-collections

Shopify-style "smart collections" for WooCommerce. Give a product category a set of conditions and
products join it when they match and leave it when they stop matching: on every product save, stock
change, rule change, and a daily pass.

Conditions use Shopify's own rule set, so collections moved from Shopify keep working unchanged:

| Field | Comparisons |
|---|---|
| Product tag | is / is not |
| Product title, type, vendor; variant title | is, is not, starts with, ends with, contains, does not contain |
| Price, compare-at price, weight | is, is not, greater than, less than |
| Inventory stock | is, greater than, less than |
| Price reduced (on sale) | is set / is not set |
| Product category (standard taxonomy), with or without subcategories | is / is not |
| Product or variant metafield (`namespace.key`) | text and number comparisons, is set / is not set |

A category can require **all** conditions or **any** of them. As in Shopify, a condition on something a
product has several of (tags, variants) holds when any of them matches, and a negative condition ("is
not", "does not contain") holds when none does. Text is compared without regard to case; numbers exactly.

Only rule-based categories are ever changed. A product's hand-picked categories are left alone, and a
category whose conditions this plugin can't evaluate keeps its products as they are rather than guessing.

## Where the facts come from

| Field | WooCommerce source |
|---|---|
| Tags, title | Product tags, product name |
| Vendor | Brands (`product_brand`, core since WooCommerce 9.6) |
| Type | The "Product type" attribute (`pa_product-type`; change with the `wdc_product_type_taxonomy` filter) |
| Variant fields | Each published variation (a simple product is one variant): active price, regular price while on sale, weight in the store unit, managed stock |
| Category, metafields | Supplied by other plugins through the `wdc_product_extra_facts` filter (e.g. a Shopify importer) |

## For other plugins

Rules are stored per category in the `_wdc_rules` term meta as JSON in Shopify's shape:
`{"disjunctive": false, "rules": [{"column": "TAG", "relation": "EQUALS", "condition": "sale"}]}`
(`metafield: "namespace.key"` on metafield rules). Facts WooCommerce has no field for are added as
plain data, with no dependency on this plugin's classes:

```php
add_filter('wdc_product_extra_facts', function (array $facts, WC_Product $product): array {
    return $facts + [
        'category_id' => 'aa-2-26',                                   // standard taxonomy id or GID
        'metafields' => ['custom.season' => 'Fall'],                  // "namespace.key" => value
        'variant_metafields' => [123 => ['custom.fabric' => 'cotton']], // variation id => metafields
    ];
}, 10, 2);
```

## Commands

```sh
wp dynamic-collections reconcile [--dry-run]
```

## Development

```sh
composer install    # also enables the pre-commit hook
composer check      # lint, format, PHPStan (max), unit tests, audit
composer e2e        # WordPress + WooCommerce + MariaDB in podman
```

## License

[AGPL-3.0-or-later](LICENSE).
