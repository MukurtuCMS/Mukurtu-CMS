<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Plugin\views\field;

use Drupal\mukurtu_multilingual\TranslationStatus\TranslationStatusTracker;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Shows a stored translation status as its text label.
 */
#[ViewsField('mukurtu_translation_status')]
class TranslationStatus extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $labels = TranslationStatusTracker::statusLabels();
    $status = $this->getValue($values);
    return $status === NULL ? '' : ($labels[(int) $status] ?? '');
  }

}
