<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Rules;

/** Everything a rule can test about a product. */
final readonly class ProductFacts
{
    /**
     * @param list<string> $tags
     * @param list<VariantFacts> $variants
     * @param array<string, string> $metafields "<namespace>.<key>" => value
     * @param ?string $categoryId a standard-taxonomy id such as "aa-2-26" (or its Shopify GID)
     */
    public function __construct(
        public string $title,
        public array $tags,
        public ?string $type,
        public ?string $vendor,
        public array $variants,
        public ?string $categoryId = null,
        public array $metafields = [],
    ) {}
}
