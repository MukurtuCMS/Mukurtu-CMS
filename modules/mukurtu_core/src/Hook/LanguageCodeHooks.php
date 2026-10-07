<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the language vocabulary's language code.
 *
 * Dictionary output uses the code as an HTML lang attribute (#2328), so it
 * has to be shaped like a BCP 47 tag.
 */
final class LanguageCodeHooks {

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, $bundle): void {
    if ($entity_type->id() === 'taxonomy_term' && $bundle === 'language' && isset($fields['field_language_code'])) {
      $fields['field_language_code']->addPropertyConstraints('value', ['MukurtuLanguageTag' => []]);
    }
  }

}
