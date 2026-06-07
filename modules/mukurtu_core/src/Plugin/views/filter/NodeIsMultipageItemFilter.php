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
    return $this->database->select('multipage_item__field_pages', 'mip')
      ->fields('mip', ['field_pages_target_id'])
      ->condition('mip.deleted', 0)
      ->condition('mip.delta', 0);
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
