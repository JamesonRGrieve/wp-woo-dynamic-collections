<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Rules;

/**
 * Decides whether a product belongs to a rule-based collection, the way Shopify does: a rule over values
 * a product has several of (tags, variants) holds if any value satisfies it; a negative relation holds
 * if none satisfies its positive form. Text compares case-insensitively; numbers compare exactly.
 */
final class Evaluator
{
    private const TAXONOMY_GID_SEPARATOR = '/';
    private const TAXONOMY_LEVEL_SEPARATOR = '-';

    public function matches(RuleSet $rules, ProductFacts $product): bool
    {
        if (!$rules->isEvaluable()) {
            return false;
        }
        foreach ($rules->rules as $rule) {
            $holds = $this->holds($rule, $product);
            if ($rules->disjunctive && $holds) {
                return true;
            }
            if (!$rules->disjunctive && !$holds) {
                return false;
            }
        }

        return !$rules->disjunctive;
    }

    private function holds(Rule $rule, ProductFacts $product): bool
    {
        $column = $rule->knownColumn();
        $relation = $rule->knownRelation();
        if ($column === null || $relation === null) {
            return false;
        }
        if ($column === Column::IsPriceReduced) {
            $reduced = $this->anyVariantReduced($product);

            return $relation === Relation::IsSet ? $reduced : !$reduced;
        }
        $positive = $relation->positive();
        $any = false;
        foreach ($this->values($column, $rule, $product) as $value) {
            if ($this->compare($column, $positive, $value, $rule->condition)) {
                $any = true;
                break;
            }
        }

        return $relation->isNegative() ? !$any : $any;
    }

    /** @return list<?string> the product's values for a column (one per tag / variant) */
    private function values(Column $column, Rule $rule, ProductFacts $product): array
    {
        $variants = $product->variants;

        return match ($column) {
            Column::Tag => $product->tags,
            Column::Title => [$product->title],
            Column::Type => [$product->type],
            Column::Vendor => [$product->vendor],
            Column::VariantTitle => array_map(static fn(VariantFacts $v): string => $v->title, $variants),
            Column::VariantPrice => array_map(static fn(VariantFacts $v): ?string => $v->price, $variants),
            Column::VariantCompareAtPrice => array_map(static fn(VariantFacts $v): ?string => $v->compareAtPrice, $variants),
            Column::VariantWeight => array_map(static fn(VariantFacts $v): ?string => $v->weight, $variants),
            Column::VariantInventory => array_map(static fn(VariantFacts $v): ?string => $v->inventory === null ? null : (string) $v->inventory, $variants),
            Column::ProductCategoryId, Column::ProductCategoryIdWithDescendants, Column::ProductTaxonomyNodeId => [$product->categoryId],
            Column::ProductMetafield => [$product->metafields[(string) $rule->metafield] ?? null],
            Column::VariantMetafield => array_map(static fn(VariantFacts $v): ?string => $v->metafields[(string) $rule->metafield] ?? null, $variants),
            Column::IsPriceReduced => [],
        };
    }

    private function compare(Column $column, Relation $relation, ?string $value, string $condition): bool
    {
        if ($relation === Relation::IsSet) {
            return $value !== null && trim($value) !== '';
        }
        if ($value === null) {
            return false;
        }
        if ($column === Column::ProductCategoryId || $column === Column::ProductTaxonomyNodeId || $column === Column::ProductCategoryIdWithDescendants) {
            $id = self::taxonomyId($value);
            $wanted = self::taxonomyId($condition);

            return $id === $wanted
                || ($column === Column::ProductCategoryIdWithDescendants && str_starts_with($id, $wanted . self::TAXONOMY_LEVEL_SEPARATOR));
        }
        $numeric = $column->isNumeric() || (($relation === Relation::GreaterThan || $relation === Relation::LessThan) && Decimal::isNumber($value) && Decimal::isNumber($condition));
        if ($numeric) {
            if (!Decimal::isNumber($value) || !Decimal::isNumber($condition)) {
                return false;
            }
            $order = Decimal::compare($value, $condition);

            return match ($relation) {
                Relation::Equals => $order === 0,
                Relation::GreaterThan => $order > 0,
                Relation::LessThan => $order < 0,
                default => false,
            };
        }
        $value = mb_strtolower(trim($value));
        $condition = mb_strtolower(trim($condition));

        return match ($relation) {
            Relation::Equals => $value === $condition,
            Relation::Contains => str_contains($value, $condition),
            Relation::StartsWith => str_starts_with($value, $condition),
            Relation::EndsWith => str_ends_with($value, $condition),
            default => false,
        };
    }

    private function anyVariantReduced(ProductFacts $product): bool
    {
        foreach ($product->variants as $variant) {
            if ($variant->price !== null && $variant->compareAtPrice !== null
                && Decimal::isNumber($variant->price) && Decimal::isNumber($variant->compareAtPrice)
                && Decimal::compare($variant->compareAtPrice, $variant->price) > 0) {
                return true;
            }
        }

        return false;
    }

    /** "gid://shopify/TaxonomyCategory/aa-2-26" and "aa-2-26" are the same category. */
    private static function taxonomyId(string $value): string
    {
        $value = trim($value);
        $slash = strrpos($value, self::TAXONOMY_GID_SEPARATOR);

        return strtolower($slash === false ? $value : substr($value, $slash + 1));
    }
}
