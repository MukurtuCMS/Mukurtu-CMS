<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests langcode-mapping imports on a site without content_translation.
 *
 * mukurtu_import.info.yml has declared content_translation a dependency since
 * 4.0.0 (#2081), but Drupal only resolves info.yml dependencies when a module
 * is installed, so every site that had mukurtu_import enabled before 4.0.0
 * updated into a state where the module is declared and absent. 38 of the
 * shipped import templates map a language column, which is what makes
 * MukurtuImportStrategy::isTranslationImport() consult
 * content_translation.manager, so on those sites most imports died with a
 * ServiceNotFoundException before processing a row.
 *
 * MukurtuImportTestBase deliberately does not enable content_translation, and
 * KernelTestBase does not enforce info.yml dependencies, so this class runs
 * against exactly the affected sites' module set. ImportTranslationTest pins
 * the other direction, where content_translation is installed and the feature
 * must still engage.
 */
#[Group('mukurtu_import')]
class ImportWithoutContentTranslationTest extends MukurtuImportTestBase {

  /**
   * The node the imports below update.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->assertFalse(
      \Drupal::moduleHandler()->moduleExists('content_translation'),
      'This class only tests anything if content_translation is absent.',
    );

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
   * Builds a strategy mapping a langcode column, as the shipped ones do.
   */
  protected function langcodeMappingStrategy(): MukurtuImportStrategy {
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping([
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'langcode', 'source' => 'langcode'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'sharing'],
    ]);
    return $strategy;
  }

  /**
   * Building the migration definition must not require the module.
   *
   * Without the guard in isTranslationImport() this is the reported
   * ServiceNotFoundException, raised from getOverwriteProperties() before
   * toDefinition() ever reaches the destination array.
   */
  public function testDefinitionBuildsWithoutContentTranslation(): void {
    $import_file = $this->createCsvFile([
      ['nid', 'title', 'langcode', 'sharing'],
      [$this->node->id(), 'After Import', 'en', 'all'],
    ]);

    $definition = $this->langcodeMappingStrategy()->toDefinition($import_file);

    // Translation targeting needs content_translation to have marked the
    // bundle translatable, so it cannot be on here.
    $this->assertArrayNotHasKey('translations', $definition['destination']);
  }

  /**
   * Non-translatable fields stay writable when the module is absent.
   *
   * getOverwriteProperties() drops every non-translatable field once a
   * strategy is a translation import. That exclusion must stay disengaged
   * here, or these sites would silently lose the ability to update
   * field_cultural_protocols on existing content.
   */
  public function testNonTranslatableFieldsStayWritable(): void {
    $import_file = $this->createCsvFile([
      ['nid', 'title', 'langcode', 'sharing'],
      [$this->node->id(), 'After Import', 'en', 'all'],
    ]);

    $definition = $this->langcodeMappingStrategy()->toDefinition($import_file);

    $this->assertContains('field_cultural_protocols', $definition['destination']['overwrite_properties']);
  }

  /**
   * The import the bug report describes runs to completion.
   */
  public function testLangcodeMappingImportCompletes(): void {
    $import_file = $this->createCsvFile([
      ['nid', 'title', 'langcode', 'sharing'],
      [$this->node->id(), 'After Import', 'en', 'all'],
    ]);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
      ['target' => 'langcode', 'source' => 'langcode'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'sharing'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $this->assertEquals('After Import', $node->getTitle());

    // Not a translation import, so the mapped non-translatable field was
    // written as it always has been. The node started out as 'any', so this
    // only holds if the row actually reached field_cultural_protocols.
    $this->assertEquals('all', $node->getSharingSetting());
  }

}
