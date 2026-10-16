<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Rules;

/** A category's rules: all must hold (conjunctive) or any may hold (disjunctive), as in Shopify. */
final readonly class RuleSet
{
    /** @param list<Rule> $rules */
    public function __construct(public bool $disjunctive, public array $rules) {}

    /**
     * Builds a rule set from its stored shape, dropping malformed rows (never throwing on stored data).
     *
     * @param array<array-key, mixed> $stored {disjunctive: bool, rules: list<{column, relation, condition, metafield?}>}
     */
    public static function fromArray(array $stored): self
    {
        $rules = [];
        foreach (is_array($stored['rules'] ?? null) ? $stored['rules'] : [] as $row) {
            if (!is_array($row) || !is_string($row['column'] ?? null) || !is_string($row['relation'] ?? null)) {
                continue;
            }
            $condition = $row['condition'] ?? '';
            $metafield = $row['metafield'] ?? null;
            $rules[] = new Rule(
                strtoupper(trim($row['column'])),
                strtoupper(trim($row['relation'])),
                is_scalar($condition) ? trim((string) $condition) : '',
                is_string($metafield) && trim($metafield) !== '' ? trim($metafield) : null,
            );
        }

        return new self(($stored['disjunctive'] ?? false) === true, $rules);
    }

    /** @return array{disjunctive: bool, rules: list<array{column: string, relation: string, condition: string, metafield?: string}>} */
    public function toArray(): array
    {
        return [
            'disjunctive' => $this->disjunctive,
            'rules' => array_map(static fn(Rule $rule): array => $rule->toArray(), $this->rules),
        ];
    }

    /** Whether every rule can be evaluated; otherwise the category's membership is left untouched. */
    public function isEvaluable(): bool
    {
        if ($this->rules === []) {
            return false;
        }
        foreach ($this->rules as $rule) {
            if (!$rule->isEvaluable()) {
                return false;
            }
        }

        return true;
    }
}
