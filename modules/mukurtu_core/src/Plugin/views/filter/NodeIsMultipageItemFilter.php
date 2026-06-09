<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Filters content nodes by multipage item page position.
 *
 * "First page only" limits results to nodes that appear as the first page
 * (delta = 0) of a multipage item, de-duplicating multi-page content in
 * admin lists.
 *
 * @ViewsFilter("mukurtu_node_is_multipage_item")
 */
class NodeIsMultipageItemFilter extends NodeBooleanExistsFilterBase {

  protected function getSubquery(): SelectInterface {
    // Returns NIDs of non-first pages (delta > 0). Used with NOT IN so that
    // non-multipage nodes are preserved alongside first-page nodes.
    return $this->database->select('multipage_item__field_pages', 'mip')
      ->fields('mip', ['field_pages_target_id'])
      ->condition('mip.deleted', 0)
      ->condition('mip.delta', 0, '>');
  }

  public function query(): void {
    $value = is_array($this->value) ? reset($this->value) : $this->value;
    if (!isset($value) || $value === self::VALUE_ANY || $value === '') {
      return;
    }
    $this->ensureMyTable();
    // Exclude non-first pages, preserving nodes not in any multipage item.
    $this->query->addWhere($this->options['group'], "$this->tableAlias.nid", $this->getSubquery(), 'NOT IN');
  }

  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Value'),
      '#options' => [
        self::VALUE_YES => $this->t('First page only'),
      ],
      '#empty_option' => $this->t('- All pages -'),
      '#empty_value' => self::VALUE_ANY,
      '#default_value' => $this->value ?? self::VALUE_ANY,
    ];
  }

  public function adminSummary() {
    if (!isset($this->value) || $this->value === self::VALUE_ANY || $this->value === '') {
      return $this->t('all pages');
    }
    return $this->t('first page only');
  }

}
