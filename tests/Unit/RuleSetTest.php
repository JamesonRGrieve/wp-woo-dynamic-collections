<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooDynamicCollections\Rules\Column;
use WooDynamicCollections\Rules\Decimal;
use WooDynamicCollections\Rules\Relation;
use WooDynamicCollections\Rules\RuleSet;

final class RuleSetTest extends TestCase
{
    public function testRoundTripsShopifysShape(): void
    {
        $stored = [
            'disjunctive' => true,
            'rules' => [
                ['column' => 'TAG', 'relation' => 'EQUALS', 'condition' => 'sale'],
                ['column' => 'PRODUCT_METAFIELD_DEFINITION', 'relation' => 'EQUALS', 'condition' => 'x', 'metafield' => 'custom.a'],
            ],
        ];

        self::assertSame($stored, RuleSet::fromArray($stored)->toArray());
    }

    public function testNormalisesAndDropsMalformedRows(): void
    {
        $rules = RuleSet::fromArray([
            'disjunctive' => 'yes',
            'rules' => [
                ['column' => ' tag ', 'relation' => 'equals', 'condition' => ' sale '],
                ['relation' => 'EQUALS'],
                'not a row',
                ['column' => 'VARIANT_PRICE', 'relation' => 'LESS_THAN', 'condition' => 10],
            ],
        ]);

        self::assertFalse($rules->disjunctive);
        self::assertCount(2, $rules->rules);
        self::assertSame(['column' => 'TAG', 'relation' => 'EQUALS', 'condition' => 'sale'], $rules->rules[0]->toArray());
        self::assertSame('10', $rules->rules[1]->condition);
        self::assertTrue($rules->isEvaluable());
    }

    public function testUnknownColumnsSurviveARoundTrip(): void
    {
        $stored = ['disjunctive' => false, 'rules' => [['column' => 'FUTURE_COLUMN', 'relation' => 'EQUALS', 'condition' => 'x']]];

        self::assertSame($stored, RuleSet::fromArray($stored)->toArray());
        self::assertFalse(RuleSet::fromArray($stored)->isEvaluable());
    }

    public function testEveryColumnOffersOnlyShopifysRelations(): void
    {
        self::assertSame([Relation::Equals, Relation::NotEquals], Column::Tag->relations());
        self::assertNotContains(Relation::NotEquals, Column::VariantInventory->relations());
        self::assertSame([Relation::IsSet, Relation::IsNotSet], Column::IsPriceReduced->relations());
        foreach (Column::cases() as $column) {
            self::assertNotSame([], $column->relations(), $column->value);
        }
    }

    public function testDecimalComparisonIsExact(): void
    {
        self::assertTrue(Decimal::isNumber('-0.50'));
        self::assertFalse(Decimal::isNumber('1e3'));
        self::assertSame(0, Decimal::compare('72.00', '72'));
        self::assertSame(1, Decimal::compare('0.30', '0.1'));
        self::assertSame(-1, Decimal::compare('-2', '1'));
        self::assertSame(1, Decimal::compare('-1', '-2'));
        self::assertSame(0, Decimal::compare('-0', '0.0'));
        self::assertSame(1, Decimal::compare('100', '99.999'));
    }
}
