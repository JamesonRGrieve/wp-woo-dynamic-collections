<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Rules;

/**
 * One condition. Kept as the raw strings it was stored with, so a rule written by a newer Shopify (an
 * unknown column or relation) survives round trips and simply makes its category not evaluable.
 */
final readonly class Rule
{
    public function __construct(
        public string $column,
        public string $relation,
        public string $condition,
        public ?string $metafield = null,
    ) {}

    public function knownColumn(): ?Column
    {
        return Column::tryFrom($this->column);
    }

    public function knownRelation(): ?Relation
    {
        return Relation::tryFrom($this->relation);
    }

    /** Whether this plugin can evaluate the rule exactly as Shopify would. */
    public function isEvaluable(): bool
    {
        $column = $this->knownColumn();
        $relation = $this->knownRelation();
        if ($column === null || $relation === null || !in_array($relation, $column->relations(), true)) {
            return false;
        }
        if ($column->needsMetafield() && ($this->metafield === null || !str_contains($this->metafield, '.'))) {
            return false;
        }
        $needsCondition = $relation !== Relation::IsSet && $relation !== Relation::IsNotSet;

        return !$needsCondition || !$column->isNumeric() || Decimal::isNumber($this->condition);
    }

    /** @return array{column: string, relation: string, condition: string, metafield?: string} */
    public function toArray(): array
    {
        $array = ['column' => $this->column, 'relation' => $this->relation, 'condition' => $this->condition];
        if ($this->metafield !== null) {
            $array['metafield'] = $this->metafield;
        }

        return $array;
    }
}
