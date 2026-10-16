<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Rules;

/** What a rule can see of one variant (a simple product is its own single variant). */
final readonly class VariantFacts
{
    /** @param array<string, string> $metafields "<namespace>.<key>" => value */
    public function __construct(
        public string $title,
        public ?string $price,
        public ?string $compareAtPrice,
        public ?string $weight,
        public ?int $inventory,
        public array $metafields = [],
    ) {}
}
