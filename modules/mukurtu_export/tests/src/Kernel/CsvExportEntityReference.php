<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\node\Entity\Node;

class CsvExportEntityReference extends CsvExportFieldTestBase {

  protected $node;
  protected $refs;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Attach an entity reference field to the 'protocol_aware_content' type.
    FieldStorageConfig::create([
      'field_name' => 'field_entity_ref',
      'entity_type' => 'node',
      'cardinality' => -1,
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_entity_ref',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Reference Field',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['protocol_aware_content' => 'protocol_aware_content'],
        ],
      ],
    ])->save();

    // Create some nodes to use as references.
    foreach (range(0,2) as $i) {
      $ref = Node::create([
        'title' => "Reference $i",
        'type' => 'protocol_aware_content',
        'status' => TRUE,
        'uid' => $this->currentUser->id(),
      ]);
      $ref->setSharingSetting('any');
      $ref->setProtocols([$this->protocol]);
      $ref->save();
      $this->refs[$i] = $ref;
    }

    $node = Node::create([
      'title' => 'Testing Export',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_entity_ref' => [
        ['target_id' => $this->refs[0]->id()],
        ['target_id' => $this->refs[1]->id()],
        ['target_id' => $this->refs[2]->id()],
      ],
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;

    $this->event = new EntityFieldExportEvent('csv', $this->node, 'field_entity_ref', $this->context);
  }

  /**
   * Test exporting an entity reference field.
   */
  public function testEntityReferenceFieldExport() {
    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->refs[0]->id(), $this->refs[1]->id(), $this->refs[2]->id()], $this->event->getValue());
  }

  /**
   * Test exporting an entity reference field, with the referenced entity additionally exported.
   */
  public function testEntityReferenceFieldWithEntityExport() {
    // Set the export config to export the referenced entity for node reference fields.
    $this->export_config->setEntityReferenceSetting('node', 'entity');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->refs[0]->id(), $this->refs[1]->id(), $this->refs[2]->id()], $this->event->getValue());

    // Check if additional entity IDs have been added to the context.
    $this->assertCount(3, $this->event->context['results']['entities']['node']);

    // Order isn't guaranteed (or important) for addtional entity exports.
    $this->assertContains($this->refs[0]->id(), $this->event->context['results']['entities']['node']);
    $this->assertContains($this->refs[1]->id(), $this->event->context['results']['entities']['node']);
    $this->assertContains($this->refs[2]->id(), $this->event->context['results']['entities']['node']);
  }

  /**
   * Test exporting an entity reference field as UUIDs.
   */
   public function testEntityReferenceFieldUuidExport() {
    $this->export_config->setIdFieldSetting('uuid');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);
    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->refs[0]->uuid(), $this->refs[1]->uuid(), $this->refs[2]->uuid()], $this->event->getValue());
  }

  /**
   * Test exporting an entity reference field as UUIDs, with the referenced entity additionally exported.
   */
  public function testEntityReferenceFieldUuidWithEntityExport() {
    $this->export_config->setIdFieldSetting('uuid');
    $this->export_config->setEntityReferenceSetting('node', 'entity');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->refs[0]->uuid(), $this->refs[1]->uuid(), $this->refs[2]->uuid()], $this->event->getValue());

    // Check if additional entity IDs have been added to the context.
    $this->assertCount(3, $this->event->context['results']['entities']['node']);

    // Order isn't guaranteed (or important) for addtional entity exports.
    // Even though we are exporting UUIDs above, the additional entity exports in the context should be normal entity IDs.
    $this->assertContains($this->refs[0]->id(), $this->event->context['results']['entities']['node']);
    $this->assertContains($this->refs[1]->id(), $this->event->context['results']['entities']['node']);
    $this->assertContains($this->refs[2]->id(), $this->event->context['results']['entities']['node']);
  }

}
