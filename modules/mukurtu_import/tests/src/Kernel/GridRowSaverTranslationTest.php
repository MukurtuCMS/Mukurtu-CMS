<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_import\BulkEdit\GridRowSaver;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests grid rows for translations.
 *
 * A translation row edits its own language. Fields shared by every
 * translation change only from the original-language row.
 */
#[Group('mukurtu_import')]
class GridRowSaverTranslationTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  /**
   * An item with a Spanish translation.
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'protocol_aware_content', TRUE);

    // Shared by every translation.
    FieldStorageConfig::create(['field_name' => 'field_identifier', 'entity_type' => 'node', 'type' => 'string'])->save();
    FieldConfig::create([
      'field_name' => 'field_identifier',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Identifier',
      'translatable' => FALSE,
    ])->save();

    $node = Node::create([
      'title' => 'Canoe',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_identifier' => 'MK-1',
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $node->addTranslation('es', ['title' => 'Canoa'] + $node->toArray())->save();
    $this->node = $node;
  }

  /**
   * Reloads the item.
   */
  protected function reload(): NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$this->node->id()]);
    return $storage->load($this->node->id());
  }

  /**
   * A translation row changes its language only, and never shared fields.
   */
  public function testTranslationRow(): void {
    $errors = (new GridRowSaver())->save($this->reload()->getTranslation('es'), [
      'title' => 'Canoa tallada',
      'field_identifier' => 'MK-2',
    ], 'Grid log');

    $this->assertSame([], $errors);
    $node = $this->reload();
    $this->assertSame('Canoa tallada', $node->getTranslation('es')->label());
    $this->assertSame('Canoe', $node->label());
    $this->assertSame('MK-1', $node->get('field_identifier')->value);
  }

  /**
   * The original-language row changes shared fields for every translation.
   */
  public function testOriginalRow(): void {
    $this->assertSame([], (new GridRowSaver())->save($this->reload(), ['field_identifier' => 'MK-2'], 'Grid log'));

    $node = $this->reload();
    $this->assertSame('MK-2', $node->get('field_identifier')->value);
    $this->assertSame('MK-2', $node->getTranslation('es')->get('field_identifier')->value);
    $this->assertSame('Canoa', $node->getTranslation('es')->label());
  }

}
