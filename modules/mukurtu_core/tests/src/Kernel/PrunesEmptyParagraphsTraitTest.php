<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Tests PrunesEmptyParagraphsTrait through a real bundle-class save.
 */
class PrunesEmptyParagraphsTraitTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'file',
    'image',
    'media',
    'text',
    'entity_reference_revisions',
    'paragraphs',
    'mukurtu_core',
    'mukurtu_core_paragraph_prune_test',
  ];

  /**
   * The test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    $this->testUser = $this->createUser();

    ParagraphsType::create(['id' => 'test_section', 'label' => 'Test Section'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'bundle' => 'test_section',
      'label' => 'Text',
    ])->save();

    NodeType::create(['type' => 'prune_test_content', 'name' => 'Prune Test Content'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'cardinality' => -1,
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'bundle' => 'prune_test_content',
      'label' => 'Sections',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'target_bundles' => ['test_section' => 'test_section'],
        ],
      ],
    ])->save();
  }

  /**
   * A brand-new, entirely-blank paragraph is dropped and never saved.
   */
  public function testEmptyNewParagraphIsPruned() {
    $paragraph = Paragraph::create(['type' => 'test_section']);

    $node = Node::create([
      'type' => 'prune_test_content',
      'title' => 'Test',
      'uid' => $this->testUser->id(),
      'field_sections' => [$paragraph],
    ]);
    $node->save();

    $this->assertTrue($node->get('field_sections')->isEmpty());
    $this->assertNull($paragraph->id(), 'The empty paragraph was never persisted.');
  }

  /**
   * A new paragraph with real content survives the save.
   */
  public function testFilledNewParagraphIsKept() {
    $paragraph = Paragraph::create(['type' => 'test_section', 'field_text' => 'Hello']);

    $node = Node::create([
      'type' => 'prune_test_content',
      'title' => 'Test',
      'uid' => $this->testUser->id(),
      'field_sections' => [$paragraph],
    ]);
    $node->save();

    $this->assertCount(1, $node->get('field_sections')->getValue());
    $this->assertNotNull($paragraph->id(), 'The filled paragraph was persisted.');
    $this->assertEquals($paragraph->id(), $node->get('field_sections')->target_id);
  }

  /**
   * In a multi-value field, only the empty item is dropped.
   */
  public function testMixedValuesOnlyPrunesEmptyOnes() {
    $empty = Paragraph::create(['type' => 'test_section']);
    $filled = Paragraph::create(['type' => 'test_section', 'field_text' => 'Hello']);

    $node = Node::create([
      'type' => 'prune_test_content',
      'title' => 'Test',
      'uid' => $this->testUser->id(),
      'field_sections' => [$empty, $filled],
    ]);
    $node->save();

    $this->assertCount(1, $node->get('field_sections')->getValue());
    $this->assertNull($empty->id());
    $this->assertNotNull($filled->id());
    $this->assertEquals($filled->id(), $node->get('field_sections')->target_id);
  }

  /**
   * An already-saved paragraph that's empty is left alone on resave.
   */
  public function testExistingEmptyParagraphIsNotPruned() {
    $paragraph = Paragraph::create(['type' => 'test_section']);
    $paragraph->save();

    $node = Node::create([
      'type' => 'prune_test_content',
      'title' => 'Test',
      'uid' => $this->testUser->id(),
      'field_sections' => [
        ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()],
      ],
    ]);
    $node->save();

    $this->assertCount(1, $node->get('field_sections')->getValue());
    $this->assertEquals($paragraph->id(), $node->get('field_sections')->target_id);
  }

}
