// SPDX-License-Identifier: AGPL-3.0-or-later
// Rules editor on the product category screens: add/remove condition rows, offer only the comparisons
// the chosen field allows, and show the metafield box only for metafield conditions.
(() => {
  'use strict';

  const METAFIELD_COLUMNS = ['PRODUCT_METAFIELD_DEFINITION', 'VARIANT_METAFIELD_DEFINITION'];

  const syncRow = (row, relations) => {
    const column = row.querySelector('.wdc-column').value;
    const allowed = relations[column] || [];
    const relation = row.querySelector('.wdc-relation');
    for (const option of relation.options) {
      option.hidden = !allowed.includes(option.value);
      option.disabled = option.hidden;
    }
    if (relation.selectedOptions[0] && relation.selectedOptions[0].disabled) {
      relation.value = allowed[0] || '';
    }
    row.querySelector('.wdc-metafield').hidden = !METAFIELD_COLUMNS.includes(column);
  };

  const setUp = (fieldset) => {
    const list = fieldset.querySelector('.wdc-rule-list');
    const template = fieldset.querySelector('.wdc-rule-template');
    const relations = JSON.parse(list.dataset.relations || '{}');
    let next = list.children.length;

    list.querySelectorAll('.wdc-rule').forEach((row) => syncRow(row, relations));
    list.addEventListener('change', (event) => {
      if (event.target.classList.contains('wdc-column')) {
        syncRow(event.target.closest('.wdc-rule'), relations);
      }
    });
    list.addEventListener('click', (event) => {
      if (event.target.classList.contains('wdc-remove-rule')) {
        const row = event.target.closest('.wdc-rule');
        const focusTarget = row.previousElementSibling || row.nextElementSibling;
        row.remove();
        (focusTarget ? focusTarget.querySelector('select') : fieldset.querySelector('.wdc-add-rule')).focus();
      }
    });
    fieldset.querySelector('.wdc-add-rule').addEventListener('click', () => {
      const html = template.innerHTML.replaceAll('__INDEX__', String(next));
      next += 1;
      list.insertAdjacentHTML('beforeend', html);
      const row = list.lastElementChild;
      syncRow(row, relations);
      row.querySelector('select').focus();
    });
  };

  document.querySelectorAll('.wdc-rules').forEach(setUp);
})();
