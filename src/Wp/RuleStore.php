<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

use WooDynamicCollections\Rules\RuleSet;

/**
 * A product category's rules, kept as term meta in Shopify's shape ({disjunctive, rules[]}). A category
 * with rules is "dynamic"; one without is an ordinary, hand-managed category.
 */
final class RuleStore
{
    /** Other plugins (e.g. a Shopify importer) write rules here; the shape is the public contract. */
    public const META_KEY = '_wdc_rules';
    public const TAXONOMY = 'product_cat';

    public function get(int $termId): ?RuleSet
    {
        $stored = get_term_meta($termId, self::META_KEY, true);
        if (is_string($stored) && $stored !== '') {
            $stored = json_decode($stored, true);
        }

        return is_array($stored) ? RuleSet::fromArray($stored) : null;
    }

    public function set(int $termId, RuleSet $rules): void
    {
        if ($rules->rules === []) {
            delete_term_meta($termId, self::META_KEY);

            return;
        }
        $encoded = (string) wp_json_encode($rules->toArray());
        if (get_term_meta($termId, self::META_KEY, true) !== $encoded) {
            update_term_meta($termId, self::META_KEY, $encoded);
        }
    }

    /** @return array<int, RuleSet> every dynamic category whose rules can be evaluated, by term id */
    public function evaluable(): array
    {
        $terms = get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'fields' => 'ids', 'meta_key' => self::META_KEY]);
        $rules = [];
        foreach (is_array($terms) ? $terms : [] as $termId) {
            $set = $this->get($termId);
            if ($set !== null && $set->isEvaluable()) {
                $rules[$termId] = $set;
            }
        }

        return $rules;
    }
}
