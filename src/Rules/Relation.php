<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Rules;

/** How a rule compares a product's value with its condition (Shopify's CollectionRuleRelation). */
enum Relation: string
{
    case Equals = 'EQUALS';
    case NotEquals = 'NOT_EQUALS';
    case Contains = 'CONTAINS';
    case NotContains = 'NOT_CONTAINS';
    case StartsWith = 'STARTS_WITH';
    case EndsWith = 'ENDS_WITH';
    case GreaterThan = 'GREATER_THAN';
    case LessThan = 'LESS_THAN';
    case IsSet = 'IS_SET';
    case IsNotSet = 'IS_NOT_SET';

    /** A negative relation holds when no value satisfies its positive counterpart. */
    public function positive(): self
    {
        return match ($this) {
            self::NotEquals => self::Equals,
            self::NotContains => self::Contains,
            self::IsNotSet => self::IsSet,
            default => $this,
        };
    }

    public function isNegative(): bool
    {
        return $this->positive() !== $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'is equal to',
            self::NotEquals => 'is not equal to',
            self::Contains => 'contains',
            self::NotContains => 'does not contain',
            self::StartsWith => 'starts with',
            self::EndsWith => 'ends with',
            self::GreaterThan => 'is greater than',
            self::LessThan => 'is less than',
            self::IsSet => 'is set',
            self::IsNotSet => 'is not set',
        };
    }
}
