<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

use WP_CLI;

/**
 * Rule-based product categories.
 *
 * ## EXAMPLES
 *
 *     wp dynamic-collections reconcile
 *     wp dynamic-collections reconcile --dry-run
 */
final class Cli
{
    public function __construct(private readonly Reconciler $reconciler) {}

    /**
     * Re-evaluates every product against every dynamic category now, in the foreground.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Count the products whose categories would change, without changing them.
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function reconcile(array $args, array $assoc): void
    {
        $dryRun = isset($assoc['dry-run']);
        $checked = 0;
        $changed = 0;
        $page = 1;
        do {
            $result = $this->reconciler->run($page++, $dryRun);
            $checked += $result['checked'];
            $changed += $result['changed'];
        } while ($result['more']);
        WP_CLI::success(sprintf('%d products checked; %d %s.', $checked, $changed, $dryRun ? 'would change' : 'changed'));
    }
}
