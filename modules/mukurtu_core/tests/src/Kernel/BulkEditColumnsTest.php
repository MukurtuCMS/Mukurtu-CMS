<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\BulkEdit\BulkEditColumns;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which columns the bulk edit page offers, shows and remembers.
 */
#[Group('mukurtu_core')]
class BulkEditColumnsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_operations',
  ];

  /**
   * The service under test.
   */
  protected BulkEditColumns $columns;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('og_membership');
    \Drupal::service(NodeAccessRebuild::class)->rebuild();

    // Created in a different order from BulkEditColumns::TYPES on purpose.
    $types = ['place' => 'Place', 'digital_heritage' => 'Digital Heritage', 'page' => 'Basic page'];
    foreach ($types as $type => $name) {
      NodeType::create(['type' => $type, 'name' => $name])->save();
    }
    foreach (['field_keywords' => 'Keywords', 'field_summary' => 'Summary'] as $field => $label) {
      FieldStorageConfig::create(['field_name' => $field, 'entity_type' => 'node', 'type' => 'string'])->save();
      FieldConfig::create([
        'field_name' => $field,
        'entity_type' => 'node',
        'bundle' => 'digital_heritage',
        'label' => $label,
      ])->save();
    }

    $this->columns = $this->container->get('mukurtu_core.bulk_edit_columns');
  }

  /**
   * Only existing types from the list get a page, in the list's order.
   */
  public function testTypes(): void {
    $this->assertSame(['digital_heritage', 'place'], array_keys($this->columns->types()));
  }

  /**
   * Without a bulk_edit form mode, keywords and category stand in.
   */
  public function testAvailableWithoutFormMode(): void {
    $available = array_map('strval', $this->columns->available('digital_heritage'));

    $this->assertSame([
      'langcode' => 'Language',
      'protocols' => 'Cultural Protocols',
      'sharing' => 'Sharing Setting',
      'community' => 'Community',
      'field_keywords' => 'Keywords',
      'changed' => 'Updated',
    ], $available);
  }

  /**
   * With a bulk_edit form mode, its fields are offered in its order.
   */
  public function testAvailableFollowsFormMode(): void {
    EntityFormMode::create(['id' => 'node.bulk_edit', 'label' => 'Bulk edit', 'targetEntityType' => 'node'])->save();
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'digital_heritage',
      'mode' => 'bulk_edit',
      'status' => TRUE,
    ]);
    // A new display starts with base fields such as uid and created; a real
    // bulk_edit form mode leaves out what bulk editing shouldn't change.
    foreach (array_keys($display->getComponents()) as $name) {
      $display->removeComponent($name);
    }
    $display
      ->setComponent('field_summary', ['type' => 'string_textfield', 'weight' => 1])
      ->setComponent('title', ['type' => 'string_textfield', 'weight' => 0])
      ->setComponent('field_keywords', ['type' => 'string_textfield', 'weight' => 2])
      ->save();

    $this->assertSame(
      ['langcode', 'protocols', 'sharing', 'community', 'field_summary', 'field_keywords', 'changed'],
      array_keys($this->columns->available('digital_heritage')),
    );
  }

  /**
   * Editors see the defaults until they choose, then their own choice.
   */
  public function testSelectedAndSave(): void {
    $editor = User::create(['name' => 'editor']);
    $editor->save();
    $other = User::create(['name' => 'other']);
    $other->save();

    $this->assertSame(['langcode', 'protocols', 'sharing', 'changed'], $this->columns->selected('digital_heritage', $editor));

    $this->columns->save('digital_heritage', $editor, ['changed', 'field_keywords', 'not_a_column']);

    // Saved in display order, unknown columns dropped, per editor and type.
    $this->assertSame(['field_keywords', 'changed'], $this->columns->selected('digital_heritage', $editor));
    $this->assertSame(['langcode', 'protocols', 'sharing', 'changed'], $this->columns->selected('digital_heritage', $other));
    $this->assertSame(['langcode', 'protocols', 'sharing', 'changed'], $this->columns->selected('place', $editor));

    // An empty choice is a choice: only the title column remains.
    $this->columns->save('digital_heritage', $editor, []);
    $this->assertSame([], $this->columns->selected('digital_heritage', $editor));
  }

  /**
   * The bulk edit route needs the "Use bulk editing" permission.
   */
  public function testRouteAccess(): void {
    // Burn uid 1, which bypasses every permission.
    User::create(['name' => 'admin'])->save();
    $without = User::create(['name' => 'without']);
    $without->save();
    Role::create(['id' => 'bulk', 'label' => 'Bulk'])->grantPermission('use mukurtu bulk edit')->save();
    $with = User::create(['name' => 'with', 'roles' => ['bulk']]);
    $with->save();

    $access = $this->container->get('access_manager');
    $this->assertFalse($access->checkNamedRoute('mukurtu_core.bulk_edit', [], $without));
    $this->assertTrue($access->checkNamedRoute('mukurtu_core.bulk_edit', [], $with));
  }

}
