<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies the shipped 'all fields' import templates stay in sync with the
 * headers mukurtu_export actually writes for the same bundle.
 *
 * The quick-path import wizard uses these templates' static mapping as-is
 * (see ImportFileSummaryForm), so a source label that doesn't match a real
 * export header, or a column that's silently missing from the mapping
 * altogether, makes the "X of Y fields mapped" count look incomplete even
 * though the import itself isn't broken. This test locks in the header
 * strings and the presence/absence of the langcode/default_langcode/
 * field_multipage_page_of columns so a future change to either module can't
 * silently reintroduce the mismatch fixed here.
 *
 * @see \Drupal\mukurtu_import\Form\ImportFileSummaryForm::getMappedFieldsMessage()
 * @see \Drupal\mukurtu_export\Entity\CsvExporter
 */
class ImportTemplateExportConsistencyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Maps each shipped import template id to its entity_type__bundle key in
   * the csv_exporter's entity_fields_export_list.
   */
  private const TEMPLATE_BUNDLES = [
    'audio_all_fields' => 'media__audio',
    'biography_section_all_fields' => 'paragraph__formatted_text_with_title',
    'collection_all_fields' => 'node__collection',
    'community_all_fields' => 'community__community',
    'cultural_protocol_all_fields' => 'protocol__protocol',
    'dictionary_word_all_fields' => 'node__dictionary_word',
    'digital_heritage_item_all_fields' => 'node__digital_heritage',
    'document_all_fields' => 'media__document',
    'external_embed_all_fields' => 'media__external_embed',
    'image_all_fields' => 'media__image',
    'indigenous_knowledge_keepers_all_fields' => 'paragraph__indigenous_knowledge_keepers',
    'multipage_item_all_fields' => 'multipage_item__multipage_item',
    'person_record_all_fields' => 'node__person',
    'place_record_all_fields' => 'node__place',
    'related_person_all_fields' => 'paragraph__related_person',
    'remote_video_all_fields' => 'media__remote_video',
    'sample_sentence_all_fields' => 'paragraph__sample_sentence',
    'soundcloud_all_fields' => 'media__soundcloud',
    'taxonomy_category_all_fields' => 'taxonomy_term__category',
    'taxonomy_community_type_all_fields' => 'taxonomy_term__community_type',
    'taxonomy_contributor_all_fields' => 'taxonomy_term__contributor',
    'taxonomy_creator_all_fields' => 'taxonomy_term__creator',
    'taxonomy_format_all_fields' => 'taxonomy_term__format',
    'taxonomy_interpersonal_relationship_all_fields' => 'taxonomy_term__interpersonal_relationship',
    'taxonomy_keywords_all_fields' => 'taxonomy_term__keywords',
    'taxonomy_language_all_fields' => 'taxonomy_term__language',
    'taxonomy_location_all_fields' => 'taxonomy_term__location',
    'taxonomy_media_tag_all_fields' => 'taxonomy_term__media_tag',
    'taxonomy_people_all_fields' => 'taxonomy_term__people',
    'taxonomy_place_type_all_fields' => 'taxonomy_term__place_type',
    'taxonomy_publisher_all_fields' => 'taxonomy_term__publisher',
    'taxonomy_subject_all_fields' => 'taxonomy_term__subject',
    'taxonomy_type_all_fields' => 'taxonomy_term__type',
    'taxonomy_word_type_all_fields' => 'taxonomy_term__word_type',
    'text_section_all_fields' => 'paragraph__text_section_with_title',
    'video_all_fields' => 'media__video',
    'word_entry_all_fields' => 'paragraph__dictionary_word_entry',
    'word_list_all_fields' => 'node__word_list',
  ];

  /**
   * The entity_fields_export_list section for every bundle above, keyed the
   * same way, as shipped in mukurtu_export's default exporter config.
   */
  private array $exportFieldsList;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_export');
    $exporter_config = (new FileStorage($module_path . '/config/install'))
      ->read('mukurtu_export.csv_exporter.default_local_metadata_only');
    $this->exportFieldsList = $exporter_config['entity_fields_export_list'];
  }

  /**
   * Reads a shipped mukurtu_import_strategy template's mapping array.
   */
  private function getTemplateMapping(string $template_id): array {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    $config = (new FileStorage($module_path . '/config/install'))
      ->read("mukurtu_import.mukurtu_import_strategy.{$template_id}");
    return $config['mapping'];
  }

  /**
   * Every template's langcode mapping source must equal the real export
   * header for that bundle, not a "(langcode)"-suffixed dropdown label.
   */
  public function testLangcodeSourceMatchesExportHeader(): void {
    foreach (self::TEMPLATE_BUNDLES as $template_id => $bundle_key) {
      $expected = $this->exportFieldsList[$bundle_key]['langcode'] ?? NULL;
      $this->assertNotNull($expected, "No langcode export header defined for {$bundle_key}.");

      $mapping = $this->getTemplateMapping($template_id);
      $langcode_sources = array_column(array_filter($mapping, fn (array $m) => $m['target'] === 'langcode'), 'source');

      $this->assertCount(1, $langcode_sources, "{$template_id} should have exactly one langcode mapping entry.");
      $this->assertSame($expected, $langcode_sources[0], "{$template_id}'s langcode source should match the real export header for {$bundle_key}.");
    }
  }

  /**
   * Every template that maps one of these base targets must use the same
   * source label the bundle's real export header uses for that column -
   * not a stale/copied field-dropdown label that will never match a real
   * file (see https://github.com/MukurtuCMS/Mukurtu-CMS/pull/2035#issuecomment-5398304518
   * and the "promote"/"sticky"/"status" follow-up on the same PR).
   *
   * Unlike langcode, these have no core fallback default (created), or are
   * silently dropped from an update's applied changes and from the "X of Y
   * mapped" count (status/promote/sticky), when a template maps them from a
   * label the exporter never actually writes:
   * MukurtuImportStrategy::getProcess() silently drops the mapping when the
   * uploaded file has no matching header. For "created" on a brand new
   * entity that means a NOT NULL constraint violation; for the others it
   * means re-importing an unedited exported CSV - or editing one of these
   * columns before re-importing - silently has no effect.
   */
  public function testBaseFieldSourceMatchesExportHeaderWhenMapped(): void {
    // Paragraph/taxonomy_term entities have no base field for any of these,
    // so MukurtuImportStrategy::getProcess() harmlessly no-ops any mapping
    // entry a template happens to carry for them - there's no export header
    // to match against in that case.
    $relevant_prefixes = ['node__', 'media__', 'multipage_item__', 'community__', 'protocol__'];

    foreach (['created', 'status', 'promote', 'sticky'] as $target) {
      foreach (self::TEMPLATE_BUNDLES as $template_id => $bundle_key) {
        $is_relevant = array_reduce($relevant_prefixes, fn ($carry, $prefix) => $carry || str_starts_with($bundle_key, $prefix), FALSE);
        if (!$is_relevant) {
          continue;
        }

        $mapping = $this->getTemplateMapping($template_id);
        $sources = array_column(array_filter($mapping, fn (array $m) => $m['target'] === $target), 'source');

        if (empty($sources)) {
          continue;
        }

        $expected = $this->exportFieldsList[$bundle_key][$target] ?? NULL;
        $this->assertNotNull($expected, "{$template_id} maps a '{$target}' target but {$bundle_key} has no real export header for it.");
        $this->assertCount(1, $sources, "{$template_id} should have exactly one {$target} mapping entry.");
        $this->assertSame($expected, $sources[0], "{$template_id}'s {$target} source should match the real export header for {$bundle_key}.");
      }
    }
  }

  /**
   * A template must carry an explicit "Ignore" mapping for default_langcode
   * ("Default translation") exactly when its bundle exports that column, and
   * must not carry one when the bundle doesn't export it.
   */
  public function testDefaultTranslationIgnoredWhenExported(): void {
    foreach (self::TEMPLATE_BUNDLES as $template_id => $bundle_key) {
      $exports_default_langcode = isset($this->exportFieldsList[$bundle_key]['default_langcode']);
      $mapping = $this->getTemplateMapping($template_id);
      $has_ignore_entry = (bool) array_filter($mapping, fn (array $m) => ($m['source'] ?? NULL) === 'Default translation' && $m['target'] === '-1');

      if ($exports_default_langcode) {
        $this->assertTrue($has_ignore_entry, "{$template_id} exports 'Default translation' but has no ignore mapping for it.");
      }
      else {
        $this->assertFalse($has_ignore_entry, "{$template_id} has a 'Default translation' ignore mapping but its bundle doesn't export that column.");
      }
    }
  }

  /**
   * Same check as above, for field_multipage_page_of ("Multipage parent").
   */
  public function testMultipageParentIgnoredWhenExported(): void {
    foreach (self::TEMPLATE_BUNDLES as $template_id => $bundle_key) {
      $exports_multipage_parent = isset($this->exportFieldsList[$bundle_key]['field_multipage_page_of']);
      $mapping = $this->getTemplateMapping($template_id);
      $has_ignore_entry = (bool) array_filter($mapping, fn (array $m) => ($m['source'] ?? NULL) === 'Multipage parent' && $m['target'] === '-1');

      if ($exports_multipage_parent) {
        $this->assertTrue($has_ignore_entry, "{$template_id} exports 'Multipage parent' but has no ignore mapping for it.");
      }
      else {
        $this->assertFalse($has_ignore_entry, "{$template_id} has a 'Multipage parent' ignore mapping but its bundle doesn't export that column.");
      }
    }
  }

  /**
   * Every column a bundle actually exports must have a mapping entry whose
   * source is that column's real header, pointed at the matching target
   * field or explicitly ignored ('-1').
   *
   * This is deliberately exhaustive (every real CSV header in the export
   * list, for every template) rather than special-cased to the columns
   * previously found broken (langcode, default_langcode,
   * field_multipage_page_of): those three were exactly the kind of gap this
   * test is meant to catch automatically instead of relying on a human to
   * spot the next one. It does not object to a template mapping some *other*
   * label the exporter doesn't emit (e.g. "Draft", "Published", "UUID") -
   * several templates intentionally support importing fields beyond the
   * default export set, and that's fine.
   *
   * A few bundles (external_embed, remote_video, soundcloud) list the same
   * header under two different target keys - a base "thumbnail" field and
   * an explicit "field_thumbnail" field that both happen to carry the same
   * export label. Since a single real CSV column can only be imported into
   * one of them, the check is grouped by header label: it's satisfied if
   * *any* of the targets sharing that label is mapped correctly, not each
   * one individually.
   */
  public function testAllExportedColumnsAreMappedCorrectly(): void {
    foreach (self::TEMPLATE_BUNDLES as $template_id => $bundle_key) {
      $mapping = $this->getTemplateMapping($template_id);

      $targets_by_source = [];
      foreach ($mapping as $entry) {
        $targets_by_source[$entry['source']][] = $entry['target'];
      }

      $targets_by_label = [];
      foreach ($this->exportFieldsList[$bundle_key] as $target => $label) {
        $targets_by_label[$label][] = $target;
      }

      foreach ($targets_by_label as $label => $valid_targets) {
        $entries = $targets_by_source[$label] ?? [];
        $this->assertNotEmpty($entries, "{$template_id}: exported column '{$label}' has no mapping entry at all.");
        $this->assertTrue(
          (bool) array_intersect($valid_targets, $entries) || in_array('-1', $entries, TRUE),
          "{$template_id}: exported column '{$label}' should map to one of [" . implode(', ', $valid_targets) . "] or be explicitly ignored ('-1'), but maps to: " . implode(', ', $entries)
        );
      }
    }
  }

  /**
   * A field whose only supported dropdown targets are sub-properties (e.g.
   * "field_x/target_id", "field_x/alt") can never be mapped by its bare
   * field name - ImportFormTrait::buildTargetOptions() never offers that as
   * a selectable option, so opening and saving Customize Settings silently
   * downgrades any such mapping to "Ignore". These are the specific bare
   * mappings found broken this way; a template must map that column to
   * '-1' outright rather than to the unusable bare field name.
   *
   * @see \Drupal\mukurtu_import\Form\ImportFormTrait::buildTargetOptions()
   */
  public function testBareSubpropertyFieldsAreExplicitlyIgnored(): void {
    $known_bare_fields = [
      'collection_all_fields' => ['Image' => 'field_collection_image'],
      'community_all_fields' => [
        'Banner Image' => 'field_banner_image',
        'Thumbnail Image' => 'field_thumbnail_image',
      ],
      'cultural_protocol_all_fields' => [
        'Banner Image' => 'field_banner_image',
        'Thumbnail Image' => 'field_thumbnail_image',
      ],
      'dictionary_word_all_fields' => ['Thumbnail' => 'field_thumbnail'],
      'sample_sentence_all_fields' => ['Recording' => 'field_sentence_recording'],
      'word_list_all_fields' => ['Image' => 'field_word_list_image'],
    ];

    foreach ($known_bare_fields as $template_id => $sources) {
      $mapping = $this->getTemplateMapping($template_id);
      foreach ($sources as $source => $unusable_bare_target) {
        $matches = array_filter($mapping, fn (array $m) => ($m['source'] ?? NULL) === $source);
        $this->assertNotEmpty($matches, "{$template_id}: expected a mapping entry for '{$source}'.");
        foreach ($matches as $m) {
          $this->assertNotSame($unusable_bare_target, $m['target'], "{$template_id}: '{$source}' must not map to the bare field '{$unusable_bare_target}' - that target is never offered by Customize Settings, so it silently becomes 'Ignore' the moment the config is resaved through that form.");
        }
      }
    }
  }

}
