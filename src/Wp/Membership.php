<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

use WC_Product;
use WC_Product_Variation;
use WooDynamicCollections\Rules\Evaluator;
use WooDynamicCollections\Rules\RuleSet;

/**
 * Puts a product in exactly the dynamic categories whose rules it matches. Hand-managed categories, and
 * dynamic ones whose rules can't be evaluated, are never touched; nothing is written when membership
 * is already right.
 */
final class Membership
{
    /** @var array<int, RuleSet>|null */
    private ?array $rules = null;

    public function __construct(
        private readonly RuleStore $store,
        private readonly FactsProvider $facts,
        private readonly Evaluator $evaluator,
    ) {}

    /** Re-evaluates one product (a variation counts as its parent); returns whether membership changed. */
    public function apply(int $productId, bool $dryRun = false): bool
    {
        $product = wc_get_product($productId);
        if ($product instanceof WC_Product_Variation) {
            $product = wc_get_product($product->get_parent_id());
        }
        if (!$product instanceof WC_Product) {
            return false;
        }
        $rules = $this->rules();
        if ($rules === []) {
            return false;
        }
        $facts = $this->facts->forProduct($product);
        $current = self::ids($product->get_category_ids('edit'));
        $wanted = array_values(array_filter(
            $current,
            static fn(int $termId): bool => !isset($rules[$termId]),
        ));
        foreach ($rules as $termId => $set) {
            if ($this->evaluator->matches($set, $facts)) {
                $wanted[] = $termId;
            }
        }
        sort($current);
        sort($wanted);
        if ($current === $wanted) {
            return false;
        }
        if (!$dryRun) {
            wp_set_object_terms($product->get_id(), $wanted, RuleStore::TAXONOMY);
            wc_delete_product_transients($product->get_id());
        }

        return true;
    }

    /** Forget cached rules (after a category's rules change within a request). */
    public function reset(): void
    {
        $this->rules = null;
    }

    /**
     * Term ids as WooCommerce returns them (ints or numeric strings), as ints.
     *
     * @param array<array-key, mixed> $ids
     *
     * @return list<int>
     */
    public static function ids(array $ids): array
    {
        $ints = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ints[] = (int) $id;
            }
        }

        return $ints;
    }

    /** @return array<int, RuleSet> */
    private function rules(): array
    {
        return $this->rules ??= $this->store->evaluable();
    }
}
