<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40100(), which enables drawCircle by default.
 *
 * The real content types (place, digital_heritage, etc.) each pull in a
 * heavy module dependency chain, so this exercises the hook's actual
 * config-manipulation logic against a hand-built 'place' node type and
 * field_coverage field rather than installing mukurtu_place itself.
 *
 * @see mukurtu_core_update_40100()
 */
#[Group('mukurtu_core')]
class DrawCircleDefaultUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'geofield',
    'leaflet',
    'file',
    'image',
    'media',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

    NodeType::create(['type' => 'place', 'name' => 'Place'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'type' => 'geofield',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'bundle' => 'place',
    ])->save();

    \Drupal::moduleHandler()->loadInclude('mukurtu_core', 'install');
  }

  /**
   * The update hook flips drawCircle to TRUE on an existing widget.
   */
  public function testUpdateEnablesDrawCircle(): void {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'place',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_coverage', [
      'type' => 'geofield_mukurtu',
      'settings' => ['toolbar' => ['drawCircle' => FALSE]],
    ]);
    $display->save();

    mukurtu_core_update_40100();

    $display = EntityFormDisplay::load('node.place.default');
    $component = $display->getComponent('field_coverage');
    $this->assertTrue($component['settings']['toolbar']['drawCircle']);
  }

  /**
   * The update hook is a no-op when there's no matching display.
   */
  public function testUpdateIsNoOpWithoutDisplay(): void {
    $this->assertNull(EntityFormDisplay::load('node.place.default'));
    mukurtu_core_update_40100();
    $this->assertNull(EntityFormDisplay::load('node.place.default'));
  }

  /**
   * A field_coverage component using a different widget is left untouched.
   *
   * Geofield_default has no "toolbar" setting at all, so the meaningful
   * assertion is that the hook's type guard prevents it from ever writing
   * one in (which would otherwise be a silent no-op change, not a crash).
   */
  public function testUpdateSkipsOtherWidgets(): void {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'place',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_coverage', ['type' => 'geofield_default']);
    $display->save();

    mukurtu_core_update_40100();

    $display = EntityFormDisplay::load('node.place.default');
    $component = $display->getComponent('field_coverage');
    $this->assertSame('geofield_default', $component['type']);
    $this->assertArrayNotHasKey('toolbar', $component['settings']);
  }

}
