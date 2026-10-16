<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WooDynamicCollections\Rules\Evaluator;
use WooDynamicCollections\Rules\ProductFacts;
use WooDynamicCollections\Rules\Rule;
use WooDynamicCollections\Rules\RuleSet;
use WooDynamicCollections\Rules\VariantFacts;

final class EvaluatorTest extends TestCase
{
    private static function bra(): ProductFacts
    {
        return new ProductFacts(
            title: 'Everyday Bra',
            tags: ['Comfort', 'new'],
            type: 'Bras',
            vendor: 'Atelier North',
            variants: [
                new VariantFacts('34B / Beige', '72.00', null, '90', 3, ['custom.fabric' => 'cotton']),
                new VariantFacts('36C / Beige', '60', '72', null, 0),
            ],
            categoryId: 'gid://shopify/TaxonomyCategory/aa-1-13-1',
            metafields: ['custom.season' => 'Fall', 'custom.rank' => '7'],
        );
    }

    /** @return iterable<string, array{string, string, string, bool, ?string}> */
    public static function singleRules(): iterable
    {
        yield 'tag equals, case-insensitive' => ['TAG', 'EQUALS', 'comfort', true, null];
        yield 'tag not equals excludes tagged products' => ['TAG', 'NOT_EQUALS', 'new', false, null];
        yield 'tag not equals keeps untagged products' => ['TAG', 'NOT_EQUALS', 'sale', true, null];
        yield 'title contains' => ['TITLE', 'CONTAINS', 'day', true, null];
        yield 'title starts with' => ['TITLE', 'STARTS_WITH', 'every', true, null];
        yield 'title ends with' => ['TITLE', 'ENDS_WITH', 'bra', true, null];
        yield 'title does not contain' => ['TITLE', 'NOT_CONTAINS', 'scarf', true, null];
        yield 'type equals' => ['TYPE', 'EQUALS', 'bras', true, null];
        yield 'vendor not equals' => ['VENDOR', 'NOT_EQUALS', 'Atelier North', false, null];
        yield 'variant title contains (any variant)' => ['VARIANT_TITLE', 'CONTAINS', '36C', true, null];
        yield 'price greater than (any variant)' => ['VARIANT_PRICE', 'GREATER_THAN', '70', true, null];
        yield 'price less than (any variant)' => ['VARIANT_PRICE', 'LESS_THAN', '61', true, null];
        yield 'price equals is exact' => ['VARIANT_PRICE', 'EQUALS', '72', true, null];
        yield 'price not equals: no variant at that price' => ['VARIANT_PRICE', 'NOT_EQUALS', '60.00', false, null];
        yield 'compare-at greater than' => ['VARIANT_COMPARE_AT_PRICE', 'GREATER_THAN', '71.99', true, null];
        yield 'weight greater than ignores missing weights' => ['VARIANT_WEIGHT', 'GREATER_THAN', '100', false, null];
        yield 'inventory equals zero' => ['VARIANT_INVENTORY', 'EQUALS', '0', true, null];
        yield 'inventory greater than' => ['VARIANT_INVENTORY', 'GREATER_THAN', '5', false, null];
        yield 'price reduced' => ['IS_PRICE_REDUCED', 'IS_SET', '', true, null];
        yield 'price not reduced' => ['IS_PRICE_REDUCED', 'IS_NOT_SET', '', false, null];
        yield 'category by id' => ['PRODUCT_CATEGORY_ID', 'EQUALS', 'aa-1-13-1', true, null];
        yield 'category by gid' => ['PRODUCT_CATEGORY_ID', 'EQUALS', 'gid://shopify/TaxonomyCategory/aa-1-13-1', true, null];
        yield 'category descendant' => ['PRODUCT_CATEGORY_ID_WITH_DESCENDANTS', 'EQUALS', 'aa-1', true, null];
        yield 'category prefix is not a descendant' => ['PRODUCT_CATEGORY_ID_WITH_DESCENDANTS', 'EQUALS', 'aa-1-1', false, null];
        yield 'exact category does not include descendants' => ['PRODUCT_CATEGORY_ID', 'EQUALS', 'aa-1', false, null];
        yield 'product metafield equals' => ['PRODUCT_METAFIELD_DEFINITION', 'EQUALS', 'fall', true, 'custom.season'];
        yield 'product metafield numeric' => ['PRODUCT_METAFIELD_DEFINITION', 'GREATER_THAN', '5', true, 'custom.rank'];
        yield 'product metafield not set' => ['PRODUCT_METAFIELD_DEFINITION', 'IS_NOT_SET', '', true, 'custom.missing'];
        yield 'variant metafield (any variant)' => ['VARIANT_METAFIELD_DEFINITION', 'EQUALS', 'Cotton', true, 'custom.fabric'];
    }

    #[DataProvider('singleRules')]
    public function testSingleRule(string $column, string $relation, string $condition, bool $expected, ?string $metafield): void
    {
        $rules = new RuleSet(false, [new Rule($column, $relation, $condition, $metafield)]);

        self::assertSame($expected, (new Evaluator())->matches($rules, self::bra()));
    }

    public function testAllConditionsMustHold(): void
    {
        $rules = new RuleSet(false, [new Rule('TYPE', 'EQUALS', 'Bras'), new Rule('TAG', 'EQUALS', 'sale')]);

        self::assertFalse((new Evaluator())->matches($rules, self::bra()));
    }

    public function testAnyConditionMayHold(): void
    {
        $rules = new RuleSet(true, [new Rule('TYPE', 'EQUALS', 'Scarves'), new Rule('TAG', 'EQUALS', 'comfort')]);

        self::assertTrue((new Evaluator())->matches($rules, self::bra()));
    }

    public function testRulesThatCannotBeEvaluatedNeverMatch(): void
    {
        $evaluator = new Evaluator();
        $unknownColumn = new RuleSet(false, [new Rule('PRODUCT_SHIPPING_SPEED', 'EQUALS', 'fast')]);
        $wrongRelation = new RuleSet(false, [new Rule('TAG', 'CONTAINS', 'com')]);
        $metafieldWithoutKey = new RuleSet(false, [new Rule('PRODUCT_METAFIELD_DEFINITION', 'EQUALS', 'x')]);
        $nonNumericPrice = new RuleSet(false, [new Rule('VARIANT_PRICE', 'GREATER_THAN', 'ten')]);

        foreach ([$unknownColumn, $wrongRelation, $metafieldWithoutKey, $nonNumericPrice, new RuleSet(false, [])] as $rules) {
            self::assertFalse($rules->isEvaluable());
            self::assertFalse($evaluator->matches($rules, self::bra()));
        }
    }
}
