<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

/**
 * Re-evaluates every product, a batch per Action Scheduler action: after a category's rules change, and
 * daily (stock and sale prices can change without a product save, e.g. a scheduled sale ending).
 */
final class Reconciler
{
    public const GROUP = 'dynamic-collections';
    public const BATCH_HOOK = 'wdc_reconcile_batch';
    public const DAILY_HOOK = 'wdc_reconcile_daily';
    public const BATCH_SIZE = 100;
    private const SECONDS_PER_DAY = 86400;
    private const FIRST_PAGE = 1;

    public function __construct(private readonly Membership $membership) {}

    public function register(): void
    {
        add_action(self::BATCH_HOOK, $this->batch(...));
        add_action(self::DAILY_HOOK, $this->schedule(...));
        add_action('action_scheduler_init', static function (): void {
            if (!as_has_scheduled_action(self::DAILY_HOOK, [], self::GROUP)) {
                as_schedule_recurring_action(time() + self::SECONDS_PER_DAY, self::SECONDS_PER_DAY, self::DAILY_HOOK, [], self::GROUP, true);
            }
        });
    }

    /** Queues a full pass (once: a pass already waiting to start covers this request too). */
    public function schedule(): void
    {
        $this->membership->reset();
        $args = [self::FIRST_PAGE];
        if (!as_has_scheduled_action(self::BATCH_HOOK, $args, self::GROUP)) {
            as_enqueue_async_action(self::BATCH_HOOK, $args, self::GROUP);
        }
    }

    /** One batch; queues the next page while there are more products. */
    public function batch(int $page): void
    {
        $result = $this->run($page, false);
        if ($result['more']) {
            as_enqueue_async_action(self::BATCH_HOOK, [$page + 1], self::GROUP);
        }
    }

    /**
     * @return array{checked: int, changed: int, more: bool}
     */
    public function run(int $page, bool $dryRun): array
    {
        $ids = wc_get_products(['status' => 'any', 'type' => ['simple', 'variable', 'grouped', 'external'], 'limit' => self::BATCH_SIZE, 'page' => $page, 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
        $ids = is_array($ids) ? $ids : [];
        $changed = 0;
        foreach ($ids as $id) {
            if (is_int($id) && $this->membership->apply($id, $dryRun)) {
                ++$changed;
            }
        }

        return ['checked' => count($ids), 'changed' => $changed, 'more' => count($ids) === self::BATCH_SIZE];
    }

    public static function unscheduleAll(): void
    {
        foreach ([self::BATCH_HOOK, self::DAILY_HOOK] as $hook) {
            as_unschedule_all_actions($hook, [], self::GROUP);
        }
    }
}
