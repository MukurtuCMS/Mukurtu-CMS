<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\mukurtu_export\ExportItemIdentity;
use Drupal\mukurtu_export\Plugin\MukurtuExporter\CSV;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests that a translated export row pulls referenced-entity names from
 * the same translation, not their own default language (#1260 Phase 5).
 *
 * @group mukurtu_export
 */
class CsvExportTranslationTest extends CsvExportFieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  protected $node;
  protected $term;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // 'en' isn't a real ConfigurableLanguage entity until explicitly
    // created, matching ProtocolLabelTranslationTest's setup.
    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'protocol_aware_content', TRUE);

    $vocabulary = Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords']);
    $vocabulary->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', 'keywords', TRUE);

    FieldStorageConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Keywords',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['keywords' => 'keywords']],
      ],
    ])->save();

    $term = Term::create([
      'name' => 'Weaving',
      'vid' => 'keywords',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $term->save();
    $term->addTranslation('es', ['name' => 'Tejido'])->save();
    $this->term = $term;

    $node = Node::create([
      'title' => 'About Weaving',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_keywords' => [['target_id' => $this->term->id()]],
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $node->addTranslation('es', ['title' => 'Sobre el Tejido'])->save();
    $this->node = $node;
  }

  /**
   * The event reports the row's langcode only for a non-original
   * translation, never for the entity's own original language.
   */
  public function testEventLangcode(): void {
    $originalEvent = new EntityFieldExportEvent('csv', $this->node, 'title', $this->context);
    $this->assertNull($originalEvent->getLangcode());

    $translatedEvent = new EntityFieldExportEvent('csv', $this->node->getTranslation('es'), 'title', $this->context);
    $this->assertSame('es', $translatedEvent->getLangcode());
  }

  /**
   * A translated row's own field values already come through correctly
   * (FieldItemList is scoped to whichever translation object it's called
   * on) - this just confirms the fixture exercises a real translation.
   */
  public function testRowOwnFieldUsesRequestedTranslation(): void {
    $event = new EntityFieldExportEvent('csv', $this->node->getTranslation('es'), 'title', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertEquals(['Sobre el Tejido'], $event->getValue());
  }

  /**
   * A referenced taxonomy term's name exports in the row's language, not
   * the term's own default language.
   */
  public function testReferencedTermNameUsesRowLanguage(): void {
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'name');
    $this->export_config->save();

    $event = new EntityFieldExportEvent('csv', $this->node->getTranslation('es'), 'field_keywords', $this->context);
    $this->fieldExporter->exportField($event);

    $this->assertEquals(['Tejido'], $event->getValue());
  }

  /**
   * The same lookup for the row's own original-language export still
   * returns the term's own default-language name, unchanged from before.
   */
  public function testReferencedTermNameUsesDefaultLanguageForOriginalRow(): void {
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'name');
    $this->export_config->save();

    $event = new EntityFieldExportEvent('csv', $this->node, 'field_keywords', $this->context);
    $this->fieldExporter->exportField($event);

    $this->assertEquals(['Weaving'], $event->getValue());
  }

  /**
   * A term additionally queued for its own export (entity_shallow/entity
   * settings) is queued under a composite id:langcode key when the row
   * that pulled it in is a translated row, so the queued entity itself
   * also exports in that language.
   */
  public function testReferencedEntityQueuedInRowLanguage(): void {
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'entity');
    $this->export_config->save();

    $event = new EntityFieldExportEvent('csv', $this->node->getTranslation('es'), 'field_keywords', $this->context);
    $this->fieldExporter->exportField($event);

    $expectedKey = ExportItemIdentity::encode($this->term->id(), 'es');
    $this->assertArrayHasKey($expectedKey, $event->context['results']['entities']['taxonomy_term']);
  }

  /**
   * CSV::batchExport() resolves a composite "$id:$langcode" item key to
   * the requested translation before exporting the row, and falls back to
   * the entity's own language when the requested translation doesn't
   * exist - the mechanism the ad-hoc/VBO and saved-list export paths both
   * funnel into.
   */
  public function testBatchExportResolvesRequestedTranslation(): void {
    $basepath = 'temporary://csv-export-translation-test';
    \Drupal::service('file_system')->prepareDirectory($basepath, FileSystemInterface::CREATE_DIRECTORY);

    $context = [
      'sandbox' => [
        'config' => $this->export_config,
        'batch' => [
          'entity_type_id' => 'node',
          'entities' => [
            ExportItemIdentity::encode($this->node->id(), 'es'),
          ],
        ],
      ],
      'results' => [
        'csv' => ['separator' => ',', 'enclosure' => '"', 'escape' => '\\'],
        'entities' => [
          'node' => [
            ExportItemIdentity::encode($this->node->id(), 'es') => ExportItemIdentity::encode($this->node->id(), 'es'),
          ],
        ],
        'exported_entities' => [],
        'exported_entities_count' => 0,
        'shallow_entity_ids' => [],
        'headers_written' => [],
        'basepath' => $basepath,
        'deliverables' => ['metadata' => [], 'files' => []],
      ],
    ];

    CSV::batchExport($context);

    $key = ExportItemIdentity::encode($this->node->id(), 'es');
    $this->assertArrayHasKey($key, $context['results']['exported_entities']['node']);

    $output = fopen("{$basepath}/Content - Protocol Aware Content.csv", 'r');
    $header = fgetcsv($output);
    $row = fgetcsv($output);
    fclose($output);

    $titleColumn = array_search('Title', $header, TRUE);
    $this->assertNotFalse($titleColumn);
    $this->assertSame('Sobre el Tejido', $row[$titleColumn]);
  }

}
