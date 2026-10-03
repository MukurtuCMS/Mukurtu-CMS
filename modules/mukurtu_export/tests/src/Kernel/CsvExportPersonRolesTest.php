<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_core\Plugin\Field\FieldType\EntityReferenceRoleItem;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests exporting person field roles as a "Field > Role" column.
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
   * Exports the role column and returns its values.
   */
  protected function exportRoles(): array {
    $event = new EntityFieldExportEvent('csv', $this->node, 'field_creator/role_target_id', $this->context);
    $this->fieldExporter->exportField($event);
    return $event->getValue();
  }

  /**
   * Roles export in the names' order, blank where there is none.
   */
  public function testRolesExportAlignedWithNames(): void {
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'name')->save();
    $this->assertSame(['Singer', '', 'Writer'], $this->exportRoles());

    // The names column is unchanged and lines up with the roles.
    $event = new EntityFieldExportEvent('csv', $this->node, 'field_creator', $this->context);
    $this->fieldExporter->exportField($event);
    $this->assertSame(['Eunice Kitto', 'Alice Fletcher', 'Mary Jones'], $event->getValue());
  }

  /**
   * ID-based exports write role IDs or UUIDs.
   */
  public function testRolesExportAsIdsAndUuids(): void {
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'id')->save();
    $this->assertEquals([$this->terms['Singer']->id(), '', $this->terms['Writer']->id()], $this->exportRoles());

    $this->export_config->setIdFieldSetting('uuid')->save();
    $this->assertSame([$this->terms['Singer']->uuid(), '', $this->terms['Writer']->uuid()], $this->exportRoles());
  }

  /**
   * A field with no roles exports an empty cell, not a row of delimiters.
   */
  public function testNoRolesExportsEmpty(): void {
    foreach ($this->node->get('field_creator') as $item) {
      $item->role_target_id = NULL;
    }
    $this->node->save();
    $this->export_config->setEntityReferenceSetting('taxonomy_term', 'name')->save();
    $this->assertSame([], $this->exportRoles());
  }

  /**
   * New exporters include the role column only where roles are turned on.
   */
  public function testRoleColumnDefaultFollowsSetting(): void {
    $role_column = function (): array {
      $fields = CsvExporter::create(['id' => 'new_exporter', 'label' => 'New'])->getMappedFields('node', 'protocol_aware_content');
      $matches = array_values(array_filter($fields, fn ($f) => $f['field_name'] === 'field_creator/role_target_id'));
      $this->assertCount(1, $matches);
      return $matches[0];
    };

    $column = $role_column();
    $this->assertSame('Creator > Role', $column['csv_header_label']);
    $this->assertFalse($column['export']);

    $this->config(EntityReferenceRoleItem::SETTINGS)->set('enabled_fields', ['field_creator'])->save();
    $this->assertTrue($role_column()['export']);
  }

}
