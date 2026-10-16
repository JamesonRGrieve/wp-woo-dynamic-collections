<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/*
 * End-to-end checks, registered as `wp wdc-e2e <scenario>` by the e2e mu-plugin. Each scenario throws on
 * the first failed expectation and prints what it verified.
 */

use WooDynamicCollections\Rules\Rule;
use WooDynamicCollections\Rules\RuleSet;
use WooDynamicCollections\Wp\Membership;
use WooDynamicCollections\Wp\RuleStore;

const WDC_E2E_IDS = 'wdc_e2e_ids';

function wdc_expect(bool $condition, string $what): void
{
    if (!$condition) {
        throw new RuntimeException("FAILED: {$what}");
    }
    WP_CLI::log("ok - {$what}");
}

function wdc_category(string $name, ?RuleSet $rules): int
{
    $term = wp_insert_term($name, RuleStore::TAXONOMY);
    if (is_wp_error($term)) {
        throw new RuntimeException("Could not create {$name}: " . $term->get_error_message());
    }
    $id = (int) $term['term_id'];
    if ($rules !== null) {
        (new RuleStore())->set($id, $rules);
    }

    return $id;
}

/**
 * @param list<string> $tags
 * @param list<int> $categories
 */
function wdc_product(string $name, string $regular, ?string $sale, array $tags, array $categories): int
{
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_regular_price($regular);
    $product->set_sale_price($sale ?? '');
    $product->set_category_ids($categories);
    $product->set_status('publish');
    $id = $product->save();
    wp_set_object_terms($id, $tags, 'product_tag');
    $product = wc_get_product($id);
    if (!$product instanceof WC_Product) {
        throw new RuntimeException("Product {$name} vanished");
    }
    $product->save();

    return $id;
}

/** @return array<string, int> */
function wdc_ids(): array
{
    $ids = get_option(WDC_E2E_IDS, []);
    $typed = [];
    foreach (is_array($ids) ? $ids : [] as $name => $id) {
        if (is_string($name) && is_int($id)) {
            $typed[$name] = $id;
        }
    }

    return $typed;
}

/** @return list<int> */
function wdc_categories_of(int $productId): array
{
    clean_object_term_cache($productId, 'product');
    $product = wc_get_product($productId);
    $ids = $product instanceof WC_Product ? Membership::ids($product->get_category_ids('edit')) : [];
    sort($ids);

    return $ids;
}

/**
 * Asserts a product's categories, ignoring WooCommerce's default category (it files every product saved
 * without one there; neither the rules nor this suite control it). A failure prints both lists.
 *
 * @param list<int> $expected
 */
function wdc_expect_categories(int $productId, array $expected, string $what): void
{
    $stored = get_option('default_product_cat', 0);
    $default = is_numeric($stored) ? (int) $stored : 0;
    $actual = array_values(array_filter(wdc_categories_of($productId), static fn(int $id): bool => $id !== $default));
    sort($expected);
    wdc_expect($actual === $expected, $what . ' (expected [' . implode(',', $expected) . '], got [' . implode(',', $actual) . '])');
}

function wdc_checksum(): string
{
    global $wpdb;
    $row = $wpdb instanceof wpdb ? $wpdb->get_row($wpdb->prepare('CHECKSUM TABLE %i', $wpdb->term_relationships), ARRAY_A) : null;

    return is_array($row) && is_scalar($row['Checksum'] ?? null) ? (string) $row['Checksum'] : 'missing';
}

function wdc_setup(): void
{
    $sale = wdc_category('Sale', new RuleSet(false, [new Rule('IS_PRICE_REDUCED', 'IS_SET', '')]));
    $comfort = wdc_category('Comfort or cheap', new RuleSet(true, [new Rule('TAG', 'EQUALS', 'comfort'), new Rule('VARIANT_PRICE', 'LESS_THAN', '20')]));
    $picks = wdc_category('Staff picks', null);
    $future = wdc_category('Future rules', new RuleSet(false, [new Rule('FUTURE_COLUMN', 'EQUALS', 'x')]));
    $onSale = wdc_product('On sale comfy', '50', '40', ['Comfort'], []);
    $full = wdc_product('Full price', '50', null, [], [$picks, $sale, $future]);
    update_option(WDC_E2E_IDS, compact('sale', 'comfort', 'picks', 'future', 'onSale', 'full'), false);
    WP_CLI::log('set up');
}

function wdc_check_initial(): void
{
    $ids = wdc_ids();
    wdc_expect_categories($ids['onSale'], [$ids['sale'], $ids['comfort']], 'on-sale comfort product joined Sale and the any-condition category');
    wdc_expect_categories($ids['full'], [$ids['picks'], $ids['future']], 'full-price product: removed from Sale, kept its hand-picked and not-evaluable categories');
}

function wdc_check_change(): void
{
    $ids = wdc_ids();
    $product = wc_get_product($ids['full']);
    if (!$product instanceof WC_Product) {
        throw new RuntimeException('Full-price product missing');
    }
    $product->set_sale_price('15');
    $product->save();
    wdc_expect_categories($ids['full'], [$ids['sale'], $ids['comfort'], $ids['picks'], $ids['future']], 'a price drop moved the product into Sale and the cheap category on save');
}

/** @param array<array-key, mixed> $args */
function wdc_scenario(array $args): void
{
    $scenario = is_string($args[0] ?? null) ? $args[0] : '';
    $file = is_string($args[1] ?? null) ? $args[1] : '';
    match ($scenario) {
        'setup' => wdc_setup(),
        'initial' => wdc_check_initial(),
        'change' => wdc_check_change(),
        'checksum' => file_put_contents($file, wdc_checksum()),
        'unchanged' => wdc_expect(wdc_checksum() === (string) file_get_contents($file), 'reconcile left category membership byte-identical'),
        default => throw new RuntimeException("Unknown scenario {$scenario}"),
    };
}

WP_CLI::add_command('wdc-e2e', wdc_scenario(...));
