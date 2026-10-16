<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

/** Untrusted request input, unslashed, read with a type at the boundary. */
final readonly class Input
{
    /** @param array<array-key, mixed> $values */
    private function __construct(private array $values) {}

    public static function post(): self
    {
        return new self(wp_unslash($_POST));
    }

    public static function query(): self
    {
        return new self(wp_unslash($_GET));
    }

    public function text(string $key): string
    {
        $value = $this->values[$key] ?? '';

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    /**
     * A list of submitted rows (e.g. name="rules[0][column]"), each a map of scalar fields as strings.
     *
     * @return list<array<string, string>>
     */
    public function rows(string $key): array
    {
        $rows = [];
        foreach (is_array($this->values[$key] ?? null) ? $this->values[$key] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $fields = [];
            foreach ($row as $field => $value) {
                if (is_string($field) && is_scalar($value)) {
                    $fields[$field] = (string) $value;
                }
            }
            $rows[] = $fields;
        }

        return $rows;
    }
}
