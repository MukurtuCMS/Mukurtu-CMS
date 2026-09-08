<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that CitationItemList cleans up artifacts left by empty tokens.
 *
 * @see \Drupal\mukurtu_core\Plugin\Field\CitationItemList
 */
class CitationItemListTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'filter',
    'token',
    'geofield',
    'leaflet',
    'mukurtu_core',
  ];

  /**
   * The citation template used by the tests.
   */
  const TEMPLATE = '[node:title], [node:field_author]. [node:field_year].';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system']);

    NodeType::create(['type' => 'citation_test_content', 'name' => 'Citation Test Content'])->save();

    foreach (['field_author', 'field_year'] as $fieldName) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'bundle' => 'citation_test_content',
        'label' => $fieldName,
      ])->save();
    }

    \Drupal::configFactory()->getEditable('mukurtu.settings')
      ->set('citation_templates.citation_test_content', self::TEMPLATE)
      ->save();
  }

  /**
   * An empty field's surrounding separator punctuation is dropped.
   */
  public function testEmptyFieldDoesNotLeaveStrayPunctuation() {
    $node = Node::create([
      'type' => 'citation_test_content',
      'title' => 'Test Node',
      'field_year' => '2020',
    ]);
    $node->save();

    $this->assertEquals('Test Node. 2020.', $node->get('field_citation')->value);
  }

  /**
   * A fully populated template renders unaffected by the cleanup pass.
   */
  public function testFullyPopulatedTemplateIsUnaffected() {
    $node = Node::create([
      'type' => 'citation_test_content',
      'title' => 'Test Node',
      'field_author' => 'Jane Doe',
      'field_year' => '2020',
    ]);
    $node->save();

    $this->assertEquals('Test Node, Jane Doe. 2020.', $node->get('field_citation')->value);
  }

  /**
   * All referenced fields empty collapses to just the title.
   */
  public function testAllFieldsEmptyLeavesOnlyLiteralText() {
    $node = Node::create([
      'type' => 'citation_test_content',
      'title' => 'Test Node',
    ]);
    $node->save();

    $this->assertEquals('Test Node.', $node->get('field_citation')->value);
  }

}
