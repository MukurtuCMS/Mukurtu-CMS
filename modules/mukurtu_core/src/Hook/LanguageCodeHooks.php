<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\taxonomy\TermInterface;

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

  /**
   * Implements hook_ENTITY_TYPE_presave() for taxonomy_term.
   *
   * Text fields keep whatever spaces were typed, and a stray one would end
   * up inside the lang attribute.
   */
  #[Hook('taxonomy_term_presave')]
  public function taxonomyTermPresave(TermInterface $term): void {
    if ($term->bundle() === 'language' && $term->hasField('field_language_code') && !$term->get('field_language_code')->isEmpty()) {
      $code = trim((string) $term->get('field_language_code')->value);
      $term->set('field_language_code', $code === '' ? NULL : $code);
    }
  }

}
