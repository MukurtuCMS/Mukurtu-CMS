<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

class CsvExportEntityReferenceRevisions extends CsvExportFieldTestBase {

  protected $node;
  protected $paragraphs;

  protected static $modules = [
    'entity_reference_revisions',
    'file',
    'paragraphs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('paragraphs_type');
    $this->installEntitySchema('paragraph');

    // Create a paragraph type with a text field.
    $paragraphType = $this->container->get('entity_type.manager')
      ->getStorage('paragraphs_type')
      ->create(['id' => 'text_paragraph', 'label' => 'Text Paragraph']);
    $paragraphType->save();

    // Add a text field to the paragraph type.
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'type' => 'text_long',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'paragraph',
      'bundle' => 'text_paragraph',
      'label' => 'Text',
    ])->save();

    // Attach a paragraph field to the 'protocol_aware_content' node type.
    FieldStorageConfig::create([
      'field_name' => 'field_paragraph',
      'entity_type' => 'node',
      'cardinality' => -1,
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_paragraph',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Paragraph',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => [
          'target_bundles' => ['text_paragraph' => 'text_paragraph'],
        ],
      ],
    ])->save();

    foreach (range(0,2) as $i) {
      $paragraph = Paragraph::create([
        'type' => 'text_paragraph',
        'field_text' => "Sample text $i",
      ]);
      $paragraph->save();
      $this->paragraphs[$i] = $paragraph;
    }

    $node = Node::create([
      'title' => 'Testing Export',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_paragraph' => [
        ['target_id' => $this->paragraphs[0]->id(), 'target_revision_id' => $this->paragraphs[0]->getRevisionId()],
        ['target_id' => $this->paragraphs[1]->id(), 'target_revision_id' => $this->paragraphs[1]->getRevisionId()],
        ['target_id' => $this->paragraphs[2]->id(), 'target_revision_id' => $this->paragraphs[2]->getRevisionId()],
      ],
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;

    $this->event = new EntityFieldExportEvent('csv', $this->node, 'field_paragraph', $this->context);
  }

  /**
   * Test exporting an entity revisions field.
   */
  public function testEntityRevisionsFieldExport() {
    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->paragraphs[0]->id(), $this->paragraphs[1]->id(), $this->paragraphs[2]->id()], $this->event->getValue());
  }

  /**
   * Test exporting an entity revisions field, with the referenced entity additionally exported.
   */
  public function testEntityRevisionsFieldWithEntityExport() {
    // Set the export config to export the referenced entity for paragraph fields.
    $this->export_config->setEntityReferenceSetting('paragraph', 'entity');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->paragraphs[0]->id(), $this->paragraphs[1]->id(), $this->paragraphs[2]->id()], $this->event->getValue());

    // Check if additional entity IDs have been added to the context.
    $this->assertCount(3, $this->event->context['results']['entities']['paragraph']);

    // Order isn't guaranteed (or important) for addtional entity exports.
    $this->assertContains($this->paragraphs[0]->id(), $this->event->context['results']['entities']['paragraph']);
    $this->assertContains($this->paragraphs[1]->id(), $this->event->context['results']['entities']['paragraph']);
    $this->assertContains($this->paragraphs[2]->id(), $this->event->context['results']['entities']['paragraph']);
  }

  /**
   * Test exporting an entity revisions field as UUIDs.
   */
  public function testEntityRevisionsFieldUuidExport() {
    $this->export_config->setIdFieldSetting('uuid');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);
    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->paragraphs[0]->uuid(), $this->paragraphs[1]->uuid(), $this->paragraphs[2]->uuid()], $this->event->getValue());
  }

  /**
   * Test exporting an entity revisions field as UUIDs, with the referenced entity additionally exported.
   */
  public function testEntityRevisionsFieldUuidWithEntityExport() {
    $this->export_config->setIdFieldSetting('uuid');
    $this->export_config->setEntityReferenceSetting('paragraph', 'entity');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->paragraphs[0]->uuid(), $this->paragraphs[1]->uuid(), $this->paragraphs[2]->uuid()], $this->event->getValue());

    // Check if additional entity IDs have been added to the context.
    $this->assertCount(3, $this->event->context['results']['entities']['paragraph']);

    // Order isn't guaranteed (or important) for addtional entity exports.
    // Even though we are exporting UUIDs above, the additional entity exports in the context should be normal entity IDs.
    $this->assertContains($this->paragraphs[0]->id(), $this->event->context['results']['entities']['paragraph']);
    $this->assertContains($this->paragraphs[1]->id(), $this->event->context['results']['entities']['paragraph']);
    $this->assertContains($this->paragraphs[2]->id(), $this->event->context['results']['entities']['paragraph']);
  }

}
