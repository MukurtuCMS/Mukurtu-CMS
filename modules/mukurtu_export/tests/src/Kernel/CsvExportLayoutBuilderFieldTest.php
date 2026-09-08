<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\node\Entity\Node;

/**
 * Test that a bundle's Layout Builder section field doesn't crash CSV export
 * (the field stores structured Section objects, not exportable scalar data).
 */
class CsvExportLayoutBuilderFieldTest extends CsvExportFieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_discovery',
  ];

  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // LayoutBuilderEntityViewDisplay::postSave() looks up reusable
    // block_content entities; the entity type must exist even though this
    // test doesn't create any.
    $this->installEntitySchema('block_content');

    LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'protocol_aware_content',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    $node = Node::create([
      'title' => 'Testing Layout Builder Export',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      OverridesSectionStorage::FIELD_NAME => [
        ['section' => new Section('layout_onecol')],
      ],
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Exporting the section field must not crash (issue found via PR #2103
   * testing: implode() cannot stringify the field's Section objects).
   */
  public function testLayoutBuilderFieldExportDoesNotCrash() {
    $event = new EntityFieldExportEvent('csv', $this->node, OverridesSectionStorage::FIELD_NAME, $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertSame([], $event->getValue());
  }

  /**
   * New export configs should not offer the section field as mappable,
   * since it has no meaningful flat CSV representation.
   */
  public function testLayoutBuilderFieldExcludedFromMappedFields() {
    $exporter = CsvExporter::create([
      'id' => 'test_layout_builder_exporter',
      'label' => 'Test Layout Builder Exporter',
    ]);

    $fieldNames = array_column($exporter->getMappedFields('node', 'protocol_aware_content'), 'field_name');
    $this->assertNotContains(OverridesSectionStorage::FIELD_NAME, $fieldNames);
  }

}
