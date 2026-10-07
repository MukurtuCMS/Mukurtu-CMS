<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Links a status row to its item's translations overview.
 *
 * Shown only to people who can reach that page.
 */
#[ViewsField('mukurtu_translation_status_translate')]
class TranslationStatusTranslateLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init($view, $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->additional_fields['entity_type'] = 'entity_type';
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity_id = $this->getValue($values);
    $entity_type_id = $this->getValue($values, 'entity_type');
    if (!$entity_id || !$entity_type_id) {
      return '';
    }
    $route = "entity.$entity_type_id.content_translation_overview";
    $url = Url::fromRoute($route, [$entity_type_id => $entity_id]);
    $access = $url->access(NULL, TRUE);
    $build = $access->isAllowed() ? Link::fromTextAndUrl($this->t('Translate'), $url)->toRenderable() : [];
    $build['#cache']['contexts'] = $access->getCacheContexts();
    $build['#cache']['tags'] = $access->getCacheTags();
    return $build;
  }

}
