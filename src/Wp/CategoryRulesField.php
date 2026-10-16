<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace WooDynamicCollections\Wp;

use WooDynamicCollections\Rules\Column;
use WooDynamicCollections\Rules\Relation;
use WooDynamicCollections\Rules\Rule;
use WooDynamicCollections\Rules\RuleSet;
use WP_Term;

/**
 * The rules editor on the product category add/edit screens. Saving rules makes the category dynamic
 * and queues a pass over every product; removing all rules makes it an ordinary category again.
 */
final class CategoryRulesField
{
    private const NONCE_ACTION = 'wdc_save_rules';
    private const NONCE_FIELD = 'wdc_rules_nonce';
    private const SCRIPT_HANDLE = 'wdc-rules';
    private const CAPABILITY = 'manage_product_terms';

    public function __construct(
        private readonly RuleStore $store,
        private readonly Reconciler $reconciler,
        private readonly string $pluginFile,
    ) {}

    public function register(): void
    {
        add_action(RuleStore::TAXONOMY . '_add_form_fields', $this->renderAdd(...));
        add_action(RuleStore::TAXONOMY . '_edit_form_fields', $this->renderEdit(...));
        add_action('created_' . RuleStore::TAXONOMY, $this->save(...));
        add_action('edited_' . RuleStore::TAXONOMY, $this->save(...));
        add_action('admin_enqueue_scripts', $this->enqueue(...));
    }

    public function enqueue(string $hook): void
    {
        if (($hook === 'edit-tags.php' || $hook === 'term.php') && Input::query()->text('taxonomy') === RuleStore::TAXONOMY) {
            wp_enqueue_script(self::SCRIPT_HANDLE, plugins_url('assets/rules.js', $this->pluginFile), [], Plugin::VERSION, true);
        }
    }

    public function renderAdd(): void
    {
        echo '<div class="form-field">';
        $this->editor(null);
        echo '</div>';
    }

    public function renderEdit(WP_Term $term): void
    {
        echo '<tr class="form-field"><th scope="row">' . esc_html__('Automatic membership', 'wp-woo-dynamic-collections') . '</th><td>';
        $this->editor($this->store->get($term->term_id));
        echo '</td></tr>';
    }

    public function save(int $termId): void
    {
        $input = Input::post();
        if (!current_user_can(self::CAPABILITY) || !wp_verify_nonce($input->text(self::NONCE_FIELD), self::NONCE_ACTION)) {
            return;
        }
        $rules = [];
        foreach ($input->rows('wdc_rules') as $row) {
            $metafield = sanitize_text_field($row['metafield'] ?? '');
            $rule = new Rule(
                strtoupper(sanitize_key($row['column'] ?? '')),
                strtoupper(sanitize_key($row['relation'] ?? '')),
                sanitize_text_field($row['condition'] ?? ''),
                $metafield === '' ? null : $metafield,
            );
            if ($rule->column !== '') {
                $rules[] = $rule;
            }
        }
        $before = $this->store->get($termId);
        $after = new RuleSet($input->text('wdc_match') === 'any', $rules);
        $this->store->set($termId, $after);
        if ($before?->toArray() !== ($after->rules === [] ? null : $after->toArray())) {
            $this->reconciler->schedule();
        }
    }

    private function editor(?RuleSet $rules): void
    {
        $rules ??= new RuleSet(false, []);
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<fieldset class="wdc-rules"><legend>' . esc_html__('Add products automatically when they match conditions', 'wp-woo-dynamic-collections') . '</legend>';
        if ($rules->rules !== [] && !$rules->isEvaluable()) {
            echo '<p class="notice notice-warning" role="status">' . esc_html__('Some conditions can\'t be evaluated here, so this category\'s products are left as they are.', 'wp-woo-dynamic-collections') . '</p>';
        }
        echo '<p><label for="wdc-match">' . esc_html__('Products must match', 'wp-woo-dynamic-collections') . '</label> <select id="wdc-match" name="wdc_match">'
            . '<option value="all"' . selected(!$rules->disjunctive, true, false) . '>' . esc_html__('all conditions', 'wp-woo-dynamic-collections') . '</option>'
            . '<option value="any"' . selected($rules->disjunctive, true, false) . '>' . esc_html__('any condition', 'wp-woo-dynamic-collections') . '</option></select></p>';
        echo '<ol class="wdc-rule-list" data-relations="' . esc_attr((string) wp_json_encode(self::relationsByColumn())) . '">';
        foreach ($rules->rules as $index => $rule) {
            $this->row((string) $index, $rule);
        }
        echo '</ol><template class="wdc-rule-template">';
        $this->row('__INDEX__', new Rule(Column::Tag->value, Relation::Equals->value, ''));
        echo '</template><p><button type="button" class="button wdc-add-rule">' . esc_html__('Add condition', 'wp-woo-dynamic-collections') . '</button></p>';
        echo '<p class="description">' . esc_html__('Leave without conditions to manage this category by hand.', 'wp-woo-dynamic-collections') . '</p></fieldset>';
    }

    private function row(string $index, Rule $rule): void
    {
        $name = static fn(string $field): string => "wdc_rules[{$index}][{$field}]";
        $id = static fn(string $field): string => "wdc-rule-{$index}-{$field}";
        echo '<li class="wdc-rule">';
        echo '<label class="screen-reader-text" for="' . esc_attr($id('column')) . '">' . esc_html__('Field', 'wp-woo-dynamic-collections') . '</label>';
        echo '<select class="wdc-column" id="' . esc_attr($id('column')) . '" name="' . esc_attr($name('column')) . '">';
        foreach (Column::cases() as $column) {
            echo '<option value="' . esc_attr($column->value) . '"' . selected($rule->column, $column->value, false) . '>' . esc_html($column->label()) . '</option>';
        }
        echo '</select> <label class="screen-reader-text" for="' . esc_attr($id('relation')) . '">' . esc_html__('Comparison', 'wp-woo-dynamic-collections') . '</label>';
        echo '<select class="wdc-relation" id="' . esc_attr($id('relation')) . '" name="' . esc_attr($name('relation')) . '">';
        foreach (Relation::cases() as $relation) {
            echo '<option value="' . esc_attr($relation->value) . '"' . selected($rule->relation, $relation->value, false) . '>' . esc_html($relation->label()) . '</option>';
        }
        echo '</select> <label class="screen-reader-text" for="' . esc_attr($id('condition')) . '">' . esc_html__('Value', 'wp-woo-dynamic-collections') . '</label>';
        echo '<input type="text" id="' . esc_attr($id('condition')) . '" name="' . esc_attr($name('condition')) . '" value="' . esc_attr($rule->condition) . '"> ';
        echo '<label class="screen-reader-text" for="' . esc_attr($id('metafield')) . '">' . esc_html__('Metafield (namespace.key)', 'wp-woo-dynamic-collections') . '</label>';
        echo '<input type="text" class="wdc-metafield" id="' . esc_attr($id('metafield')) . '" name="' . esc_attr($name('metafield')) . '" placeholder="namespace.key" value="' . esc_attr($rule->metafield ?? '') . '"> ';
        echo '<button type="button" class="button-link wdc-remove-rule">' . esc_html__('Remove condition', 'wp-woo-dynamic-collections') . '</button></li>';
    }

    /** @return array<string, list<string>> column => relations it allows (drives the relation picker) */
    private static function relationsByColumn(): array
    {
        $map = [];
        foreach (Column::cases() as $column) {
            $map[$column->value] = array_map(static fn(Relation $relation): string => $relation->value, $column->relations());
        }

        return $map;
    }
}
