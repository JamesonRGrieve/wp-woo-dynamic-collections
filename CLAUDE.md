# wp-woo-dynamic-collections — Agent Operating Guide

AGPL-3.0-or-later WordPress plugin (requires WooCommerce): Shopify-style rule-based product categories.
README.md is the contract. PHP rules: `/home/jameson/Source/ai-prompts/php.md`.

## Layout

- `src/Rules/` — pure, WordPress-free: Shopify's columns and relations, the stored rule shape
  (`RuleSet`), product facts, and the `Evaluator`. Everything here is unit-tested.
- `src/Wp/` — WooCommerce side: `RuleStore` (term meta `_wdc_rules`), `FactsProvider` (product →
  facts, extended with plain data through `wdc_product_extra_facts`), `Membership` (apply one product), `ProductEvents`,
  `Reconciler` (batched full passes on Action Scheduler), `CategoryRulesField` (admin editor), `Cli`.
- `assets/rules.js` — the editor's add/remove rows and relation filtering.

## Rules

- Semantics follow Shopify exactly: any-value for multi-valued facts, negatives as "none", case-insensitive
  text, exact decimals. A rule set that isn't fully evaluable never changes membership.
- Only rule-based categories are written, and only when membership differs (an unchanged pass writes
  nothing).
- The stored rule shape and the `wdc_product_extra_facts` filter are a public contract with other plugins
  (the Shopify importer); change them only compatibly.
- Unknown columns/relations are preserved on round trips, never dropped.
