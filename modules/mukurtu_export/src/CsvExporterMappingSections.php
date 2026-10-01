<?php

namespace Drupal\mukurtu_export;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_export\Entity\CsvExporter;

/**
 * Groups CSV exporter field mappings into sections.
 *
 * Each section is edited on its own page so a single save never posts the
 * field mapping inputs for every bundle on the site, which would exceed
 * PHP's max_input_vars.
 */
class CsvExporterMappingSections {

  use StringTranslationTrait;

  /**
   * Bundles listed after the others. NULL means all bundles.
   */
  protected const SECONDARY_BUNDLES = [
    'node' => ['article', 'page', 'landing_page'],
    'paragraph' => ['footer_logo', 'footer_social_link'],
    'file' => NULL,
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
  ) {}

  /**
   * Returns the field mapping sections.
   *
   * @return array
   *   Sections keyed by section key. Each section has a 'label' and an
   *   ordered list of 'items', each with 'type', 'bundle', 'label' and
   *   'wrap' (whether the bundle's table goes in its own details element).
   *   Custom groups combine a content type with its related paragraphs;
   *   every other bundle has a section of its own.
   */
  public function getSections(CsvExporter $exporter): array {
    $all_bundle_info = $this->entityTypeBundleInfo->getAllBundleInfo();
    $sections = [];
    $handled = [];

    // Custom groups interleave content types with their related paragraphs.
    // The first item's fields are placed directly in the section; subsequent
    // items each get a collapsed sub-details.
    $groups = [
      'digital_heritage' => [
        'label' => $this->t('Digital Heritage'),
        'items' => [
          ['node', 'digital_heritage'],
          ['paragraph', 'indigenous_knowledge_keepers'],
        ],
      ],
      'dictionary_word' => [
        'label' => $this->t('Dictionary Word'),
        'items' => [
          ['node', 'dictionary_word'],
          ['paragraph', 'dictionary_word_entry'],
          ['paragraph', 'sample_sentence'],
        ],
      ],
      'person' => [
        'label' => $this->t('Person'),
        'items' => [
          ['node', 'person'],
          ['paragraph', 'formatted_text_with_title'],
          ['paragraph', 'related_person'],
        ],
      ],
      'place' => [
        'label' => $this->t('Place'),
        'items' => [
          ['node', 'place'],
          ['paragraph', 'text_section_with_title'],
        ],
      ],
      'collection' => [
        'label' => $this->t('Collection'),
        'items' => [
          ['node', 'collection'],
        ],
      ],
      'word_list' => [
        'label' => $this->t('Word List'),
        'items' => [
          ['node', 'word_list'],
        ],
      ],
    ];

    foreach ($groups as $group_key => $group) {
      $items = [];
      foreach ($group['items'] as [$type, $bundle]) {
        if (!isset($all_bundle_info[$type][$bundle])) {
          continue;
        }
        $items[] = $this->item($type, $bundle, $all_bundle_info[$type][$bundle], !empty($items));
        $handled["{$type}__{$bundle}"] = TRUE;
      }
      if ($items) {
        $sections[$group_key] = ['label' => $group['label'], 'items' => $items];
      }
    }

    // The remaining bundles get a section each, so a section never grows with
    // the number of bundles a site adds (vocabularies, media types, ...). An
    // entity type with a single bundle uses the entity type as its section.
    // Secondary bundles are listed last.
    $secondary = [];
    foreach ($exporter->getSupportedEntityTypes() as $type) {
      $definition = $this->entityTypeManager->getDefinition($type, FALSE);
      if (!$definition) {
        continue;
      }
      $all_bundles = $all_bundle_info[$type] ?? [];
      $single_bundle = count($all_bundles) === 1;
      $secondary_list = array_key_exists($type, self::SECONDARY_BUNDLES)
        ? (self::SECONDARY_BUNDLES[$type] ?? array_keys($all_bundles))
        : [];

      foreach ($all_bundles as $bundle => $bundle_info) {
        if (isset($handled["{$type}__{$bundle}"])) {
          continue;
        }
        $item = $this->item($type, $bundle, $bundle_info, FALSE);
        $section = [
          'label' => $single_bundle
            ? $definition->getLabel()
            : $this->t('@type: @bundle', ['@type' => $definition->getLabel(), '@bundle' => $item['label']]),
          'items' => [$item],
        ];
        $section_key = $single_bundle ? $type : "{$type}__{$bundle}";
        if (in_array($bundle, $secondary_list)) {
          $secondary[$section_key] = $section;
        }
        else {
          $sections[$section_key] = $section;
        }
      }
    }

    $sections += $secondary;

    return $sections;
  }

  /**
   * Returns a single section, or NULL if it doesn't exist.
   */
  public function getSection(CsvExporter $exporter, string $section_key): ?array {
    return $this->getSections($exporter)[$section_key] ?? NULL;
  }

  /**
   * Builds a section item.
   */
  protected function item(string $type, string $bundle, array $bundle_info, bool $wrap): array {
    return [
      'type' => $type,
      'bundle' => $bundle,
      'label' => $bundle_info['label'] ?? $bundle,
      'wrap' => $wrap,
    ];
  }

}
