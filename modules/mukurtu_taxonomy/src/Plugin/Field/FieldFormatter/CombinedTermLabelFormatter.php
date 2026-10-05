<?php

namespace Drupal\mukurtu_taxonomy\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Taxonomy term label formatter that shows identical names only once.
 *
 * Person and place records reference terms from several vocabularies (for
 * example creator, contributor and people), so the same name can be
 * referenced once per vocabulary. This formatter keeps the first term with a
 * given name and drops the rest, comparing the translated labels the viewer
 * actually sees.
 */
#[FieldFormatter(
  id: 'mukurtu_combined_term_label',
  label: new TranslatableMarkup('Label (combine identical names)'),
  description: new TranslatableMarkup('Shows terms with identical names once, linked to the first term.'),
  field_types: ['entity_reference'],
)]
class CombinedTermLabelFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    $kept = [];
    foreach ($elements as $delta => $element) {
      $key = static::normalizeLabel((string) $element['#entity']->label());
      if (!isset($kept[$key])) {
        $kept[$key] = $delta;
        continue;
      }

      // Keep the dropped term's cache tags so editing any of the combined
      // terms still invalidates the rendered field.
      $first = $kept[$key];
      CacheableMetadata::createFromRenderArray($elements[$first])
        ->merge(CacheableMetadata::createFromRenderArray($element))
        ->applyTo($elements[$first]);
      unset($elements[$delta]);
    }

    return $elements;
  }

  /**
   * Normalizes a term label for comparison.
   *
   * Trims, collapses runs of whitespace and lowercases, matching the label
   * normalization used by the import label lookups.
   *
   * @param string $label
   *   The term label.
   *
   * @return string
   *   The normalized label.
   */
  public static function normalizeLabel(string $label): string {
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $label)));
  }

}
