<?php

namespace Drupal\mukurtu_core\Plugin\views\filter;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Filters content nodes by whether they are a community record.
 *
 * A node is a community record when it has a value in
 * node__field_mukurtu_original_record (i.e. it was created as a community
 * record version of an original digital heritage item).
 *
 * @ViewsFilter("mukurtu_node_is_community_record")
 */
class NodeIsCommunityRecordFilter extends NodeBooleanExistsFilterBase {

  protected function getSubquery(): SelectInterface {
    return $this->database->select('node__field_mukurtu_original_record', 'ocr')
      ->fields('ocr', ['entity_id'])
      ->condition('ocr.deleted', 0);
  }

  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Value'),
      '#options' => [
        self::VALUE_NO  => $this->t('Original record'),
        self::VALUE_YES => $this->t('Community record'),
      ],
      '#empty_option' => $this->t('- Any -'),
      '#empty_value' => self::VALUE_ANY,
      '#default_value' => $this->value ?? self::VALUE_ANY,
    ];
  }

  public function adminSummary() {
    if (!isset($this->value) || $this->value === self::VALUE_ANY || $this->value === '') {
      return $this->t('unrestricted');
    }
    return $this->value === self::VALUE_YES ? $this->t('Community record') : $this->t('Original record');
  }


}
