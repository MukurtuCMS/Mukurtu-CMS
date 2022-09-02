<?php

namespace Drupal\mukurtu_core\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * TermStatusItemList class to generate a computed field.
 */
class CitationItemList extends FieldItemList
{
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue()
  {
    $config = \Drupal::config('mukurtu.settings');
    $entity = $this->getEntity();
    $targetBundle = $entity->bundle();

    $templates = [];

    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

    // Gather all bundles dynamically.
    foreach ($bundleInfo as $bundleName => $bundleValue) {
      $templates[$bundleName] = $config->get($bundleName);
    }

    $targetTemplate = $templates[$targetBundle];

    $tokenService = \Drupal::service("token");

    // Token::replace() requires a keyed array of token types.
    // Some tokens are not replaced by default.
    $data = [
      'node' => $entity,
      'language' => $entity->language(),
      'random' => $entity,
    ];

    // Clear tokens that do not have a replacement value.
    $options = [
      'clear' => TRUE
    ];

    $citation = $tokenService->replace($targetTemplate, $data, $options);

    $this->list[0] = $this->createItem(0, $citation);
  }
}
