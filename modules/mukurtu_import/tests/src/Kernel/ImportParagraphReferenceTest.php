<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;


/**
 * Test the import of paragraph references.
 */
class ImportParagraphReferenceTest extends MukurtuImportTestBase {
  protected $node;
  protected $paragraphs;
  protected static $modules = ['entity_reference_revisions', 'paragraphs'];


  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['entity_reference_revisions', 'paragraphs']);

    $paragraph_type = ParagraphsType::create([
      'id' => 'simple_paragraph',
      'label' => 'Simple Paragraph',
      'description' => '',
    ]);
    $paragraph_type->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_paragraph_text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_paragraph_text',
      'entity_type' => 'paragraph',
      'bundle' => 'simple_paragraph',
      'label' => 'Simple Text',
      'settings' => [],
    ]);
    $field->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'paragraph',
      ]
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Paragraphs',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'target_bundles' => [
            'simple_paragraph' => 'simple_paragraph',
          ],
          'negate' => FALSE,
          'target_bundles_drag_drop' => [
            'simple_paragraph' => [
              'enabled' => TRUE,
              'weight' => 0,
            ],
          ],
        ],
      ],
    ])->save();

    foreach (range(0, 2) as $i) {
      $paragraph = Paragraph::create([
        'field_paragraph_text' => "Sample Text $i",
        'type' => 'simple_paragraph',
      ]);
      $paragraph->save();
      $this->paragraphs[$i] = $paragraph;
    }

    $node = Node::create([
      'title' => 'Title',
      'type' => 'protocol_aware_content',
      'field_paragraphs' => [],
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Testing importing paragraph references.
   */
  public function testParagraphReferenceImport() {
    $data = [
      ['nid', 'Paragraphs'],
      [$this->node->id(), "{$this->paragraphs[2]->id()};{$this->paragraphs[0]->uuid()}"],
    ];
    $import_file = $this->createCsvFile($data);

    $mapping = [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'field_paragraphs', 'source' => 'Paragraphs'],
    ];

    $result = $this->importCsvFile($import_file, $mapping);

    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated_node = $this->entityTypeManager->getStorage('node')->load($this->node->id());
    $refs = $updated_node->get('field_paragraphs')->referencedEntities();
    $this->assertCount(2, $refs);

    // Testing ordering and existence.
    $this->assertEquals($this->paragraphs[2]->id(), $refs[0]->id());
    $this->assertEquals($this->paragraphs[0]->id(), $refs[1]->id());
  }

}
