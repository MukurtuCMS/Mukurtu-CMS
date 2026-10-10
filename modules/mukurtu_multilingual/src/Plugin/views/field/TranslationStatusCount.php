<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Shows "3 of 5" for translated fields, and sorts by the share translated.
 */
#[ViewsField('mukurtu_translation_status_count')]
class TranslationStatusCount extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init($view, $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->additional_fields['total_count'] = 'total_count';
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    $this->ensureMyTable();
    // Sort by the share of fields translated, so "1 of 2" sits above
    // "1 of 10". An item with nothing to translate counts as fully done.
    // Multiply by 1.0 first: PostgreSQL and SQLite divide integers exactly.
    $formula = "COALESCE($this->tableAlias.translated_count * 1.0 / NULLIF($this->tableAlias.total_count, 0), 1)";
    $alias = $this->query->addField(NULL, $formula, $this->tableAlias . '_translated_share');
    $this->query->addOrderBy(NULL, NULL, $order, $alias);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $translated = $this->getValue($values);
    if ($translated === NULL) {
      return '';
    }
    return $this->t('@translated of @total', [
      '@translated' => (int) $translated,
      '@total' => (int) $this->getValue($values, 'total_count'),
    ]);
  }

}
