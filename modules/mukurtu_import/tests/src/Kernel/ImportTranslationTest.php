<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\node\Entity\Node;

/**
 * Tests translation-targeting CSV import (#1260 Phase 5, import track).
 *
 * @group mukurtu_import
 */
class ImportTranslationTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'protocol_aware_content', TRUE);

    $node = Node::create([
      'title' => 'Before Import',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Mapping a column to langcode, on a translatable bundle, adds a
   * translation instead of overwriting the base entity - and doesn't
   * touch field_cultural_protocols on the new translation, since it
   * isn't translatable.
   */
  public function testLangcodeColumnAddsTranslation(): void {
    $data = [
      ['nid', 'title', 'langcode', 'sharing'],
      [$this->node->id(), 'Después de Importar', 'es', 'none'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'langcode', 'source' => 'langcode'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'sharing'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertTrue($node->hasTranslation('es'));
    $this->assertEquals('Después de Importar', $node->getTranslation('es')->getTitle());

    // The base (original-language) entity is untouched - only a
    // translation was added, not an overwrite of the default entity.
    $this->assertEquals('Before Import', $node->getTitle());

    // field_cultural_protocols isn't translatable, so the translation
    // import must not have written it - the sharing setting mapped in
    // this row ('none') is nowhere on the entity.
    $this->assertEquals('any', $node->getSharingSetting());
  }

  /**
   * The same langcode-mapped import on a bundle *without* translation
   * enabled produces a destination definition with no 'translations' key
   * at all - the opt-in gate, verified directly against toDefinition()'s
   * output rather than relying on ambiguous runtime behavior.
   */
  public function testUngatedWithoutTranslationEnabledBundle(): void {
    \Drupal::service('content_translation.manager')->setEnabled('node', 'protocol_aware_content', FALSE);

    $data = [
      ['nid', 'title', 'langcode'],
      [$this->node->id(), 'Ignored', 'es'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'langcode', 'source' => 'langcode'],
    ];

    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('node');
    $import_config->setTargetBundle('protocol_aware_content');
    $import_config->setMapping($mapping);
    $definition = $import_config->toDefinition($import_file);

    $this->assertArrayNotHasKey('translations', $definition['destination']);
  }

  /**
   * The same for a strategy with no langcode column mapped at all -
   * byte-identical to before this feature existed.
   */
  public function testUngatedWithoutLangcodeColumn(): void {
    $data = [
      ['nid', 'title'],
      [$this->node->id(), 'Updated Title'],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ];

    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('node');
    $import_config->setTargetBundle('protocol_aware_content');
    $import_config->setMapping($mapping);
    $definition = $import_config->toDefinition($import_file);

    $this->assertArrayNotHasKey('translations', $definition['destination']);
  }

}
