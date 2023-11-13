<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

class CsvExportTaxonomyTerms extends CsvExportFieldTestBase {

  protected $node;
  protected $terms;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $vocabulary = Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords']);
    $vocabulary->save();

    // Attach an entity reference field to the 'protocol_aware_content' type.
    FieldStorageConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Keywords',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'keywords' => 'keywords',
          ],
          'auto_create' => TRUE,
        ],
      ],
    ])->save();

    // Create some terms to use as references.
    foreach (range(0,2) as $i) {
      $term = Term::create([
        'name' => "Term $i",
        'vid' => 'keywords',
        'status' => TRUE,
        'uid' => $this->currentUser->id(),
      ]);
      $term->save();
      $this->terms[$i] = $term;
    }

    $node = Node::create([
      'title' => 'Testing Export',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_keywords' => [
        ['target_id' => $this->terms[0]->id()],
        ['target_id' => $this->terms[1]->id()],
        ['target_id' => $this->terms[2]->id()],
      ],
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;

    $this->event = new EntityFieldExportEvent('csv', $this->node, 'field_keywords', $this->context);
  }

  /**
   * Test exporting a taxonomy entity reference field as IDs.
   */
  public function testTaxonomyReferenceFieldExport() {
    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->terms[0]->id(), $this->terms[1]->id(), $this->terms[2]->id()], $this->event->getValue());
  }

  /**
   * Test exporting a taxonomy entity reference field, with the term entities additionally exported.
   */
  public function testTaxonomyReferenceFieldWithEntityExport() {
    // Set the export config to export the referenced term entities.
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'entity');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->terms[0]->id(), $this->terms[1]->id(), $this->terms[2]->id()], $this->event->getValue());

    // Check if additional entity IDs have been added to the context.
    $this->assertCount(3, $this->event->context['results']['entities']['taxonomy_term']);

    // Order isn't guaranteed (or important) for addtional entity exports.
    $this->assertContains($this->terms[0]->id(), $this->event->context['results']['entities']['taxonomy_term']);
    $this->assertContains($this->terms[1]->id(), $this->event->context['results']['entities']['taxonomy_term']);
    $this->assertContains($this->terms[2]->id(), $this->event->context['results']['entities']['taxonomy_term']);
  }

  /**
   * Test exporting a taxonomy entity reference field as UUIDs.
   */
   public function testTaxonomyReferenceFieldUuidExport() {
    $this->export_config->setIdFieldSetting('uuid');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);
    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->terms[0]->uuid(), $this->terms[1]->uuid(), $this->terms[2]->uuid()], $this->event->getValue());
  }

  /**
   * Test exporting a taxonomy entity reference field as UUIDs, with the referenced entity additionally exported.
   */
  public function testTaxonomyReferenceFieldUuidWithEntityExport() {
    $this->export_config->setIdFieldSetting('uuid');
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'entity');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);

    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->terms[0]->uuid(), $this->terms[1]->uuid(), $this->terms[2]->uuid()], $this->event->getValue());

    // Check if additional entity IDs have been added to the context.
    $this->assertCount(3, $this->event->context['results']['entities']['taxonomy_term']);

    // Order isn't guaranteed (or important) for addtional entity exports.
    // Even though we are exporting UUIDs above, the additional entity exports in the context should be normal entity IDs.
    $this->assertContains($this->terms[0]->id(), $this->event->context['results']['entities']['taxonomy_term']);
    $this->assertContains($this->terms[1]->id(), $this->event->context['results']['entities']['taxonomy_term']);
    $this->assertContains($this->terms[2]->id(), $this->event->context['results']['entities']['taxonomy_term']);
  }

  /**
   * Test exporting a taxonomy entity reference field as names.
   */
  public function testTaxonomyReferenceFieldNameExport() {
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'name');
    $this->export_config->save();

    $this->fieldExporter->exportField($this->event);
    $this->assertCount(3, $this->event->getValue());
    $this->assertEquals([$this->terms[0]->getName(), $this->terms[1]->getName(), $this->terms[2]->getName()], $this->event->getValue());
  }

}
