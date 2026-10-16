<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Rules;

/** Exact comparison of decimal strings (prices, weights), never through floats. */
final class Decimal
{
    private const PATTERN = '/^-?(\d+(\.\d*)?|\.\d+)$/';

    public static function isNumber(string $value): bool
    {
        return preg_match(self::PATTERN, trim($value)) === 1;
    }

    /** -1, 0 or 1. Both values must satisfy isNumber(). */
    public static function compare(string $a, string $b): int
    {
        [$negativeA, $wholeA, $fractionA] = self::parts($a);
        [$negativeB, $wholeB, $fractionB] = self::parts($b);
        if ($negativeA !== $negativeB) {
            return $negativeA ? -1 : 1;
        }
        $width = max(strlen($fractionA), strlen($fractionB));
        $magnitude = strlen($wholeA) !== strlen($wholeB)
            ? strlen($wholeA) <=> strlen($wholeB)
            : strcmp($wholeA . str_pad($fractionA, $width, '0'), $wholeB . str_pad($fractionB, $width, '0')) <=> 0;

        return $negativeA ? -$magnitude : $magnitude;
    }

    /** @return array{0: bool, 1: string, 2: string} sign, whole digits (no leading zeros), fraction digits (no trailing zeros) */
    private static function parts(string $value): array
    {
        $value = trim($value);
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $whole = ltrim($whole, '0');
        $fraction = rtrim($fraction, '0');
        if ($whole === '' && $fraction === '') {
            return [false, '', ''];
        }

        return [$negative, $whole, $fraction];
    }
}
