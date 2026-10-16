<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

/*
 * Removes the plugin's rules and scheduled actions. Categories and their current products stay as they
 * are (they become ordinary, hand-managed categories).
 */

use WooDynamicCollections\Wp\Reconciler;
use WooDynamicCollections\Wp\RuleStore;

if (!defined('WP_UNINSTALL_PLUGIN')) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

delete_metadata('term', 0, RuleStore::META_KEY, '', true);
if (function_exists('as_unschedule_all_actions')) {
    Reconciler::unscheduleAll();
}
