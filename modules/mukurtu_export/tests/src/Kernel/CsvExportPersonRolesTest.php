<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests exporting person field roles as "Name>Role".
 */
#[Group('mukurtu_export')]
class CsvExportPersonRolesTest extends CsvExportFieldTestBase {

  /**
   * Terms by name.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms = [];

  /**
   * The exported node.
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Vocabulary::create(['vid' => 'creator', 'name' => 'Creator'])->save();
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_creator',
      'entity_type' => 'node',
      'type' => 'mukurtu_entity_reference_role',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_creator',
      'entity_type' => 'node',
      'bundle' => 'protocol_aware_content',
      'label' => 'Creator',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['creator' => 'creator']],
        'role_target_bundles' => ['role' => 'role'],
      ],
    ])->save();

    foreach (['creator' => ['Eunice Kitto', 'Alice Fletcher', 'Mary Jones'], 'role' => ['Singer', 'Writer']] as $vid => $names) {
      foreach ($names as $name) {
        $this->terms[$name] = Term::create(['vid' => $vid, 'name' => $name]);
        $this->terms[$name]->save();
      }
    }

    $this->node = Node::create([
      'title' => 'Recording',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_creator' => [
        ['target_id' => $this->terms['Eunice Kitto']->id(), 'role_target_id' => $this->terms['Singer']->id()],
        ['target_id' => $this->terms['Alice Fletcher']->id()],
        ['target_id' => $this->terms['Mary Jones']->id(), 'role_target_id' => $this->terms['Writer']->id()],
      ],
    ]);
    $this->node->setSharingSetting('any');
    $this->node->setProtocols([$this->protocol]);
    $this->node->save();
  }

  /**
   * Exports field_creator and returns its values.
   */
  protected function exportCreators(): array {
    $event = new EntityFieldExportEvent('csv', $this->node, 'field_creator', $this->context);
    $this->fieldExporter->exportField($event);
    return $event->getValue();
  }

  /**
   * Each person with a role is exported as "Name>Role".
   */
  public function testRolesExportAfterNames(): void {
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'name')->save();
    $this->assertSame(['Eunice Kitto>Singer', 'Alice Fletcher', 'Mary Jones>Writer'], $this->exportCreators());
  }

  /**
   * With IDs or UUIDs for people, roles are still written as names.
   */
  public function testRolesStayNamesWithIdExports(): void {
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'id')->save();
    $this->assertEquals([
      $this->terms['Eunice Kitto']->id() . '>Singer',
      $this->terms['Alice Fletcher']->id(),
      $this->terms['Mary Jones']->id() . '>Writer',
    ], $this->exportCreators());

    $this->export_config->setIdFieldSetting('uuid')->save();
    $this->assertSame([
      $this->terms['Eunice Kitto']->uuid() . '>Singer',
      $this->terms['Alice Fletcher']->uuid(),
      $this->terms['Mary Jones']->uuid() . '>Writer',
    ], $this->exportCreators());
  }

  /**
   * People without roles export as plain names, exactly as before.
   */
  public function testNoRolesExportsPlainNames(): void {
    foreach ($this->node->get('field_creator') as $item) {
      $item->role_target_id = NULL;
    }
    $this->node->save();
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'name')->save();
    $this->assertSame(['Eunice Kitto', 'Alice Fletcher', 'Mary Jones'], $this->exportCreators());
  }

  /**
   * The exporter offers no separate role column.
   */
  public function testNoSeparateRoleColumn(): void {
    $fields = CsvExporter::create(['id' => 'new_exporter', 'label' => 'New'])->getMappedFields('node', 'protocol_aware_content');
    $names = array_column($fields, 'field_name');
    $this->assertContains('field_creator', $names);
    $this->assertEmpty(array_filter($names, fn ($name) => str_starts_with($name, 'field_creator/')));
  }

}
